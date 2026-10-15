"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { ContactFields, GeneralInfoFields, PassportFields, PersonalFields } from "@/components/ParticularsFields";
import { Alert, Button, Field, Input, Panel, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application, EnrollmentCenter } from "@/lib/types";
import { APPOINTMENT_FIELDS, CONTACT_FIELDS, fieldError, PASSPORT_FIELDS, PERSONAL_FIELDS, validate } from "@/lib/validation";

/** 8-Step Application Process */
const STEPS = [
  "General Information",
  "Personal details",
  "Travel / Identity document",
  "Residence & contacts",
  "Documents",
  "Review & declaration",
  "Fee payment",
  "Biometrics appointment",
];

const MAX_BYTES = 2 * 1024 * 1024;

type DocSpec = { type: string; label: string; required: boolean; accept: string; kinds: string };

const PHOTO: DocSpec = { type: "photo", label: "Passport photograph (white background)", required: true, accept: "image/jpeg,image/png", kinds: "JPG or PNG" };

/** Category-dependent document requirements */
function getDocuments(category?: string): DocSpec[] {
  const cat = (category || "WORK").toUpperCase();
  const catDoc: DocSpec =
    cat === "STUDENT"
      ? { type: "admission_letter", label: "Institution Admission Letter", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" }
      : cat === "BUSINESS"
      ? { type: "cac_certificate", label: "CAC Business Registration Certificate / Documents", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" }
      : { type: "appointment_letter", label: "Employment Appointment / Offer Letter", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" };

  return [
    { type: "passport_copy", label: "International Passport data page OR ECOWAS Travel Certificate", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
    { type: "domicile_proof", label: "Proof of domicile in Nigeria (Utility bill / Tenancy)", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
    catDoc,
    { type: "residence_visa", label: "Subject-to-Regularization / Entry visa (if applicable)", required: false, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
    { type: "additional", label: "Additional supporting document", required: false, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
  ];
}

/** Which step each field lives on (to jump back when the API rejects one). */
const FIELD_STEP: Record<string, number> = {
  nationality: 1, category: 1, travel_document_type: 1,
  ...Object.fromEntries(PERSONAL_FIELDS.filter((f) => f !== "nationality").map((f) => [f, 2])),
  blood_group: 2, renewal_card_number: 2, photo: 2, principal_application_id: 2, dependant_relationship: 2, phone: 2, email: 2,
  ...Object.fromEntries(PASSPORT_FIELDS.map((f) => [f, 3])),
  travel_document_type_doc: 3,
  ...Object.fromEntries(CONTACT_FIELDS.map((f) => [f, 4])),
  home_country_address: 4,
  documents: 5,
  declaration: 6,
  payment_reference: 7,
  ...Object.fromEntries(APPOINTMENT_FIELDS.map((f) => [f, 8])),
};

type DraftDoc = { id: number; type: string; label: string; original_name: string; size_bytes: number; mime_type: string };
type AppType = "NEW" | "RENEWAL" | "REPLACE";
type Draft = { type: AppType; current_step: number; data: Record<string, string>; documents: DraftDoc[]; saved_at: string };

type DocUploadState = {
  status: "idle" | "uploading" | "success" | "error";
  fileName?: string;
  fileSize?: number;
  previewUrl?: string;
  isPdf?: boolean;
  error?: string;
  pendingFile?: File;
};

function kb(bytes: number) {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Short-lived link to an uploaded draft document. */
async function documentUrl(id: number): Promise<string> {
  const { url } = await api<{ url: string }>("applicant", `draft/documents/${id}`);
  return url;
}

function Wizard() {
  const router = useRouter();
  const params = useSearchParams();
  const { t } = useI18n();
  const [loaded, setLoaded] = useState(false);
  const [step, setStep] = useState(1);
  const [type, setType] = useState<AppType>(() => {
    const t = (params.get("type") ?? "").toUpperCase();
    return t === "RENEWAL" || t === "REPLACE" ? t : "NEW";
  });
  const [data, setData] = useState<Record<string, string>>({
    category: "WORK",
    travel_document_type: "PASSPORT",
  });
  const [documents, setDocuments] = useState<DraftDoc[]>([]);
  const [touched, setTouched] = useState<Set<string>>(new Set());
  const [focusedField, setFocusedField] = useState<string | null>(null);
  const [docStates, setDocStates] = useState<Record<string, DocUploadState>>({});
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [stepError, setStepError] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<{ tone: "success" | "danger" | "info"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [paid, setPaid] = useState(false);
  const [fee, setFee] = useState<number | null>(null);
  const [photoLink, setPhotoLink] = useState<{ id: number; url: string } | null>(null);
  const [localPhotoPreview, setLocalPhotoPreview] = useState<string | null>(null);
  const [photoUploadStatus, setPhotoUploadStatus] = useState<"idle" | "uploading" | "success" | "error">("idle");
  const [preview, setPreview] = useState<{ url: string; name: string } | null>(null);

  const initialLoadedRef = useRef(false);
  const isUploadingPhotoRef = useRef(false);

  const set = useCallback((key: string, value: string) => {
    setData((d) => ({ ...d, [key]: value }));
    setServerErrors((e) => (e[key] ? { ...e, [key]: "" } : e));
    setStepError((e) => (e[key] ? { ...e, [key]: "" } : e));
  }, []);
  const touch = useCallback((key: string) => setTouched((t) => (t.has(key) ? t : new Set(t).add(key))), []);

  const photo = documents.find((d) => d.type === "photo");
  const photoUrl = photo && photoLink?.id === photo.id ? photoLink.url : null;
  const currentDocs = useMemo(() => getDocuments(data.category), [data.category]);

  /** Fields validated on each step. */
  const stepFields = useCallback(
    (n: number) =>
      n === 1
        ? ["nationality", "category", "travel_document_type"]
        : n === 2
        ? [...(type !== "NEW" ? ["renewal_card_number"] : []), ...PERSONAL_FIELDS.filter((f) => f !== "phone" && f !== "email" && f !== "nationality")]
        : n === 3
        ? PASSPORT_FIELDS
        : n === 4
        ? CONTACT_FIELDS
        : n === 8
        ? APPOINTMENT_FIELDS
        : [],
    [type],
  );

  /** Errors that are not about a single form field (photo, documents, payment, declaration). */
  const extraErrors = useCallback(
    (n: number): Record<string, string> => {
      const e: Record<string, string> = {};
      if (n === 2 && !photo && !localPhotoPreview) e.photo = "Upload your passport photograph.";
      if (n === 5) {
        const requiredDocs = getDocuments(data.category);
        const absent = requiredDocs.filter((d) => d.required && !documents.some((x) => x.type === d.type));
        if (absent.length) e.documents = `Please upload: ${absent.map((d) => d.label).join(", ")}.`;
      }
      if (n === 6 && data.declaration !== "1") e.declaration = "You must accept the declaration before you pay.";
      if (n === 7 && !paid) e.payment_reference = "Pay the residence card fee before you continue.";
      return e;
    },
    [photo, localPhotoPreview, documents, paid, data.declaration, data.category],
  );

  // Errors shown: fields are validated on blur or step submit; in-progress typing is not prematurely flagged red.
  const errors = useMemo(() => {
    const shown: Record<string, string> = {};
    for (const field of touched) {
      // Suppress premature errors while user is actively focused on field and typing
      if (focusedField === field && (data[field]?.trim()?.length ?? 0) > 0) {
        continue;
      }
      const message = fieldError(field, data);
      if (message) shown[field] = message;
    }
    for (const [field, message] of Object.entries(serverErrors)) {
      if (message && focusedField !== field) shown[field] = message;
    }
    for (const [field, message] of Object.entries(stepError)) {
      if (message && (!data[field] || ["photo", "documents", "declaration", "payment_reference"].includes(field))) {
        if (focusedField !== field || !data[field]) {
          shown[field] = message;
        }
      }
    }
    return shown;
  }, [touched, data, serverErrors, stepError, focusedField]);

  const currentStepHasErrors = [...stepFields(step), "photo", "documents", "payment_reference", "declaration"].some((f) => errors[f] && FIELD_STEP[f] === step);

  const saveDraft = useCallback(
    async (nextStep: number, patch: Record<string, string> = {}) => {
      const body = { type, current_step: nextStep, data: { ...data, ...patch } };
      const result = await api<{ draft: Draft }>("applicant", "draft", { method: "PUT", json: body });
      setDocuments(result.draft.documents);
      return result.draft;
    },
    [type, data],
  );

  // Load draft (or pre-fill from the profile) and handle the Paystack return.
  useEffect(() => {
    if (initialLoadedRef.current) return;
    initialLoadedRef.current = true;

    (async () => {
      try {
        const [{ draft }, me, centerList] = await Promise.all([
          api<{ draft: Draft | null }>("applicant", "draft"),
          api<{ surname: string; forenames: string; email: string; phone: string }>("applicant", "me"),
          api<{ data: EnrollmentCenter[]; fee_naira: number }>("public", "enrollment-centers"),
        ]);
        setFee(centerList.fee_naira);

        // A new application for a spouse or child (from the portal home page).
        const relationship = (params.get("for") ?? "").toUpperCase();
        const isDependantFlow = Boolean(params.get("principal") && ["SPOUSE", "CHILD"].includes(relationship));

        let restored: Record<string, string> = {
          nationality: "",
          category: "WORK",
          travel_document_type: "PASSPORT",
          surname: me.surname,
          forenames: isDependantFlow ? "" : me.forenames,
        };

        if (isDependantFlow) {
          restored.principal_application_id = params.get("principal") as string;
          restored.dependant_relationship = relationship;
          // Dependant flow starts afresh: previous draft of principal is not loaded
          restored.phone = me.phone;
          restored.email = me.email;
        } else {
          if (!draft && params.get("card")) restored.renewal_card_number = (params.get("card") ?? "").replace(/\D/g, "");
          if (draft) {
            restored = { ...restored, ...draft.data };
            setType(draft.type);
            setStep(draft.current_step);
            setDocuments(draft.documents);
            setNotice({ tone: "info", text: `Restored your application saved on ${new Date(draft.saved_at).toLocaleString("en-GB")}.` });
          }
          restored.phone = me.phone;
          restored.email = me.email;
        }

        const reference = params.get("reference") || params.get("trxref") || restored.payment_reference;
        if (reference) {
          const payment = await api<{ paid: boolean; used: boolean }>("applicant", `payments/${encodeURIComponent(reference)}/verify`, { method: "POST" }).catch(() => null);
          if (payment?.paid && !payment.used) {
            restored.payment_reference = reference;
            setPaid(true);
            if (params.get("payment") === "callback") {
              setStep(7);
              setNotice({ tone: "success", text: "Payment confirmed by Paystack. Click “Save & continue” to book your biometrics appointment." });
            }
          } else if (params.get("payment") === "callback") {
            setStep(7);
            setNotice({ tone: "danger", text: "We could not confirm your payment. If you were charged, contact support with your payment reference." });
          }
        }
        setData(restored);
      } catch (err) {
        setNotice({ tone: "danger", text: (err as Error).message || "Failed to load application data." });
      } finally {
        setLoaded(true);
      }
    })();
  }, []);

  // Clean up temporary local preview object URL to prevent memory leaks
  useEffect(() => {
    return () => {
      if (localPhotoPreview) {
        URL.revokeObjectURL(localPhotoPreview);
      }
    };
  }, [localPhotoPreview]);

  // Passport photograph server link (only needed when no local preview is already showing)
  useEffect(() => {
    if (!photo || localPhotoPreview) return;
    if (photoLink?.id === photo.id && photoLink.url) return;
    let cancelled = false;
    documentUrl(photo.id)
      .then((url) => {
        if (!cancelled) {
          setPhotoLink({ id: photo.id, url });
          setPhotoUploadStatus("success");
        }
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [photo, localPhotoPreview, photoLink?.id, photoLink?.url]);

  /** Check a whole step; shows every problem at once. */
  function checkStep(n: number): boolean {
    const fields = stepFields(n);
    setTouched((t) => new Set([...t, ...fields]));
    const problems = { ...validate(fields, data), ...extraErrors(n) };
    setStepError(extraErrors(n));
    return Object.keys(problems).length === 0;
  }

  function goTo(n: number) {
    setStep(n);
    setStepError({});
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function next() {
    const isAnyDocUploading = Object.values(docStates).some((s) => s.status === "uploading") || photoUploadStatus === "uploading";
    if (isAnyDocUploading) {
      setNotice({ tone: "danger", text: "Please wait for files to finish uploading before proceeding." });
      return;
    }
    if (!checkStep(step)) {
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    setBusy(true);
    try {
      await saveDraft(step + 1);
      setNotice(null);
      goTo(step + 1);
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  async function saveAndExit() {
    setBusy(true);
    await saveDraft(step);
    router.push("/portal");
  }

  async function discard() {
    if (!confirm("Discard this saved application? Uploaded documents will be removed.")) return;
    await api("applicant", "draft", { method: "DELETE" });
    router.push("/portal");
  }

  async function uploadPhotograph(file: File) {
    if (isUploadingPhotoRef.current) return;

    if (file.size > MAX_BYTES) {
      setStepError((prev) => ({ ...prev, photo: `Passport photograph: the file is ${kb(file.size)}. Please upload a file smaller than 2 MB.` }));
      setPhotoUploadStatus("error");
      return;
    }

    const validMimes = ["image/jpeg", "image/jpg", "image/png"];
    if (!validMimes.includes(file.type.toLowerCase())) {
      setStepError((prev) => ({ ...prev, photo: "Passport photograph: please upload a JPEG or PNG image." }));
      setPhotoUploadStatus("error");
      return;
    }

    if (localPhotoPreview) {
      URL.revokeObjectURL(localPhotoPreview);
    }

    const objectUrl = URL.createObjectURL(file);
    setLocalPhotoPreview(objectUrl);
    setPhotoUploadStatus("uploading");
    setStepError((prev) => {
      const next = { ...prev };
      delete next.photo;
      return next;
    });

    isUploadingPhotoRef.current = true;
    setBusy(true);

    try {
      await saveDraft(step);
      const result = await upload<{ draft: Draft; uploaded_document?: { id: number; url: string } }>(
        "applicant",
        "draft/documents",
        { type: "photo", file }
      );
      setDocuments(result.draft.documents);
      const uploadedDoc = result.draft.documents.find((d) => d.type === "photo");
      if (uploadedDoc) {
        setPhotoLink({ id: uploadedDoc.id, url: result.uploaded_document?.url || objectUrl });
      }
      setPhotoUploadStatus("success");
    } catch (e) {
      setPhotoUploadStatus("error");
      if (e instanceof ApiError) {
        setStepError((prev) => ({
          ...prev,
          photo: e.errors.file?.join(" ") ?? Object.values(e.fieldErrors())[0] ?? e.message,
        }));
      } else {
        setStepError((prev) => ({ ...prev, photo: (e as Error).message }));
      }
    } finally {
      isUploadingPhotoRef.current = false;
      setBusy(false);
    }
  }

  async function uploadDocFile(spec: DocSpec, file: File) {
    if (spec.type === "photo") {
      return uploadPhotograph(file);
    }

    const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");
    const isImage = ["image/jpeg", "image/jpg", "image/png"].includes(file.type.toLowerCase()) ||
                    /\.(jpe?g|png)$/i.test(file.name);

    if (file.size > MAX_BYTES) {
      setDocStates((prev) => ({
        ...prev,
        [spec.type]: {
          status: "error",
          fileName: file.name,
          fileSize: file.size,
          isPdf,
          error: `File size (${kb(file.size)}) exceeds maximum limit of 2 MB. Please select a smaller file.`,
          pendingFile: file,
        },
      }));
      return;
    }

    if (!isPdf && !isImage) {
      setDocStates((prev) => ({
        ...prev,
        [spec.type]: {
          status: "error",
          fileName: file.name,
          fileSize: file.size,
          isPdf: false,
          error: "Invalid file format. Please upload a PDF, JPG, or PNG document.",
          pendingFile: file,
        },
      }));
      return;
    }

    const objectUrl = URL.createObjectURL(file);
    setDocStates((prev) => {
      if (prev[spec.type]?.previewUrl) {
        try { URL.revokeObjectURL(prev[spec.type].previewUrl!); } catch (_) {}
      }
      return {
        ...prev,
        [spec.type]: {
          status: "uploading",
          fileName: file.name,
          fileSize: file.size,
          previewUrl: objectUrl,
          isPdf,
          pendingFile: file,
        },
      };
    });

    try {
      await saveDraft(step);
      const result = await upload<{ draft: Draft }>("applicant", "draft/documents", { type: spec.type, file });
      setDocuments(result.draft.documents);
      const uploaded = result.draft.documents.find((d) => d.type === spec.type);
      setDocStates((prev) => ({
        ...prev,
        [spec.type]: {
          status: "success",
          fileName: uploaded?.original_name || file.name,
          fileSize: uploaded?.size_bytes || file.size,
          previewUrl: objectUrl,
          isPdf,
        },
      }));
      setStepError((prev) => {
        const next = { ...prev };
        delete next.documents;
        return next;
      });
    } catch (e) {
      let errorMsg = (e as Error).message;
      if (e instanceof ApiError) {
        errorMsg = e.errors.file?.join(" ") ?? Object.values(e.fieldErrors())[0] ?? e.message;
      }
      setDocStates((prev) => ({
        ...prev,
        [spec.type]: {
          status: "error",
          fileName: file.name,
          fileSize: file.size,
          previewUrl: objectUrl,
          isPdf,
          error: errorMsg,
          pendingFile: file,
        },
      }));
    }
  }

  async function removeDoc(spec: DocSpec) {
    const current = documents.find((x) => x.type === spec.type);
    if (!confirm(`Are you sure you want to remove ${spec.label}?`)) return;
    setBusy(true);
    try {
      if (current) {
        const result = await api<{ draft: Draft }>("applicant", `draft/documents/${current.id}`, { method: "DELETE" });
        setDocuments(result.draft.documents);
      }
      setDocStates((prev) => {
        const next = { ...prev };
        if (next[spec.type]?.previewUrl) {
          try { URL.revokeObjectURL(next[spec.type].previewUrl!); } catch (_) {}
        }
        delete next[spec.type];
        return next;
      });
      setNotice({ tone: "info", text: `${spec.label} removed.` });
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  async function viewDoc(spec: DocSpec) {
    const current = documents.find((x) => x.type === spec.type);
    const localState = docStates[spec.type];
    if (localState?.previewUrl) {
      if (localState.isPdf) {
        window.open(localState.previewUrl, "_blank", "noopener");
      } else {
        setPreview({ url: localState.previewUrl, name: localState.fileName || spec.label });
      }
      return;
    }
    if (current) {
      try {
        const url = await documentUrl(current.id);
        const isPdf = current.mime_type === "application/pdf" || current.original_name.toLowerCase().endsWith(".pdf");
        if (isPdf) {
          window.open(url, "_blank", "noopener");
        } else {
          setPreview({ url, name: current.original_name });
        }
      } catch (e) {
        setNotice({ tone: "danger", text: (e as Error).message });
      }
    }
  }

  async function view(doc: DraftDoc) {
    try {
      const url = await documentUrl(doc.id);
      if (doc.mime_type?.startsWith("image/")) setPreview({ url, name: doc.original_name });
      else window.open(url, "_blank", "noopener");
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
    }
  }

  async function pay() {
    setBusy(true);
    try {
      const payment = await api<{ reference: string; authorization_url: string | null; fake: boolean }>("applicant", "payments", { method: "POST" });
      await saveDraft(7, { payment_reference: payment.reference });
      if (payment.fake) {
        const verified = await api<{ paid: boolean }>("applicant", `payments/${payment.reference}/verify`, { method: "POST" });
        setData((d) => ({ ...d, payment_reference: payment.reference }));
        setPaid(verified.paid);
        setStepError({});
        setNotice({ tone: "info", text: "Test mode: no Paystack keys are configured, so the payment was simulated." });
        setBusy(false);
      } else if (payment.authorization_url) {
        window.location.href = payment.authorization_url;
      }
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
      setBusy(false);
    }
  }

  async function submit() {
    if (busy) return;
    if (!checkStep(8)) return;
    // The declaration (step 6) and payment (step 7) must both be complete.
    for (const n of [6, 7]) {
      if (Object.keys(extraErrors(n)).length) {
        goTo(n);
        setStepError(extraErrors(n));
        return;
      }
    }
    setBusy(true);
    try {
      const { data: application } = await api<{ data: Application }>("applicant", "applications", {
        method: "POST",
        json: { ...data, type, declaration: true },
      });
      router.push(`/portal/applications/${application.id}?submitted=1`);
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        const fieldErrors = e.fieldErrors();
        setServerErrors(fieldErrors);
        const first = Math.min(...Object.keys(fieldErrors).map((f) => FIELD_STEP[f] ?? 8));
        goTo(first);
      } else {
        setNotice({ tone: "danger", text: (e as Error).message });
      }
    } finally {
      setBusy(false);
    }
  }

  if (!loaded) return <Spinner label={t("Loading…")} />;

  const props = { data, errors, set, touch, focus: setFocusedField };

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">
          {t(type === "RENEWAL" ? "Residence card renewal" : type === "REPLACE" ? "Replacement of a lost or stolen card" : "Residence card application")}
          {data.dependant_relationship && ` · for your ${data.dependant_relationship === "CHILD" ? "child" : "spouse"}`}
        </p>
        <h1 className="mt-1 text-2xl font-semibold text-slate-900">{t("Step {n} of 8 — {title}", { n: step, title: t(STEPS[step - 1]) })}</h1>
        <ol className="mt-4 grid grid-cols-8 gap-1.5" aria-label="Progress">
          {STEPS.map((label, i) => (
            <li key={label} title={t(label)} aria-current={i + 1 === step ? "step" : undefined} className={`h-2 rounded-full ${i + 1 < step ? "bg-nis-primary" : i + 1 === step ? "bg-nis-orange" : "bg-slate-200"}`} />
          ))}
        </ol>
      </div>

      {notice && <Alert tone={notice.tone}>{notice.text}</Alert>}
      {currentStepHasErrors && <Alert tone="danger">{t("Please correct the highlighted details.")}</Alert>}

      <Panel title={t(STEPS[step - 1])}>
        {/* Step 1: General Information */}
        {step === 1 && (
          <GeneralInfoFields {...props} feeNaira={fee} />
        )}

        {/* Step 2: Personal details */}
        {step === 2 && (
          <div className="space-y-6">
            <PhotoUpload
              photo={photo}
              url={photoUrl}
              localPreview={localPhotoPreview}
              uploadStatus={photoUploadStatus}
              error={errors.photo}
              busy={busy}
              onUpload={uploadPhotograph}
              onView={() => {
                if (localPhotoPreview) {
                  setPreview({ url: localPhotoPreview, name: "Passport photograph" });
                } else if (photo) {
                  view(photo);
                }
              }}
            />
            {data.dependant_relationship && (
              <Alert tone="info">
                This application is for your <strong>{data.dependant_relationship === "CHILD" ? "child" : "spouse"}</strong> and is linked to your own application.
                The surname is locked to match the primary applicant. Enter their forenames, photograph and passport.
              </Alert>
            )}
            {type !== "NEW" && (
              <Field label={type === "REPLACE" ? "Number of the lost or stolen card" : "Residence card number being renewed"} required error={errors.renewal_card_number}
                hint={type === "REPLACE" ? "The card must first be reported lost or stolen under “My cards”." : undefined}>
                <Input value={data.renewal_card_number ?? ""} inputMode="numeric" maxLength={12} onChange={(e) => set("renewal_card_number", e.target.value.replace(/\D/g, ""))} onBlur={() => touch("renewal_card_number")} />
              </Field>
            )}
            <PersonalFields {...props} lockContact />
          </div>
        )}

        {/* Step 3: Travel / Identity document */}
        {step === 3 && <PassportFields {...props} />}

        {/* Step 4: Residence & contacts */}
        {step === 4 && <ContactFields {...props} />}

        {/* Step 5: Documents */}
        {step === 5 && (
          <div className="space-y-4">
            <div className="rounded-xl border border-slate-200 bg-nis-mint/40 p-4">
              <div className="text-sm font-semibold text-nis-primary-dark">
                Required Documents for {data.category === "STUDENT" ? "Student" : data.category === "BUSINESS" ? "Business" : "Work"} Category
              </div>
              <p className="mt-1 text-xs text-slate-600">
                Official Document Verification: Each file must be <strong>smaller than 2 MB</strong> in <strong>PDF, JPG, or PNG</strong> format.
                Ensure all documents are clear, fully legible, and upright. Your passport photograph was uploaded in step 2.
              </p>
            </div>

            {errors.documents && <Alert tone="danger">{errors.documents}</Alert>}

            <div className="grid gap-3.5">
              {currentDocs.map((d) => {
                const current = documents.find((x) => x.type === d.type);
                const state = docStates[d.type];
                const isUploading = state?.status === "uploading";
                const isError = state?.status === "error";
                const isUploaded = (state?.status === "success" || Boolean(current)) && !isError && !isUploading;
                const fileName = state?.fileName || current?.original_name;
                const fileSize = state?.fileSize || current?.size_bytes;
                const isPdf = state?.isPdf ?? (current?.mime_type === "application/pdf" || current?.original_name?.toLowerCase().endsWith(".pdf"));
                const previewUrl = state?.previewUrl;

                let cardStyle = "border-slate-200 bg-white hover:border-slate-300";
                if (isUploading) cardStyle = "border-blue-300 bg-blue-50/20";
                else if (isError) cardStyle = "border-red-300 bg-red-50/20";
                else if (isUploaded) cardStyle = "border-emerald-300 bg-emerald-50/20";

                return (
                  <div
                    key={d.type}
                    className={`flex flex-col gap-4 rounded-xl border p-4 shadow-sm transition-all sm:flex-row sm:items-center sm:justify-between ${cardStyle}`}
                  >
                    {/* Left: Thumbnail / Badge & metadata */}
                    <div className="flex items-start gap-3.5">
                      {/* Document icon / preview box */}
                      <div className="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
                        {isUploaded && !isPdf && previewUrl ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={previewUrl} alt={fileName || d.label} className="h-full w-full object-cover" />
                        ) : isUploaded && isPdf ? (
                          <div className="flex flex-col items-center justify-center text-red-600">
                            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                              <polyline points="14 2 14 8 20 8" />
                              <line x1="16" y1="13" x2="8" y2="13" />
                              <line x1="16" y1="17" x2="8" y2="17" />
                              <polyline points="10 9 9 9 8 9" />
                            </svg>
                            <span className="text-[8px] font-bold tracking-tight">PDF</span>
                          </div>
                        ) : isUploaded ? (
                          <svg className="h-6 w-6 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" y1="18" x2="12" y2="12" />
                            <line x1="9" y1="15" x2="15" y2="15" />
                          </svg>
                        ) : isUploading ? (
                          <svg className="h-6 w-6 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                          </svg>
                        ) : (
                          <svg className="h-6 w-6 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="12" y1="12" x2="12" y2="18" />
                            <line x1="9" y1="15" x2="15" y2="15" />
                          </svg>
                        )}
                      </div>

                      {/* Details */}
                      <div className="space-y-1">
                        <div className="text-sm font-semibold text-slate-900">
                          {d.label} {d.required ? <span className="text-red-600">*</span> : <span className="text-xs font-normal text-slate-500">(Optional)</span>}
                        </div>

                        {/* Status lines */}
                        {isUploading && (
                          <div className="flex items-center gap-1.5 text-xs font-medium text-blue-700">
                            <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span>Uploading document…</span>
                            {fileName && <span className="truncate max-w-[200px] text-slate-500">({fileName})</span>}
                          </div>
                        )}

                        {isUploaded && (
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                              <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                              </svg>
                              Document uploaded successfully
                            </span>
                            <span className="truncate max-w-[240px] text-xs font-medium text-slate-700">{fileName}</span>
                            {fileSize && <span className="text-xs text-slate-400">({kb(fileSize)})</span>}
                          </div>
                        )}

                        {isError && (
                          <div className="space-y-0.5">
                            <div className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                              <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                              </svg>
                              Upload Failed
                            </div>
                            <p className="text-xs text-red-700">{state?.error || "Please select a valid document."}</p>
                          </div>
                        )}

                        {!isUploading && !isUploaded && !isError && (
                          <p className="text-xs text-slate-500">PDF, JPG or PNG up to 2 MB</p>
                        )}
                      </div>
                    </div>

                    {/* Right: Actions */}
                    <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                      {isUploaded && (
                        <>
                          <Button
                            variant="secondary"
                            type="button"
                            onClick={() => viewDoc(d)}
                            className="!text-xs !py-1.5 !px-3"
                          >
                            View
                          </Button>
                          <UploadButton
                            label="Replace"
                            accept={d.accept}
                            disabled={busy || isUploading}
                            onFile={(f) => uploadDocFile(d, f)}
                            className="!text-xs !py-1.5 !px-3"
                          />
                          <Button
                            variant="ghost"
                            type="button"
                            onClick={() => removeDoc(d)}
                            disabled={busy || isUploading}
                            className="!text-xs !py-1.5 !px-3 !text-red-700 hover:!bg-red-50"
                          >
                            Remove
                          </Button>
                        </>
                      )}

                      {isError && (
                        <>
                          {state?.pendingFile && (
                            <Button
                              variant="primary"
                              type="button"
                              onClick={() => uploadDocFile(d, state.pendingFile!)}
                              disabled={busy || isUploading}
                              className="!text-xs !py-1.5 !px-3"
                            >
                              Retry
                            </Button>
                          )}
                          <UploadButton
                            label="Choose file"
                            accept={d.accept}
                            disabled={busy || isUploading}
                            onFile={(f) => uploadDocFile(d, f)}
                            className="!text-xs !py-1.5 !px-3"
                          />
                        </>
                      )}

                      {!isUploaded && !isError && (
                        <UploadButton
                          label={isUploading ? "Uploading…" : "Upload document"}
                          accept={d.accept}
                          disabled={busy || isUploading}
                          onFile={(f) => uploadDocFile(d, f)}
                          className="!text-xs !py-1.5 !px-3 !bg-nis-primary !text-white hover:!bg-nis-primary-dark"
                        />
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Step 6: Review & declaration */}
        {step === 6 && (
          <div className="space-y-6">
            <p className="text-sm text-slate-600">Check every detail carefully before you pay. Use <strong>Edit</strong> to change a section.</p>
            <ReviewSection title="General Information" onEdit={() => goTo(1)}>
              <ReviewGrid
                items={[
                  ["Nationality", data.nationality],
                  ["Category / Class", data.category === "STUDENT" ? "Student" : data.category === "BUSINESS" ? "Business" : "Work (Employment)"],
                  ["Document Type", data.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate" : "Passport"],
                  ["Validity Period", "2 Years from date of production"],
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Personal details" onEdit={() => goTo(2)}>
              <div className="flex flex-col gap-5 sm:flex-row">
                {photoUrl && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photoUrl} alt="Passport photograph" className="h-40 w-32 shrink-0 rounded-lg border object-cover" />
                )}
                <ReviewGrid
                  items={[
                    ...(type !== "NEW" ? [[type === "REPLACE" ? "Card being replaced" : "Card being renewed", data.renewal_card_number] as [string, string | undefined]] : []),
                    ...(data.dependant_relationship ? [["Applying for", `Your ${data.dependant_relationship === "CHILD" ? "child" : "spouse"}`] as [string, string | undefined]] : []),
                    ["Surname", data.surname], ["Other names", data.forenames], ["Nationality", data.nationality], ["Sex", data.sex],
                    ["Date of birth", nisDate(data.date_of_birth)], ["Place of birth", data.place_of_birth], ["Profession", data.profession],
                    ["Phone number", data.phone], ["E-mail address", data.email],
                    ["Blood group", data.blood_group || "UNKNOWN"], ["Height", data.height], ["Complexion", data.complexion],
                    ["Colour of eyes", data.eye_color], ["Colour of hair", data.hair_color], ["Distinguishing features", data.distinguished_features || "NONE"],
                  ]}
                />
              </div>
            </ReviewSection>

            <ReviewSection title="Travel / Identity document" onEdit={() => goTo(3)}>
              <ReviewGrid
                items={[
                  ["Document type", data.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate" : "International Passport"],
                  [data.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate number" : "Passport number", data.passport_number],
                  ["Issue Date", nisDate(data.passport_issue_date)], ["Expiring Date", nisDate(data.passport_expiry)], ["NIN", data.national_id_number], ["Tax ID", data.tax_id_number]
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Residence & contacts" onEdit={() => goTo(4)}>
              <ReviewGrid
                items={[
                  ["Address in Home Country", data.home_country_address],
                  ["Address in Nigeria", data.domicile], ["Local government area", data.domicile_lga], ["State", data.domicile_state],
                  ["Change of address", data.change_of_address],
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Emergency contact" onEdit={() => goTo(4)}>
              <ReviewGrid
                items={[
                  ["Full name", data.emergency_contact_name], ["Relationship", data.emergency_contact_relation], ["Phone number", data.emergency_contact_phone],
                  ["Address", data.emergency_contact_address], ["Local government area", data.emergency_contact_lga], ["State", data.emergency_contact_state],
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Documents" onEdit={() => goTo(5)}>
              <ul className="divide-y divide-slate-100 text-sm">
                {[PHOTO, ...currentDocs].map((d) => {
                  const current = documents.find((x) => x.type === d.type);
                  if (!current && !d.required) return null;
                  return (
                    <li key={d.type} className="flex items-center justify-between gap-3 py-2">
                      <span>{d.label}: <span className={current ? "text-slate-700" : "text-red-700"}>{current ? current.original_name : "missing"}</span></span>
                      {current && <button type="button" onClick={() => view(current)} className="text-sm font-medium text-nis-primary hover:underline">View</button>}
                    </li>
                  );
                })}
              </ul>
            </ReviewSection>

            <ReviewSection title="Residence card fee" onEdit={() => goTo(7)}>
              <ReviewGrid
                items={[
                  ["Amount", fee !== null ? naira(fee) : "—"],
                  ["Payment reference", data.payment_reference ?? "Created when you pay (next step)"],
                  ["Payment status", paid ? "PAID — confirmed by Paystack" : "Not paid yet: you pay in the next step"],
                ]}
              />
            </ReviewSection>

            <label className={`flex items-start gap-3 rounded-lg border p-4 text-sm ${errors.declaration ? "border-red-400 bg-red-50" : "border-slate-200 bg-nis-mint"}`}>
              <input type="checkbox" className="mt-1" checked={data.declaration === "1"} onChange={(e) => { set("declaration", e.target.checked ? "1" : ""); setStepError({}); }} />
              <span>
                I solemnly declare that the particulars given in this application are true and correct. I understand that giving false
                information is an offence under the Immigration Act 2015.
              </span>
            </label>
            {errors.declaration && <p className="text-sm text-red-700">{errors.declaration}</p>}
          </div>
        )}

        {/* Step 7: Fee payment */}
        {step === 7 && (
          <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <Summary label="Residence card fee" value={fee !== null ? naira(fee) : "…"} />
              <Summary label="Payment reference" value={data.payment_reference ?? "Created when you pay"} />
            </div>
            {paid ? (
              <Alert tone="success">Payment confirmed. Click “Save &amp; continue” to book your biometrics appointment.</Alert>
            ) : (
              <>
                {errors.payment_reference && <Alert tone="danger">{errors.payment_reference}</Alert>}
                <Button onClick={pay} disabled={busy} className="!bg-nis-orange hover:!brightness-95">{busy ? "Opening Paystack…" : t("Pay securely with Paystack")}</Button>
                <p className="text-xs text-slate-500">You pay on Paystack&apos;s secure page by card, bank transfer or USSD, then return here automatically. You cannot continue until the payment is confirmed.</p>
              </>
            )}
          </div>
        )}

        {/* Step 8: Biometrics appointment */}
        {step === 8 && (
          <div className="space-y-6">
            <AppointmentPicker {...props} />
            <div className="grid gap-4 sm:grid-cols-3">
              <Summary label="Declaration" value={data.declaration === "1" ? "Accepted" : "Not accepted"} />
              <Summary label="Residence card fee" value={fee !== null ? naira(fee) : "…"} />
              <Summary label="Payment reference" value={paid ? data.payment_reference ?? "—" : "Not paid"} />
            </div>
            <p className="text-sm text-slate-600">Choose your appointment, then click <strong>Submit application</strong>.</p>
          </div>
        )}
      </Panel>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex gap-2">
          {step > 1 && <Button variant="secondary" onClick={() => goTo(step - 1)} disabled={busy}>{t("Back")}</Button>}
          <Button variant="ghost" onClick={saveAndExit} disabled={busy}>{t("Save & exit")}</Button>
          <Button variant="ghost" onClick={discard} disabled={busy} className="text-red-700">{t("Discard")}</Button>
        </div>
        {step < 8 ? (
          <Button onClick={next} disabled={busy || (step === 7 && !paid)}>{busy ? t("Saving…") : t("Save & continue")}</Button>
        ) : (
          <Button onClick={submit} disabled={busy}>{busy ? t("Submitting…") : t("Submit application")}</Button>
        )}
      </div>
      <p className="text-xs text-slate-500">Need help? <Link href="/track" className="underline">Track an existing application</Link>.</p>

      {preview && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-label={preview.name} onClick={() => setPreview(null)}>
          <div className="max-h-full max-w-3xl overflow-auto rounded-xl bg-white p-3 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-2 flex items-center justify-between gap-4">
              <span className="truncate text-sm font-medium">{preview.name}</span>
              <Button variant="secondary" onClick={() => setPreview(null)}>Close</Button>
            </div>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={preview.url} alt={preview.name} className="max-h-[75vh] w-auto rounded" />
          </div>
        </div>
      )}
    </div>
  );
}

function Summary({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-nis-mint px-4 py-3">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-base font-medium text-nis-primary-dark">{value}</div>
    </div>
  );
}

function ReviewGrid({ items }: { items: [string, string | undefined][] }) {
  return (
    <dl className="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
      {items.map(([k, v]) => (
        <div key={k}>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{k}</dt>
          <dd className="mt-0.5 break-words text-sm text-slate-900">{v || "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

function ReviewSection({ title, onEdit, children }: { title: string; onEdit: () => void; children: ReactNode }) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200">
      <header className="flex items-center justify-between bg-nis-mint px-4 py-2.5">
        <h3 className="text-sm font-semibold text-nis-primary-dark">{title}</h3>
        <button type="button" onClick={onEdit} className="text-sm font-medium text-nis-primary hover:underline">Edit</button>
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}

function UploadButton({ label, accept, disabled, className, onFile }: { label: string; accept: string; disabled?: boolean; className?: string; onFile: (file: File) => void }) {
  return (
    <label className={`inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 ${disabled ? "pointer-events-none opacity-50" : ""} ${className ?? ""}`}>
      {label}
      <input
        type="file"
        accept={accept}
        className="sr-only"
        disabled={disabled}
        onChange={(e) => {
          const file = e.target.files?.[0];
          e.target.value = "";
          if (file) onFile(file);
        }}
      />
    </label>
  );
}

function PhotoUpload({
  photo,
  url,
  localPreview,
  uploadStatus,
  error,
  busy,
  onUpload,
  onView,
}: {
  photo?: DraftDoc;
  url: string | null;
  localPreview?: string | null;
  uploadStatus: "idle" | "uploading" | "success" | "error";
  error?: string;
  busy: boolean;
  onUpload: (f: File) => void;
  onView: () => void;
}) {
  const displayUrl = localPreview || url;

  return (
    <div className={`flex flex-col gap-5 rounded-xl border p-4 sm:flex-row sm:items-center ${error ? "border-red-400 bg-red-50" : "border-slate-200 bg-nis-mint"}`}>
      <div className="relative flex h-40 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-300 bg-white shadow-inner">
        {displayUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={displayUrl} alt="Your passport photograph" className="h-full w-full object-cover" />
        ) : (
          <svg className="h-14 w-14 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden>
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" />
          </svg>
        )}
        {uploadStatus === "uploading" && (
          <div className="absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white backdrop-blur-[1px]">
            <svg className="h-6 w-6 animate-spin text-white" viewBox="0 0 24 24" fill="none">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <span className="mt-1.5 text-[11px] font-medium tracking-wide">Uploading…</span>
          </div>
        )}
      </div>
      <div className="space-y-2">
        <div className="font-semibold text-slate-900">
          Passport photograph <span className="text-red-600">*</span>
        </div>
        <p className="text-sm text-slate-600">A recent colour photograph of your face on a white background. JPG or PNG, smaller than 2 MB.</p>

        {/* Status badges */}
        {uploadStatus === "uploading" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">
            <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            Uploading photograph…
          </div>
        )}
        {uploadStatus !== "uploading" && (uploadStatus === "success" || (photo && !error)) && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
            </svg>
            Upload Successful {photo ? `(${photo.original_name})` : ""}
          </div>
        )}
        {uploadStatus === "error" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            Upload Failed — please select a valid photo
          </div>
        )}
        {displayUrl && uploadStatus === "idle" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
            Photograph Selected
          </div>
        )}

        {error && <p className="text-sm font-medium text-red-700">{error}</p>}
        <div className="flex flex-wrap gap-2 pt-1">
          <UploadButton
            label={displayUrl ? "Change photograph" : "Upload photograph"}
            accept={PHOTO.accept}
            disabled={busy || uploadStatus === "uploading"}
            onFile={onUpload}
          />
          {displayUrl && (
            <Button variant="secondary" onClick={onView} disabled={uploadStatus === "uploading"}>
              View
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

export default function ApplyPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <Wizard />
    </Suspense>
  );
}
