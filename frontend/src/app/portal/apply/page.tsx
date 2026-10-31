"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { ContactFields, GeneralInfoFields, PersonalFields } from "@/components/ParticularsFields";
import { Alert, Button, Field, Input, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application, CodChangeItem, EnrollmentCenter } from "@/lib/types";
import { APPOINTMENT_FIELDS, CONTACT_FIELDS, fieldError, formatRelationship, GENERAL_FIELDS, PASSPORT_FIELDS, PERSONAL_FIELDS, validate } from "@/lib/validation";

const COD_AVAILABLE_OPTIONS: { key: string; label: string; docHint?: string }[] = [
  { key: "name", label: "Change of Name", docHint: "e.g. Marriage Certificate, Newspaper Publication, Deed Poll, Court Affidavit" },
  { key: "date_of_birth", label: "Change of Date of Birth", docHint: "e.g. National Population Commission (NPC) Birth Certificate, Age Declaration" },
];


/** 7-Step Application Process */
const STEPS = [
  "General Information",
  "Personal details",
  "Residence & contacts",
  "Documents",
  "Review & declaration",
  "Fee payment",
  "Biometrics appointment",
];

const MAX_BYTES = 2 * 1024 * 1024;

type DocSpec = { type: string; label: string; required: boolean; accept: string; kinds: string };

const PHOTO: DocSpec = {
  type: "photo",
  label: "Passport photograph (white background)",
  required: true,
  accept: ".jpg,.jpeg,.png,image/jpeg,image/png",
  kinds: "JPG or PNG",
};

/** Category-dependent document requirements */
function getDocuments(category?: string, docs?: DraftDoc[]): DocSpec[] {
  let cat = (category || "").toUpperCase();
  if (!["WORK", "BUSINESS", "STUDENT", "RELIGIOUS"].includes(cat)) {
    if (docs?.some((d) => d.type === "admission_letter" || d.type === "acceptance_letter" || d.type === "proof_of_payment")) cat = "STUDENT";
    else if (docs?.some((d) => d.type === "cac_certificate")) cat = "BUSINESS";
    else if (docs?.some((d) => d.type === "religious_invitation" || d.type === "religious_registration")) cat = "RELIGIOUS";
    else cat = "WORK";
  }

  const catDocs: DocSpec[] = [];

  if (cat === "STUDENT") {
    catDocs.push(
      {
        type: "admission_letter",
        label: "Institution Admission Letter",
        required: true,
        accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
        kinds: "JPG, JPEG, PNG or PDF",
      },
      {
        type: "acceptance_letter",
        label: "Acceptance Letter",
        required: true,
        accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
        kinds: "JPG, JPEG, PNG or PDF",
      },
      {
        type: "proof_of_payment",
        label: "Proof of Payment",
        required: true,
        accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
        kinds: "JPG, JPEG, PNG or PDF",
      }
    );
  } else if (cat === "BUSINESS") {
    catDocs.push({
      type: "cac_certificate",
      label: "CAC Registration Document (Optional)",
      required: false,
      accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
      kinds: "JPG, JPEG, PNG or PDF",
    });
  } else if (cat === "WORK") {
    catDocs.push({
      type: "appointment_letter",
      label: "Appointment Letter / Contract Letter",
      required: false,
      accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
      kinds: "JPG, JPEG, PNG or PDF",
    });
  } else if (cat === "RELIGIOUS") {
    catDocs.push(
      {
        type: "religious_invitation",
        label: "Appointment Letter / Contract Letter",
        required: true,
        accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
        kinds: "JPG, JPEG, PNG or PDF",
      },
      {
        type: "religious_registration",
        label: "Evidence of Host Organization Registration or Recognition",
        required: false,
        accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
        kinds: "JPG, JPEG, PNG or PDF",
      }
    );
  }

  return [
    {
      type: "passport_copy",
      label: "International Passport data page OR ECOWAS Travel Certificate",
      required: true,
      accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
      kinds: "JPG, JPEG, PNG or PDF",
    },
    {
      type: "domicile_proof",
      label: "Proof of Residence (Utility Bill / Tenancy Agreement)",
      required: true,
      accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
      kinds: "JPG, JPEG, PNG or PDF",
    },
    ...catDocs,
    {
      type: "additional",
      label: "Additional supporting document",
      required: false,
      accept: ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png",
      kinds: "JPG, JPEG, PNG or PDF",
    },
  ];
}

/** Which step each field lives on (to jump back when the API rejects one). */
const FIELD_STEP: Record<string, number> = {
  type: 1, nationality: 1, category: 1, travel_document_type: 1,
  passport_number: 1, passport_issue_date: 1, passport_expiry: 1, national_id_number: 1, tax_id_number: 1,
  cod: 1,
  ...Object.fromEntries(PERSONAL_FIELDS.filter((f) => f !== "nationality").map((f) => [f, 2])),
  renewal_card_number: 2, photo: 2, principal_application_id: 2, dependant_relationship: 2, phone: 2, email: 2,
  ...Object.fromEntries(CONTACT_FIELDS.map((f) => [f, 3])),
  home_country_address: 3,
  documents: 4,
  declaration: 5,
  payment_reference: 6,
  ...Object.fromEntries(APPOINTMENT_FIELDS.map((f) => [f, 7])),
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

/** Short-lived link to an uploaded draft or queried application document. */
async function documentUrl(id: number, applicationId?: number): Promise<string> {
  const endpoint = applicationId
    ? `applications/${applicationId}/documents/${id}`
    : `draft/documents/${id}`;
  const { url } = await api<{ url: string }>("applicant", endpoint);
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
    category: "",
    travel_document_type: "",
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
  const [paymentAcknowledged, setPaymentAcknowledged] = useState(false);
  const [ackError, setAckError] = useState<string | null>(null);
  const [photoLink, setPhotoLink] = useState<{ id: number; url: string } | null>(null);
  const [localPhotoPreview, setLocalPhotoPreview] = useState<string | null>(null);
  const [photoUploadStatus, setPhotoUploadStatus] = useState<"idle" | "uploading" | "success" | "error">("idle");
  const [preview, setPreview] = useState<{ url: string; name: string } | null>(null);

  const [queriedApp, setQueriedApp] = useState<Application | null>(null);
  const [queryResponseText, setQueryResponseText] = useState("");
  const [submittingResponse, setSubmittingResponse] = useState(false);
  const [queryResponseSuccess, setQueryResponseSuccess] = useState<string | null>(null);
  const [queryResponseError, setQueryResponseError] = useState<string | null>(null);

  const [hasCod, setHasCod] = useState(false);
  const [codChanges, setCodChanges] = useState<CodChangeItem[]>([]);
  const [originalParticulars, setOriginalParticulars] = useState<Record<string, string>>({});
  const [renewalSource, setRenewalSource] = useState<{ id: number; application_number: string; card_number?: string } | null>(null);
  const [codFieldToAdd, setCodFieldToAdd] = useState("");
  const [codUploadBusy, setCodUploadBusy] = useState<number | null>(null);

  const initialLoadedRef = useRef(false);
  const isUploadingPhotoRef = useRef(false);
  const uploadingDocsRef = useRef<Set<string>>(new Set());

  const set = useCallback((key: string, value: string) => {
    setData((d) => ({ ...d, [key]: value }));
    setServerErrors((e) => (e[key] ? { ...e, [key]: "" } : e));
    setStepError((e) => (e[key] ? { ...e, [key]: "" } : e));
  }, []);
  const touch = useCallback((key: string) => setTouched((t) => (t.has(key) ? t : new Set(t).add(key))), []);

  const photo = documents.find((d) => d.type === "photo");
  const photoUrl = photo && photoLink?.id === photo.id ? photoLink.url : null;

  const resolvedCategory = useMemo(() => {
    const raw = (data.category || "").toUpperCase();
    if (["WORK", "BUSINESS", "STUDENT", "RELIGIOUS"].includes(raw)) return raw;
    if (documents.some((x) => x.type === "admission_letter")) return "STUDENT";
    if (documents.some((x) => x.type === "cac_certificate")) return "BUSINESS";
    if (documents.some((x) => x.type === "religious_invitation" || x.type === "religious_registration")) return "RELIGIOUS";
    return "WORK";
  }, [data.category, documents]);

  const currentDocs = useMemo(() => getDocuments(resolvedCategory, documents), [resolvedCategory, documents]);

  /** Fields validated on each step. */
  const stepFields = useCallback(
    (n: number) => {
      const isDep = Boolean(data.principal_application_id || data.dependant_relationship);
      return n === 1
        ? GENERAL_FIELDS
        : n === 2
        ? [
            ...(type !== "NEW" ? ["renewal_card_number"] : []),
            ...PERSONAL_FIELDS.filter((f) => f !== "nationality" && (isDep ? true : (f !== "phone" && f !== "email"))),
          ]
        : n === 3
        ? CONTACT_FIELDS
        : n === 7
        ? APPOINTMENT_FIELDS
        : [];
    },
    [type, data.principal_application_id, data.dependant_relationship],
  );

  /** Errors that are not about a single form field (photo, documents, payment, declaration). */
  const extraErrors = useCallback(
    (n: number): Record<string, string> => {
      const e: Record<string, string> = {};
      if (n === 2 && !photo && !localPhotoPreview) e.photo = "Upload your passport photograph.";
      if (n === 4) {
        const requiredDocs = getDocuments(resolvedCategory, documents);
        const absent = requiredDocs.filter((d) => d.required && !documents.some((x) => x.type === d.type));
        if (absent.length === 1) {
          e.documents = `Please upload ${absent[0].label}.`;
        } else if (absent.length > 1) {
          const names = absent.map((d) => d.label);
          e.documents = `Please upload: ${names.join(", ")}.`;
        }
      }
      if (n === 5 && hasCod) {
        if (codChanges.length === 0) {
          e.cod = "Please select at least one field to change and specify the correction, or cancel Change of Data.";
        } else {
          for (const c of codChanges) {
            if (!c.requested_value || !c.requested_value.trim()) {
              e.cod = `Please enter the requested new value for ${c.field_label || c.field}.`;
              break;
            }
            if (!c.reason || !c.reason.trim()) {
              e.cod = `Please enter the reason for change for ${c.field_label || c.field}.`;
              break;
            }
            if (!c.document_name || !c.document_name.trim()) {
              e.cod = `Please upload a supporting document for ${c.field_label || c.field}.`;
              break;
            }
          }
        }
      }
      if (type === "RENEWAL" && (n === 2 || n === 5) && (originalParticulars.surname || originalParticulars.forenames || originalParticulars.date_of_birth)) {
        const origSurname = (originalParticulars.surname || "").trim();
        const origForenames = (originalParticulars.forenames || "").trim();
        const origDob = (originalParticulars.date_of_birth || "").trim();
        const curSurname = (data.surname || "").trim();
        const curForenames = (data.forenames || "").trim();
        const curDob = (data.date_of_birth || "").trim();

        const nameDiff = (origSurname && curSurname && origSurname.toLowerCase() !== curSurname.toLowerCase()) ||
                         (origForenames && curForenames && origForenames.toLowerCase() !== curForenames.toLowerCase());
        const dobDiff = (origDob && curDob && origDob !== curDob);

        if ((nameDiff || dobDiff) && !hasCod) {
          const diffs: string[] = [];
          if (nameDiff) diffs.push("Name");
          if (dobDiff) diffs.push("Date of Birth");
          e.cod = `Modifications to ${diffs.join(" and ")} require Change of Data (COD). Please click "Change of Data", specify your reason, and attach supporting documentation.`;
        }
      }
      if (n === 5 && data.declaration !== "1") e.declaration = "You must accept the declaration before you pay.";
      if (n === 6 && !paid) e.payment_reference = "Pay the residence card fee before you continue.";
      return e;
    },
    [photo, localPhotoPreview, documents, paid, data.declaration, data.surname, data.forenames, data.date_of_birth, resolvedCategory, hasCod, codChanges, type, originalParticulars],
  );

  // Errors shown: fields are validated on blur or step submit; in-progress typing is not prematurely flagged red.
  const errors = useMemo(() => {
    const shown: Record<string, string> = {};
    for (const field of touched) {
      // Suppress premature errors while user is actively focused on field
      if (focusedField === field) {
        continue;
      }
      const message = fieldError(field, data);
      if (message) shown[field] = message;
    }
    for (const [field, message] of Object.entries(serverErrors)) {
      if (message && focusedField !== field) shown[field] = message;
    }
    for (const [field, message] of Object.entries(stepError)) {
      if (message && (!data[field] || ["photo", "documents", "declaration", "payment_reference", "cod"].includes(field))) {
        if (focusedField !== field || !data[field]) {
          shown[field] = message;
        }
      }
    }
    return shown;
  }, [touched, data, serverErrors, stepError, focusedField]);

  const currentStepHasErrors = [
    ...stepFields(step),
    "photo",
    "documents",
    "payment_reference",
    "declaration",
    "cod",
  ].some((f) => errors[f] && FIELD_STEP[f] === step);

  const serializeCodChanges = useCallback((): CodChangeItem[] => {
    if (!hasCod || !Array.isArray(codChanges)) return [];
    return codChanges.map((c) => {
      const isName = c.field === "name" || c.field === "surname" || c.field === "forenames";
      return {
        ...c,
        field: isName ? "name" : c.field,
        field_label: c.field_label || (isName ? "Change of Name" : "Change of Date of Birth"),
        original_value: c.original_value || (isName ? `${originalParticulars.surname || ""} ${originalParticulars.forenames || ""}`.trim() : (originalParticulars.date_of_birth || "")),
        requested_value: c.requested_value || (isName ? `${c.requested_surname || data.surname || ""} ${c.requested_forenames || data.forenames || ""}`.trim() : (data.date_of_birth || "")),
        requested_surname: isName ? (c.requested_surname || data.surname || "") : undefined,
        requested_forenames: isName ? (c.requested_forenames || data.forenames || "") : undefined,
      };
    });
  }, [hasCod, codChanges, data, originalParticulars]);

  const saveDraft = useCallback(
    async (nextStep: number, patch: Record<string, string> = {}) => {
      if (queriedApp) {
        try {
          const res = await api<{ data: Application }>("applicant", `applications/${queriedApp.id}`, {
            method: "PUT",
            json: { ...data, ...patch },
          });
          setQueriedApp(res.data);
        } catch (_) {}
        return null;
      }
      const activeCat = patch.category || resolvedCategory;
      const body = {
        type,
        current_step: nextStep,
        data: {
          ...data,
          category: activeCat,
          type,
          has_cod: hasCod ? "1" : "0",
          cod_changes: JSON.stringify(serializeCodChanges()),
          renewal_source_application_id: renewalSource?.id ? String(renewalSource.id) : (data.renewal_source_application_id || ""),
          ...patch,
        },
      };
      const result = await api<{ draft: Draft }>("applicant", "draft", { method: "PUT", json: body });
      setDocuments(result.draft.documents);
      return result.draft;
    },
    [type, data, resolvedCategory, queriedApp, hasCod, serializeCodChanges, renewalSource],
  );

  async function uploadCodDocument(index: number, file: File) {
    if (file.size > MAX_BYTES) {
      alert(`File size is ${kb(file.size)}. Please upload a file smaller than 2 MB.`);
      return;
    }
    setCodUploadBusy(index);
    try {
      const result = await upload<{ draft: Draft; uploaded_document?: { id: number; url: string } }>(
        "applicant",
        "draft/documents",
        { type: "additional", file }
      );
      setDocuments(result.draft.documents);
      const uploadedDoc = result.draft.documents.find((d) => d.original_name === file.name)
        || result.draft.documents[result.draft.documents.length - 1];
      setCodChanges((prev) => {
        const next = [...prev];
        next[index] = {
          ...next[index],
          document_name: file.name,
          document_id: uploadedDoc?.id,
        };
        return next;
      });
      setNotice({ tone: "success", text: `Uploaded supporting document for ${codChanges[index]?.field_label || "Change of Data"} successfully.` });
    } catch (err) {
      alert((err as Error).message || "Failed to upload document.");
    } finally {
      setCodUploadBusy(null);
    }
  }

  function addCodField() {
    if (!codFieldToAdd) return;
    const spec = COD_AVAILABLE_OPTIONS.find((f) => f.key === codFieldToAdd);
    if (!spec) return;
    if (codFieldToAdd === "name") {
      const orig = `${originalParticulars.surname || data.surname || ""} ${originalParticulars.forenames || data.forenames || ""}`.trim();
      setCodChanges((prev) => [
        ...prev.filter((c) => c.field !== "name" && c.field !== "surname" && c.field !== "forenames"),
        {
          field: "name",
          field_label: "Change of Name",
          original_value: orig,
          requested_value: `${data.surname || ""} ${data.forenames || ""}`.trim(),
          requested_surname: data.surname || "",
          requested_forenames: data.forenames || "",
          reason: "",
          document_name: "",
        },
      ]);
    } else if (codFieldToAdd === "date_of_birth") {
      const orig = originalParticulars.date_of_birth || data.date_of_birth || "";
      setCodChanges((prev) => [
        ...prev.filter((c) => c.field !== "date_of_birth"),
        {
          field: "date_of_birth",
          field_label: "Change of Date of Birth",
          original_value: orig,
          requested_value: data.date_of_birth || "",
          reason: "",
          document_name: "",
        },
      ]);
    }
    setCodFieldToAdd("");
  }

  // Load draft (or pre-fill from the profile) and handle the Paystack return.
  useEffect(() => {
    if (initialLoadedRef.current) return;
    initialLoadedRef.current = true;

    (async () => {
      try {
        const queryAppId = params.get("query_app") || params.get("query_application");
        if (queryAppId) {
          const [appRes, centerList] = await Promise.all([
            api<{ data: Application }>("applicant", `applications/${queryAppId}`),
            api<{ data: EnrollmentCenter[]; fee_naira: number }>("public", "enrollment-centers"),
          ]);
          const app = appRes.data;
          setQueriedApp(app);
          setType(app.type || "NEW");
          setFee(centerList.fee_naira);

          const restored: Record<string, string> = {
            category: app.category ? app.category.toUpperCase() : "",
            travel_document_type: (app as any).travel_document_type || "",
            surname: app.surname || "",
            forenames: app.forenames || "",
            nationality: app.nationality || "",
            date_of_birth: app.date_of_birth || "",
            place_of_birth: app.place_of_birth || "",
            sex: app.sex || "",
            height: app.height || "",
            complexion: app.complexion || "",
            eye_color: app.eye_color || "",
            hair_color: app.hair_color || "",
            distinguished_features: app.distinguished_features || "",
            blood_group: app.blood_group || "",
            profession: app.profession || "",
            home_country: (app as any).home_country || "",
            home_country_state: (app as any).home_country_state || "",
            home_country_city: (app as any).home_country_city || "",
            home_country_district: (app as any).home_country_district || "",
            home_country_address: (app as any).home_country_address || "",
            home_country_postal_code: (app as any).home_country_postal_code || "",
            domicile: app.domicile || "",
            domicile_state: app.domicile_state || "",
            domicile_lga: app.domicile_lga || "",
            change_of_address: app.change_of_address || "",
            passport_number: app.passport_number || "",
            passport_issue_date: app.passport_issue_date || "",
            passport_expiry: app.passport_expiry || "",
            national_id_number: app.national_id_number || "",
            tax_id_number: app.tax_id_number || "",
            emergency_contact_name: app.emergency_contact_name || "",
            emergency_contact_relation: app.emergency_contact_relation || "",
            emergency_contact_phone: app.emergency_contact_phone || "",
            emergency_contact_address: app.emergency_contact_address || "",
            emergency_contact_state: app.emergency_contact_state || "",
            emergency_contact_lga: app.emergency_contact_lga || "",
            home_country_emergency_contact_name: (app as any).home_country_emergency_contact_name || "",
            home_country_emergency_contact_relation: (app as any).home_country_emergency_contact_relation || "",
            home_country_emergency_contact_phone: (app as any).home_country_emergency_contact_phone || "",
            home_country_emergency_contact_country: (app as any).home_country_emergency_contact_country || "",
            home_country_emergency_contact_state: (app as any).home_country_emergency_contact_state || "",
            home_country_emergency_contact_city: (app as any).home_country_emergency_contact_city || "",
            home_country_emergency_contact_district: (app as any).home_country_emergency_contact_district || "",
            home_country_emergency_contact_address: (app as any).home_country_emergency_contact_address || "",
            home_country_emergency_contact_postal_code: (app as any).home_country_emergency_contact_postal_code || "",
            phone: app.phone || "",
            email: app.email || "",
          };
          setData(restored);

          const appDocs: DraftDoc[] = (app.documents || []).map((d) => ({
            id: d.id,
            type: d.type,
            original_name: (d as any).file_name || d.label || d.type,
            size_bytes: (d as any).file_size_bytes || 0,
            mime_type: (d as any).mime_type || "application/pdf",
          }));
          setDocuments(appDocs);

          const initialStates: Record<string, DocUploadState> = {};
          for (const d of (app.documents || [])) {
            const fileName = (d as any).file_name || d.label || d.type;
            const isPdf = (d as any).mime_type === "application/pdf" || fileName.toLowerCase().endsWith(".pdf");
            initialStates[d.type] = {
              status: "success",
              fileName,
              fileSize: (d as any).file_size_bytes || 0,
              isPdf,
            };
          }
          setDocStates(initialStates);

          const targetStep = parseInt(params.get("step") || "4", 10);
          setStep(isNaN(targetStep) ? 4 : Math.min(Math.max(1, targetStep), 4));
          setLoaded(true);
          return;
        }

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
          category: "",
          travel_document_type: "",
          surname: me.surname,
          forenames: isDependantFlow ? "" : me.forenames,
        };

        const activeType = ((params.get("type") ?? type) || "NEW").toUpperCase() as AppType;
        if (["NEW", "RENEWAL", "REPLACE"].includes(activeType)) {
          setType(activeType);
        }

        // Automatically fetch previous particulars for Renewal applications
        if (activeType === "RENEWAL") {
          const cardParam = params.get("card") || params.get("card_number") || "";
          const queryParams = new URLSearchParams();
          if (isDependantFlow) {
            if (params.get("principal")) queryParams.set("principal", params.get("principal") || "");
            if (relationship) queryParams.set("for", relationship);
          }
          if (cardParam) {
            queryParams.set("card", cardParam);
          }
          const depQuery = queryParams.toString() ? `?${queryParams.toString()}` : "";
          const renewalRes = await api<{
            found: boolean;
            message?: string;
            source_application_id?: number;
            source_application_number?: string;
            renewal_card_number?: string;
            particulars?: Record<string, string>;
          }>("applicant", `renewal-source${depQuery}`).catch(() => null);

          if (renewalRes?.found && renewalRes.particulars) {
            restored = { ...restored, ...renewalRes.particulars };
            setOriginalParticulars(renewalRes.particulars);
            if (renewalRes.source_application_id) {
              restored.renewal_source_application_id = String(renewalRes.source_application_id);
              setRenewalSource({
                id: renewalRes.source_application_id,
                application_number: renewalRes.source_application_number || "",
                card_number: renewalRes.renewal_card_number,
              });
            }
            if (renewalRes.renewal_card_number && !restored.renewal_card_number) {
              restored.renewal_card_number = renewalRes.renewal_card_number;
            }
          }
        }

        if (isDependantFlow) {
          restored.principal_application_id = params.get("principal") as string;
          restored.dependant_relationship = relationship;
          if (draft && (draft.type === activeType || params.get("resume") === "1")) {
            restored = { ...restored, ...draft.data };
            setDocuments(draft.documents);
            if (params.get("step") || params.get("resume") === "1") {
              const resumedStep = Math.min(Math.max(1, draft.current_step || 1), 7);
              setStep(resumedStep);
            }
          }
          if (!restored.phone) restored.phone = me.phone;
          if (!restored.email) restored.email = me.email;
        } else {
          const hasMatchingDraft = Boolean(draft && (draft.type === activeType || (!params.get("type") && draft.type)));
          const shouldResumeDraft = params.get("resume") === "1" || params.get("payment") === "callback" || hasMatchingDraft;
          if (!shouldResumeDraft && params.get("card")) restored.renewal_card_number = (params.get("card") ?? "").replace(/\D/g, "");
          if (draft && shouldResumeDraft) {
            if (activeType === "RENEWAL") {
              const mergedDraft: Record<string, string> = {};
              for (const [k, v] of Object.entries(draft.data || {})) {
                if (v !== undefined && v !== null && v !== "") {
                  mergedDraft[k] = v;
                }
              }
              restored = { ...restored, ...mergedDraft };
            } else {
              restored = { ...restored, ...draft.data };
            }
            if (draft.data.has_cod === "1" || draft.data.has_cod === "true") {
              setHasCod(true);
            }
            if (draft.data.cod_changes) {
              try {
                const parsed = typeof draft.data.cod_changes === "string" ? JSON.parse(draft.data.cod_changes) : draft.data.cod_changes;
                if (Array.isArray(parsed)) setCodChanges(parsed);
              } catch (_) {}
            }
            if (draft.data.renewal_source_application_id) {
              setRenewalSource({
                id: Number(draft.data.renewal_source_application_id),
                application_number: draft.data.renewal_source_application_number || "",
                card_number: draft.data.renewal_card_number,
              });
            }
            if (!restored.category || !["WORK", "BUSINESS", "STUDENT", "RELIGIOUS"].includes(restored.category.toUpperCase())) {
              if (draft.documents.some((d) => d.type === "admission_letter")) {
                restored.category = "STUDENT";
              } else if (draft.documents.some((d) => d.type === "cac_certificate")) {
                restored.category = "BUSINESS";
              } else if (draft.documents.some((d) => d.type === "religious_invitation" || d.type === "religious_registration")) {
                restored.category = "RELIGIOUS";
              } else {
                restored.category = "WORK";
              }
            }
            if (draft.type) setType(draft.type);
            const resumedStep = Math.min(Math.max(1, draft.current_step || 1), 7);
            if (params.get("step") || params.get("resume") === "1") {
              setStep(resumedStep);
            }
            setDocuments(draft.documents);
            if (params.get("resume") === "1") {
              setNotice({ tone: "info", text: `Restored your application saved on ${new Date(draft.saved_at).toLocaleString("en-GB")}.` });
            }
          }
          if (!restored.phone) restored.phone = me.phone;
          if (!restored.email) restored.email = me.email;
        }

        const reference = params.get("reference") || params.get("trxref") || restored.payment_reference;
        if (reference) {
          const payment = await api<{ paid: boolean; used: boolean; paid_at?: string }>("applicant", `payments/${encodeURIComponent(reference)}/verify`, { method: "POST" }).catch(() => null);
          if (payment?.paid && !payment.used) {
            restored.payment_reference = reference;
            if (payment.paid_at) {
              restored.paid_at = payment.paid_at;
            }
            setPaid(true);
            setStep(7);
            await api("applicant", "draft", {
              method: "PUT",
              json: {
                type,
                current_step: 7,
                data: { ...restored, type, category: (restored.category || "WORK").toUpperCase(), payment_reference: reference, paid_at: restored.paid_at },
              },
            }).catch(() => null);
            setNotice({
              tone: "success",
              text: "Payment confirmed by Paystack. Please choose an enrollment center and appointment date to complete your application.",
            });
            if (params.get("payment") === "callback" && typeof window !== "undefined" && window.history?.replaceState) {
              const url = new URL(window.location.href);
              url.searchParams.delete("payment");
              url.searchParams.delete("trxref");
              url.searchParams.delete("reference");
              url.searchParams.set("resume", "1");
              window.history.replaceState({}, "", url.toString());
            }
          } else if (params.get("payment") === "callback") {
            setStep(6);
            setNotice({
              tone: "danger",
              text: "We could not confirm your payment. If you were charged, contact support with your payment reference.",
            });
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
    documentUrl(photo.id, queriedApp?.id)
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
  }, [photo, localPhotoPreview, photoLink?.id, photoLink?.url, queriedApp?.id]);

  /** Check a whole step; shows every problem at once, scrolls to first invalid field and focuses it. */
  function checkStep(n: number): boolean {
    const fields = stepFields(n);
    const extra = extraErrors(n);
    const allStepFields = [...fields, ...Object.keys(extra)];
    setTouched((t) => new Set([...t, ...allStepFields]));
    const fieldProblems = validate(fields, data);
    const problems = { ...fieldProblems, ...extra };
    setStepError(extra);

    if (Object.keys(problems).length > 0) {
      setFocusedField(null);

      // Collect all candidate elements for problem fields
      const candidates: { el: HTMLElement; key: string; msg: string }[] = [];

      // Step 4 specific: check document uploads
      if (n === 4) {
        if (problems.documents) {
          const requiredDocs = getDocuments(resolvedCategory, documents);
          const absent = requiredDocs.filter((d) => d.required && !documents.some((x) => x.type === d.type));
          for (const d of absent) {
            const el = document.getElementById(`doc-card-${d.type}`) || document.querySelector<HTMLElement>(`[data-doc-type="${d.type}"]`);
            if (el) {
              candidates.push({ el, key: d.type, msg: `Please upload ${d.label}.` });
            }
          }
        }
      }

      // Check all problem keys
      for (const [key, msg] of Object.entries(problems)) {
        if (n === 4 && key === "documents") {
          continue;
        }
        let el: HTMLElement | null = null;
        if (key === "photo") {
          el = document.getElementById("photo-upload-section") || document.querySelector<HTMLElement>('[data-field="photo"]');
        } else if (key === "declaration") {
          el = document.querySelector<HTMLElement>('input[name="declaration"]') || document.getElementById("declaration-checkbox");
        } else if (key === "cod") {
          el = document.getElementById("cod-section");
        } else {
          el = document.querySelector<HTMLElement>(`[data-field="${key}"] input:not([type="hidden"]), [data-field="${key}"] select, [name="${key}"]:not([type="hidden"]), [name="${key}_national"], [name="${key}"], #${key}`);
        }
        if (el) {
          if ((el as HTMLInputElement).type === "hidden") {
            const visible = el.parentElement?.querySelector<HTMLElement>("input:not([type='hidden']), select, textarea, button");
            if (visible) el = visible;
          }
          candidates.push({ el, key, msg });
        }
      }

      // Sort candidate elements by document visual position (top-to-bottom)
      if (candidates.length > 0) {
        candidates.sort((a, b) => {
          if (a.el === b.el) return 0;
          return a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
        });

        const first = candidates[0];
        const container = first.el.closest("label") || first.el.closest(".rounded-xl") || first.el;
        const rect = container.getBoundingClientRect();
        const headerOffset = 100;
        const targetTop = window.scrollY + rect.top - headerOffset;

        window.scrollTo({
          top: Math.max(0, targetTop),
          behavior: "smooth",
        });

        const focusable = first.el.matches("input:not([type='hidden']), select, textarea, button, [tabindex]")
          ? first.el
          : first.el.querySelector<HTMLElement>("input:not([type='hidden']), select, textarea, button, [tabindex]");

        if (focusable && typeof focusable.focus === "function") {
          setTimeout(() => {
            try {
              focusable.focus({ preventScroll: true });
            } catch (_) {
              focusable.focus();
            }
          }, 150);
        }

        setNotice({
          tone: "danger",
          text: t("Please fill in all required fields before you can proceed."),
        });
      } else {
        window.scrollTo({ top: 0, behavior: "smooth" });
        setNotice({
          tone: "danger",
          text: t("Please fill in all required fields before you can proceed."),
        });
      }

      return false;
    }

    setNotice(null);
    return true;
  }

  function goTo(n: number) {
    if (n > step) {
      for (let s = 1; s < n; s++) {
        if (!checkStep(s)) {
          setNotice({
            tone: "danger",
            text: `Please complete all required fields in ${STEPS[s - 1]} before proceeding.`,
          });
          setStep(s);
          setStepError({});
          window.scrollTo({ top: 0, behavior: "smooth" });
          return;
        }
      }
    }
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
      return;
    }

    // Step 1: Immediate server-side passport validation
    if (step === 1 && data.passport_number) {
      setBusy(true);
      try {
        await api<{ valid: boolean }>("applicant", "check-passport", {
          method: "POST",
          json: {
            passport_number: data.passport_number,
            travel_document_type: data.travel_document_type,
            type,
            renewal_card_number: data.renewal_card_number,
            principal_application_id: data.principal_application_id,
          },
        });
        setServerErrors((prev) => {
          const next = { ...prev };
          delete next.passport_number;
          return next;
        });
      } catch (err) {
        setBusy(false);
        const msg = err instanceof ApiError && err.errors?.passport_number?.[0]
          ? err.errors.passport_number[0]
          : "This passport number is already associated with an existing record. Please review your details or contact support if you believe this is an error.";
        setServerErrors((prev) => ({ ...prev, passport_number: msg }));
        setNotice({ tone: "danger", text: msg });
        const el = document.querySelector('[name="passport_number"]') as HTMLElement;
        if (el) {
          const rect = (el.closest("label") || el).getBoundingClientRect();
          window.scrollTo({ top: Math.max(0, window.scrollY + rect.top - 100), behavior: "smooth" });
          el.focus?.({ preventScroll: true });
        }
        return;
      }
    }

    // Pre-Payment Validation Guard:
    // When completing Step 5 (Review & Declaration), ensure all pre-payment steps (1 to 5)
    // and compulsory documents are complete and valid before advancing to Step 6 (Payment).
    if (step === 5) {
      for (let s = 1; s <= 5; s++) {
        if (!checkStep(s)) {
          goTo(s);
          setNotice({
            tone: "danger",
            text: `Please complete all required fields in ${STEPS[s - 1]} before proceeding to payment.`,
          });
          return;
        }
      }
    }

    setBusy(true);
    try {
      await saveDraft(step + 1);
      setNotice(null);
      goTo(step + 1);
    } catch (e) {
      if (e instanceof ApiError && e.errors) {
        setServerErrors(Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)])));
        const firstField = Object.keys(e.errors)[0];
        const msg = Object.values(e.errors)[0]?.[0] || e.message;
        setNotice({ tone: "danger", text: msg });
        const el = document.querySelector(`[name="${firstField}"], [data-field="${firstField}"]`) as HTMLElement;
        if (el) {
          const rect = (el.closest("label") || el).getBoundingClientRect();
          window.scrollTo({ top: Math.max(0, window.scrollY + rect.top - 100), behavior: "smooth" });
          el.focus?.({ preventScroll: true });
        }
      } else {
        setNotice({ tone: "danger", text: (e as Error).message || "A network or server error occurred while saving your progress. Please try again." });
      }
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
      if (queriedApp) {
        const result = await upload<{ data: Application }>(
          "applicant",
          `applications/${queriedApp.id}/documents`,
          { type: "photo", file }
        );
        const updatedApp = result.data;
        setQueriedApp(updatedApp);
        const appDocs: DraftDoc[] = (updatedApp.documents || []).map((d) => ({
          id: d.id,
          type: d.type,
          original_name: (d as any).file_name || d.label || d.type,
          size_bytes: (d as any).file_size_bytes || file.size,
          mime_type: (d as any).mime_type || file.type,
        }));
        setDocuments(appDocs);
        const uploadedDoc = appDocs.find((d) => d.type === "photo");
        if (uploadedDoc) {
          setPhotoLink({ id: uploadedDoc.id, url: objectUrl });
        }
        setPhotoUploadStatus("success");
        setNotice({ tone: "success", text: "Photograph updated successfully." });
      } else {
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
      }
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

    if (uploadingDocsRef.current.has(spec.type)) {
      return;
    }

    const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");
    const isImage = [
      "image/jpeg",
      "image/jpg",
      "image/pjpeg",
      "image/png",
      "image/x-png",
    ].includes(file.type.toLowerCase()) || /\.(jpe?g|png)$/i.test(file.name);

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

    uploadingDocsRef.current.add(spec.type);
    const objectUrl = isImage ? URL.createObjectURL(file) : undefined;

    setDocStates((prev) => {
      if (prev[spec.type]?.previewUrl && prev[spec.type]?.previewUrl?.startsWith("blob:")) {
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
      if (queriedApp) {
        const result = await upload<{ data: Application }>(
          "applicant",
          `applications/${queriedApp.id}/documents`,
          { type: spec.type, file }
        );
        const updatedApp = result.data;
        setQueriedApp(updatedApp);
        const mappedDocs: DraftDoc[] = (updatedApp.documents || []).map((d) => ({
          id: d.id,
          type: d.type,
          original_name: (d as any).file_name || d.label || d.type,
          size_bytes: (d as any).file_size_bytes || file.size,
          mime_type: (d as any).mime_type || (isPdf ? "application/pdf" : file.type),
        }));
        setDocuments(mappedDocs);
        const uploaded = mappedDocs.find((d) => d.type === spec.type);
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
        setNotice({ tone: "success", text: `${spec.label} uploaded successfully.` });
      } else {
        await saveDraft(step);
        const idDocType = spec.type === "emergency_contact_id_nigeria"
          ? data.emergency_contact_id_type
          : spec.type === "emergency_contact_id_home"
          ? data.home_country_emergency_contact_id_type
          : undefined;
        const uploadPayload: Record<string, any> = { type: spec.type, file };
        if (idDocType) uploadPayload.id_document_type = idDocType;
        const result = await upload<{ draft: Draft }>("applicant", "draft/documents", uploadPayload);
        setDocuments(result.draft.documents);
        const uploaded = result.draft.documents.find((d) => d.type === spec.type);
        setDocStates((prev) => ({
          ...prev,
          [spec.type]: {
            status: "success",
            fileName: uploaded?.original_name || file.name,
            fileSize: uploaded?.size_bytes || file.size,
            previewUrl: objectUrl || (uploaded as any)?.url,
            isPdf,
          },
        }));
      }
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
    } finally {
      uploadingDocsRef.current.delete(spec.type);
    }
  }

  async function removeDoc(spec: DocSpec) {
    if (queriedApp) return;
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
        const url = await documentUrl(current.id, queriedApp?.id);
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
      const url = await documentUrl(doc.id, queriedApp?.id);
      if (doc.mime_type?.startsWith("image/")) setPreview({ url, name: doc.original_name });
      else window.open(url, "_blank", "noopener");
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
    }
  }

  async function handleSubmitQueryResponse() {
    if (!queriedApp) return;
    setSubmittingResponse(true);
    setQueryResponseError(null);
    try {
      await api<{ data: Application }>("applicant", `applications/${queriedApp.id}/respond`, {
        method: "POST",
        json: { response: queryResponseText.trim() || undefined },
      });
      setQueryResponseSuccess(t("Your query response has been submitted successfully and returned to the review queue."));
      setTimeout(() => {
        router.push(`/portal/applications/${queriedApp.id}`);
      }, 1500);
    } catch (e) {
      setQueryResponseError((e as Error).message || "Failed to submit query response.");
    } finally {
      setSubmittingResponse(false);
    }
  }

  async function pay() {
    // Non-refundable payment acknowledgement check
    if (!paymentAcknowledged) {
      setAckError("Please acknowledge the non-refundable payment policy before proceeding.");
      return;
    }
    setAckError(null);

    // Pre-Payment Client Validation Guard across steps 1 through 5
    for (let s = 1; s <= 5; s++) {
      if (!checkStep(s)) {
        goTo(s);
        setNotice({
          tone: "danger",
          text: `Please complete all required fields in ${STEPS[s - 1]} before proceeding to payment.`,
        });
        return;
      }
    }

    setBusy(true);
    try {
      await saveDraft(step, {
        type,
        category: resolvedCategory,
        payment_acknowledged: "1",
        payment_acknowledged_at: new Date().toISOString(),
      });
      const payment = await api<{ reference: string; authorization_url: string | null; fake: boolean }>(
        "applicant",
        "payments",
        {
          method: "POST",
          json: {
            gateway: "paystack",
            acknowledged_non_refundable: true,
            acknowledged_at: new Date().toISOString(),
            acknowledgement_text:
              "I understand that the application processing fee is generally non-refundable once payment has been successfully processed, except where a refund is required by applicable law or the officially published refund policy.",
            version: "1.0",
          },
        }
      );
      await saveDraft(6, {
        type,
        category: resolvedCategory,
        payment_reference: payment.reference,
        payment_acknowledged: "1",
        payment_acknowledged_at: new Date().toISOString(),
      });
      if (payment.fake) {
        const verified = await api<{ paid: boolean; paid_at?: string }>("applicant", `payments/${payment.reference}/verify`, { method: "POST" });
        const paidAt = verified.paid_at || new Date().toISOString();
        setData((d) => ({ ...d, payment_reference: payment.reference, paid_at: paidAt, category: resolvedCategory }));
        setPaid(verified.paid);
        setStepError({});
        if (verified.paid) {
          await saveDraft(7, { type, category: resolvedCategory, payment_reference: payment.reference, paid_at: paidAt });
          setStep(7);
          setNotice({
            tone: "success",
            text: "Payment confirmed by Paystack. Please select your biometrics appointment date and center to complete your application.",
          });
        } else {
          setNotice({ tone: "danger", text: "Payment verification failed. Please try again." });
        }
        setBusy(false);
      } else if (payment.authorization_url) {
        window.location.href = payment.authorization_url;
      }
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        const fieldErrors = e.fieldErrors();
        setServerErrors(fieldErrors);
        const first = Math.min(...Object.keys(fieldErrors).map((f) => FIELD_STEP[f] ?? 6));
        goTo(first);
        const errorDescriptions = Object.entries(fieldErrors)
          .map(([k, errs]) => `${k.replace(/_/g, " ")}: ${Array.isArray(errs) ? errs.join(", ") : errs}`)
          .join("; ");
        setNotice({
          tone: "danger",
          text: errorDescriptions
            ? `Please correct the following: ${errorDescriptions}`
            : "Please complete all required application details before proceeding to payment.",
        });
      } else {
        setNotice({ tone: "danger", text: (e as Error).message });
      }
      setBusy(false);
    }
  }

  async function submit() {
    if (busy) return;
    if (!checkStep(7)) return;
    // The declaration (step 5) and payment (step 6) must both be complete.
    for (const n of [5, 6]) {
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
        json: {
          ...data,
          category: resolvedCategory,
          type,
          has_cod: hasCod,
          cod_changes: hasCod ? serializeCodChanges() : [],
          renewal_source_application_id: renewalSource?.id || (data.renewal_source_application_id ? Number(data.renewal_source_application_id) : undefined),
          declaration: true,
        },
      });
      router.push(`/portal/applications/${application.id}?submitted=1`);
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        const fieldErrors = e.fieldErrors();
        setServerErrors(fieldErrors);
        const first = Math.min(...Object.keys(fieldErrors).map((f) => FIELD_STEP[f] ?? 7));
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

  if (queriedApp) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">
            {t("Application Query Response")} · {queriedApp.application_number}
          </h1>
          <p className="mt-1 text-sm text-slate-600">
            {t("Please review the comments and provide the necessary information required.")}
          </p>
        </div>

        <div>
          <h2 className="mb-2 text-base font-semibold text-slate-900">{t("Comments")}</h2>
          <div className="rounded-lg border border-[#FECACA] bg-[#FFF1F2] p-3.5 sm:p-4 text-sm text-[#7F1D1D] leading-relaxed whitespace-pre-wrap break-words">
            {queriedApp.decision_notes || t("Please review and address the requested items.")}
          </div>
        </div>

        {notice && <Alert tone={notice.tone}>{notice.text}</Alert>}
        {queryResponseError && <Alert tone="danger">{queryResponseError}</Alert>}
        {queryResponseSuccess && <Alert tone="success">{queryResponseSuccess}</Alert>}

        <div>
          <h2 className="mb-3 text-base font-semibold text-slate-900">{t("Documents")}</h2>
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
              const previewUrl = state?.previewUrl || (current as any)?.url;

              let cardStyle = "border-slate-200 bg-white hover:border-slate-300";
              if (isUploading) cardStyle = "border-blue-300 bg-blue-50/20";
              else if (isError) cardStyle = "border-red-300 bg-red-50/20";
              else if (isUploaded) cardStyle = "border-emerald-300 bg-emerald-50/20";

              return (
                <div
                  key={d.type}
                  className={`flex flex-col gap-4 rounded-xl border p-4 shadow-sm transition-all sm:flex-row sm:items-center sm:justify-between ${cardStyle}`}
                >
                  <div className="flex items-start gap-3.5">
                    <div className="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
                      {isUploaded && !isPdf && previewUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={previewUrl} alt={fileName || d.label} className="h-full w-full object-cover" />
                      ) : isUploaded && !isPdf ? (
                        <div className="flex flex-col items-center justify-center text-emerald-600">
                          <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                          </svg>
                          <span className="text-[8px] font-bold tracking-tight">IMG</span>
                        </div>
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

                    <div className="space-y-1">
                      <div className="text-sm font-semibold text-slate-900">
                        {d.label} {d.required ? <span className="text-red-600">*</span> : <span className="text-xs font-normal text-slate-500">({t("Optional")})</span>}
                      </div>

                      {isUploading && (
                        <div className="flex items-center gap-1.5 text-xs font-medium text-blue-700">
                          <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                          </svg>
                          <span>{t("Uploading document…")}</span>
                          {fileName && <span className="truncate max-w-[200px] text-slate-500">({fileName})</span>}
                        </div>
                      )}

                      {isUploaded && (
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                            <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                              <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                            </svg>
                            {t("Document uploaded successfully")}
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
                            {t("Upload Failed")}
                          </div>
                          <p className="text-xs text-red-700">{state?.error || t("Please select a valid document.")}</p>
                        </div>
                      )}

                      {!isUploading && !isUploaded && !isError && (
                        <p className="text-xs text-slate-500">PDF, JPG or PNG up to 2 MB</p>
                      )}
                    </div>
                  </div>

                  <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                    {isUploaded && (
                      <>
                        <Button
                          variant="secondary"
                          type="button"
                          onClick={() => viewDoc(d)}
                          className="!text-xs !py-1.5 !px-3"
                        >
                          {t("View")}
                        </Button>
                        <UploadButton
                          label={t("Replace")}
                          accept={d.accept}
                          disabled={busy || isUploading}
                          onFile={(f) => uploadDocFile(d, f)}
                          className="!text-xs !py-1.5 !px-3"
                        />
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
                            {t("Retry")}
                          </Button>
                        )}
                        <UploadButton
                          label={t("Choose file")}
                          accept={d.accept}
                          disabled={busy || isUploading}
                          onFile={(f) => uploadDocFile(d, f)}
                          className="!text-xs !py-1.5 !px-3"
                        />
                      </>
                    )}

                    {!isUploaded && !isError && (
                      <UploadButton
                        label={isUploading ? t("Uploading…") : t("Upload document")}
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

        <div className="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
          <Button
            type="button"
            disabled={submittingResponse}
            onClick={handleSubmitQueryResponse}
            className="!bg-nis-primary hover:!bg-nis-primary-dark !text-white !font-bold !px-8 !py-2.5 !rounded-xl !shadow-md"
          >
            {submittingResponse ? (
              <>
                <Spinner className="mr-2 h-4 w-4" />
                {t("Submitting…")}
              </>
            ) : (
              t("Submit")
            )}
          </Button>

          <Button
            variant="ghost"
            onClick={() => router.push(`/portal/applications/${queriedApp.id}`)}
            disabled={submittingResponse}
          >
            {t("Return to Application")}
          </Button>
        </div>

        {preview && (
          <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-label={preview.name} onClick={() => setPreview(null)}>
            <div className="max-h-full max-w-3xl overflow-auto rounded-xl bg-white p-3 shadow-2xl" onClick={(e) => e.stopPropagation()}>
              <div className="mb-2 flex items-center justify-between gap-4">
                <span className="truncate text-sm font-medium">{preview.name}</span>
                <Button variant="secondary" onClick={() => setPreview(null)}>{t("Close")}</Button>
              </div>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={preview.url} alt={preview.name} className="max-h-[75vh] w-auto rounded" />
            </div>
          </div>
        )}
      </div>
    );
  }

  const isValidationNotice = Boolean(
    notice && (
      notice.text === t("Please fill in all required fields before you can proceed.") ||
      notice.text === "Please fill in all required fields before you can proceed."
    )
  );

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">
          {t(type === "RENEWAL" ? "Residence card renewal" : type === "REPLACE" ? "Replacement of a lost or stolen card" : "Residence card application")}
          {data.dependant_relationship && ` · for your ${data.dependant_relationship === "CHILD" ? "child" : "spouse"}`}
        </p>
        <h1 className="mt-1 text-xl sm:text-2xl font-semibold text-slate-900 leading-snug">
          {t("Step {n} of 7 — {title}", { n: step, title: t(STEPS[step - 1]) })}
        </h1>
        <ol className="mt-4 grid gap-1.5 grid-cols-7" aria-label="Progress">
          {STEPS.map((label, i) => (
            <li
              key={label}
              title={t(label)}
              aria-current={i + 1 === step ? "step" : undefined}
              className={`h-2 rounded-full transition-colors ${i + 1 < step ? "bg-nis-primary" : i + 1 === step ? "bg-nis-orange" : "bg-slate-200"}`}
            />
          ))}
        </ol>
      </div>

      {notice && !isValidationNotice && <Alert tone={notice.tone}>{notice.text}</Alert>}
      {errors.cod && <Alert tone="danger">{t(errors.cod)}</Alert>}

      {/* Centered required-fields validation container in the middle of the page */}
      {isValidationNotice && (
        <div
          className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in duration-150"
          role="dialog"
          aria-modal="true"
          aria-label="Required fields notice"
          onClick={() => setNotice(null)}
        >
          <div
            className="w-full max-w-sm rounded-xl bg-white p-4 sm:p-5 shadow-xl border border-red-100 text-center space-y-3 animate-in zoom-in-95 duration-150"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600">
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div className="space-y-1">
              <h3 className="text-sm font-bold text-slate-900">
                {t("Action Required")}
              </h3>
              <p className="text-xs sm:text-sm font-medium text-slate-700 leading-normal">
                {t("Please fill in all required fields before you can proceed.")}
              </p>
            </div>
            <div className="pt-1">
              <Button
                type="button"
                onClick={() => setNotice(null)}
                className="w-full justify-center !min-h-[36px] py-1.5 px-4 text-xs font-semibold !bg-nis-primary hover:!bg-nis-primary-dark text-white rounded-lg"
              >
                {t("OK")}
              </Button>
            </div>
          </div>
        </div>
      )}

      <Panel title={t(STEPS[step - 1])}>
        {/* Renewal: Change of Data Action Button & COD Section */}
        {type === "RENEWAL" && [1, 2, 3].includes(step) && (
          <div className="mb-6 space-y-4">
            {!hasCod ? (
              <div className="flex justify-end">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => setHasCod(true)}
                  className="!border-nis-primary !text-nis-primary hover:!bg-nis-primary/10 flex items-center gap-1.5 font-bold text-xs sm:text-sm shadow-xs"
                >
                  <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  {t("Change of Data")}
                </Button>
              </div>
            ) : (
              <div id="cod-section" data-field="cod" className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5 shadow-xs space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 pb-3">
                  <div>
                    <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                      <span>{t("Change of Data (COD) Request")}</span>
                      <span className="rounded-md bg-nis-mint/80 border border-nis-primary/20 px-2.5 py-0.5 text-xs font-semibold text-nis-primary-dark">
                        +₦15,000 ({t("Administrative Fee")})
                      </span>
                    </h3>
                    <p className="mt-1 text-xs text-slate-600">
                      {t("Change of Data applies only to corrections of Name (Surname and Other Names) and Date of Birth. All other information may be updated directly on the form without an administrative charge.")}
                    </p>
                  </div>
                  <div className="shrink-0">
                    <Button
                      type="button"
                      variant="secondary"
                      onClick={() => {
                        if (confirm(t("Cancel Change of Data? All requested field modifications and justifications will be discarded."))) {
                          setHasCod(false);
                          setCodChanges([]);
                          if (originalParticulars.surname) set("surname", originalParticulars.surname);
                          if (originalParticulars.forenames) set("forenames", originalParticulars.forenames);
                          if (originalParticulars.date_of_birth) set("date_of_birth", originalParticulars.date_of_birth);
                        }
                      }}
                      className="!border !border-slate-300 !bg-white hover:!bg-slate-50 !text-slate-700 text-xs sm:text-sm font-medium px-3.5 py-1.5 rounded-lg shadow-xs transition"
                    >
                      {t("Cancel Change of Data")}
                    </Button>
                  </div>
                </div>

                {/* List of active changes */}
                {(Array.isArray(codChanges) ? codChanges : []).length > 0 ? (
                  <div className="space-y-4">
                    {(Array.isArray(codChanges) ? codChanges : []).map((change, idx) => {
                      const isNameChange = change.field === "name" || change.field === "surname" || change.field === "forenames";
                      const spec = COD_AVAILABLE_OPTIONS.find((f) => f.key === (isNameChange ? "name" : "date_of_birth"));
                      return (
                        <div key={idx} className="rounded-xl border border-slate-200 bg-white p-4 space-y-3 relative shadow-xs">
                          <div className="flex items-center justify-between gap-2 border-b border-slate-200/60 pb-2">
                            <span className="text-xs font-bold uppercase tracking-wider text-slate-800">
                              #{idx + 1} · {t(isNameChange ? "Change of Name" : "Change of Date of Birth")}
                            </span>
                            <button
                              type="button"
                              onClick={() => {
                                setCodChanges((prev) => prev.filter((_, i) => i !== idx));
                                if (isNameChange) {
                                  if (originalParticulars.surname) set("surname", originalParticulars.surname);
                                  if (originalParticulars.forenames) set("forenames", originalParticulars.forenames);
                                } else {
                                  if (originalParticulars.date_of_birth) set("date_of_birth", originalParticulars.date_of_birth);
                                }
                              }}
                              className="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline"
                            >
                              {t("Remove change")}
                            </button>
                          </div>

                          {isNameChange ? (
                            <div className="space-y-3">
                              <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t("Existing Surname")} hint={t("Recorded on official file")}>
                                  <Input
                                    value={originalParticulars.surname || data.surname || "—"}
                                    readOnly
                                    disabled
                                    className="!bg-slate-100 !text-slate-600 font-mono text-xs sm:text-sm"
                                  />
                                </Field>
                                <Field label={t("Proposed New Surname")} required hint={t("Enter corrected surname")}>
                                  <Input
                                    value={change.requested_surname || data.surname || ""}
                                    placeholder={t("Enter new surname…")}
                                    onChange={(e) => {
                                      const val = e.target.value.toUpperCase();
                                      set("surname", val);
                                      setCodChanges((prev) => {
                                        const next = [...prev];
                                        next[idx] = {
                                          ...next[idx],
                                          requested_surname: val,
                                          requested_value: `${val} ${next[idx].requested_forenames || data.forenames || ""}`.trim(),
                                        };
                                        return next;
                                      });
                                    }}
                                  />
                                </Field>
                              </div>

                              <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t("Existing Other Names")} hint={t("Recorded on official file")}>
                                  <Input
                                    value={originalParticulars.forenames || data.forenames || "—"}
                                    readOnly
                                    disabled
                                    className="!bg-slate-100 !text-slate-600 font-mono text-xs sm:text-sm"
                                  />
                                </Field>
                                <Field label={t("Proposed New Other Names")} required hint={t("Enter corrected other names")}>
                                  <Input
                                    value={change.requested_forenames || data.forenames || ""}
                                    placeholder={t("Enter new other names…")}
                                    onChange={(e) => {
                                      const val = e.target.value.toUpperCase();
                                      set("forenames", val);
                                      setCodChanges((prev) => {
                                        const next = [...prev];
                                        next[idx] = {
                                          ...next[idx],
                                          requested_forenames: val,
                                          requested_value: `${next[idx].requested_surname || data.surname || ""} ${val}`.trim(),
                                        };
                                        return next;
                                      });
                                    }}
                                  />
                                </Field>
                              </div>
                            </div>
                          ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                              <Field label={t("Existing Date of Birth")} hint={t("Recorded on official file")}>
                                <Input
                                  value={originalParticulars.date_of_birth || data.date_of_birth || "—"}
                                  readOnly
                                  disabled
                                  className="!bg-slate-100 !text-slate-600 font-mono text-xs sm:text-sm"
                                />
                              </Field>
                              <Field label={t("Proposed New Date of Birth")} required hint={t("Enter corrected date of birth")}>
                                <Input
                                  type="date"
                                  value={data.date_of_birth || change.requested_value || ""}
                                  onChange={(e) => {
                                    const val = e.target.value;
                                    set("date_of_birth", val);
                                    setCodChanges((prev) => {
                                      const next = [...prev];
                                      next[idx] = { ...next[idx], requested_value: val };
                                      return next;
                                    });
                                  }}
                                />
                              </Field>
                            </div>
                          )}

                          <Field label={t("Reason for Change")} required hint={t("Explain why this change is required")}>
                            <Textarea
                              rows={2}
                              value={change.reason}
                              placeholder={isNameChange ? t("e.g. Change of marital status, legal deed poll, correction of spelling error…") : t("e.g. Correction of birth records, official NPC documentation…")}
                              onChange={(e) => {
                                const val = e.target.value;
                                setCodChanges((prev) => {
                                  const next = [...prev];
                                  next[idx] = { ...next[idx], reason: val };
                                  return next;
                                });
                              }}
                            />
                          </Field>

                          <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                              {t("Supporting Documents")} <span className="text-red-500">*</span>
                            </label>
                            {spec?.docHint && (
                              <p className="text-xs text-slate-500 mb-2">{spec.docHint}</p>
                            )}
                            <div className="flex flex-wrap items-center gap-3">
                              <label className="cursor-pointer inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition">
                                <svg className="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span>{codUploadBusy === idx ? t("Uploading…") : t("Upload supporting document")}</span>
                                <input
                                  type="file"
                                  className="sr-only"
                                  accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                  disabled={codUploadBusy === idx}
                                  onChange={(e) => {
                                    if (e.target.files?.[0]) {
                                      uploadCodDocument(idx, e.target.files[0]);
                                    }
                                  }}
                                />
                              </label>

                              {change.document_name ? (
                                <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-medium text-emerald-800">
                                  ✓ {change.document_name}
                                </span>
                              ) : (
                                <span className="text-xs text-slate-500 italic">
                                  {t("No document attached yet")}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
                    <p className="text-sm font-medium text-slate-700">
                      {t("No fields selected for modification yet.")}
                    </p>
                    <p className="text-xs text-slate-500 mt-1">
                      {t("Select Change of Name or Change of Date of Birth below to add your change request.")}
                    </p>
                  </div>
                )}

                {/* Add Field Form */}
                <div className="pt-2 border-t border-slate-200/80">
                  <div className="flex flex-wrap items-end gap-3">
                    <div className="w-full sm:w-72">
                      <Field label={t("Select field to change")}>
                        <Select
                          value={codFieldToAdd}
                          onChange={(e) => setCodFieldToAdd(e.target.value)}
                        >
                          <option value="">{t("Select field to change…")}</option>
                          {COD_AVAILABLE_OPTIONS.filter((f) => !(Array.isArray(codChanges) ? codChanges : []).some((c) => (c.field === f.key || (f.key === "name" && (c.field === "surname" || c.field === "forenames"))))).map((f) => (
                            <option key={f.key} value={f.key}>
                              {t(f.label)}
                            </option>
                          ))}
                        </Select>
                      </Field>
                    </div>
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={!codFieldToAdd}
                      onClick={addCodField}
                      className="text-xs sm:text-sm font-semibold"
                    >
                      + {t("Add another change")}
                    </Button>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

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
            <PersonalFields {...props} lockContact={type === "NEW"} />
          </div>
        )}

        {/* Step 3: Residence & contacts */}
        {step === 3 && <ContactFields {...props} />}

        {/* Step 4: Documents */}
        {step === 4 && (
          <div className="space-y-4">
            <div className="rounded-xl border border-slate-200 bg-nis-mint/40 p-4">
              <div className="text-sm font-semibold text-nis-primary-dark">
                {resolvedCategory === "STUDENT"
                  ? t("Required Documents for Student Category")
                  : resolvedCategory === "BUSINESS"
                  ? t("Required Documents for Business Category")
                  : resolvedCategory === "RELIGIOUS"
                  ? t("Required Documents for Religious Activities Category")
                  : t("Required Documents for Work Category")}
              </div>
              <p className="mt-1 text-xs text-slate-600">
                {t("Official Document Verification: Each file must be smaller than 2 MB in PDF, JPG, or PNG format. Ensure all documents are clear, fully legible, and upright. Your passport photograph was uploaded in step 2.")}
              </p>
            </div>

            {errors.documents && <Alert tone="danger">{t(errors.documents)}</Alert>}

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
                const previewUrl = state?.previewUrl || (current as any)?.url;

                const isAbsent = Boolean(errors.documents && d.required && !isUploaded);
                let cardStyle = "border-slate-200 bg-white hover:border-slate-300";
                if (isUploading) cardStyle = "border-blue-300 bg-blue-50/20";
                else if (isError || isAbsent) cardStyle = "border-red-300 bg-red-50/20";
                else if (isUploaded) cardStyle = "border-emerald-300 bg-emerald-50/20";

                return (
                  <div
                    key={d.type}
                    id={`doc-card-${d.type}`}
                    data-doc-type={d.type}
                    data-field={d.type}
                    className={`flex flex-col gap-4 rounded-xl border p-4 shadow-sm transition-all sm:flex-row sm:items-center sm:justify-between ${cardStyle}`}
                  >
                    {/* Left: Thumbnail / Badge & metadata */}
                    <div className="flex items-start gap-3.5">
                      {/* Document icon / preview box */}
                      <div className="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
                        {isUploaded && !isPdf && previewUrl ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={previewUrl} alt={fileName || d.label} className="h-full w-full object-cover" />
                        ) : isUploaded && !isPdf ? (
                          <div className="flex flex-col items-center justify-center text-emerald-600">
                            <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                              <circle cx="8.5" cy="8.5" r="1.5" />
                              <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <span className="text-[8px] font-bold tracking-tight">IMG</span>
                          </div>
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
                          {t(d.label)} {d.required ? <span className="text-red-600">*</span> : <span className="text-xs font-normal text-slate-500">({t("Optional")})</span>}
                        </div>
                        {isAbsent && (
                          <p className="text-xs font-medium text-red-600">{t("Please upload this compulsory document.")}</p>
                        )}

                        {/* Status lines */}
                        {isUploading && (
                          <div className="flex items-center gap-1.5 text-xs font-medium text-blue-700">
                            <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span>{t("Uploading document…")}</span>
                            {fileName && <span className="truncate max-w-[200px] text-slate-500">({fileName})</span>}
                          </div>
                        )}

                        {isUploaded && (
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                              <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                              </svg>
                              {t("Document uploaded successfully")}
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
                              {t("Upload Failed")}
                            </div>
                            <p className="text-xs text-red-700">{state?.error ? t(state.error) : t("Please select a valid document.")}</p>
                          </div>
                        )}

                        {!isUploading && !isUploaded && !isError && (
                          <p className="text-xs text-slate-500">{t("PDF, JPG or PNG up to 2 MB")}</p>
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
                            {t("View")}
                          </Button>
                          <UploadButton
                            label={t("Replace")}
                            accept={d.accept}
                            disabled={busy || isUploading}
                            onFile={(f) => uploadDocFile(d, f)}
                            className="!text-xs !py-1.5 !px-3"
                          />
                          {!queriedApp && (
                            <Button
                              variant="ghost"
                              type="button"
                              onClick={() => removeDoc(d)}
                              disabled={busy || isUploading}
                              className="!text-xs !py-1.5 !px-3 !text-red-700 hover:!bg-red-50"
                            >
                              {t("Remove")}
                            </Button>
                          )}
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
                              {t("Retry")}
                            </Button>
                          )}
                          <UploadButton
                            label={t("Choose file")}
                            accept={d.accept}
                            disabled={busy || isUploading}
                            onFile={(f) => uploadDocFile(d, f)}
                            className="!text-xs !py-1.5 !px-3"
                          />
                        </>
                      )}

                      {!isUploaded && !isError && (
                        <div className="flex flex-col sm:items-end gap-1">
                          <UploadButton
                            label={isUploading ? t("Uploading…") : t("Choose file")}
                            accept={d.accept}
                            disabled={busy || isUploading}
                            onFile={(f) => uploadDocFile(d, f)}
                            className="!text-xs !py-1.5 !px-3 !bg-nis-primary !text-white hover:!bg-nis-primary-dark"
                          />
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>


          </div>
        )}

        {/* Step 5: Review & declaration */}
        {step === 5 && (
          <div className="space-y-6">
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-xs text-slate-600 sm:text-sm">
              <svg className="h-4 w-4 shrink-0 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
              <span>{t("Check every detail carefully before you pay. Use Edit to change a section.")}</span>
            </div>

            <ReviewSection title="General Information" onEdit={() => goTo(1)}>
              <ReviewGrid
                items={[
                  ["Nationality", data.nationality],
                  ["Category / Class", (data.category || resolvedCategory) === "STUDENT" ? "Student (Intent)" : (data.category || resolvedCategory) === "BUSINESS" ? "Business (Trade / Artisans / Enterprise)" : (data.category || resolvedCategory) === "RELIGIOUS" ? "Cleric / Missionaries" : "Work (Employment)"],
                  ["Document Type", data.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate" : "Passport"],
                  [data.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate number" : "Passport number", data.passport_number],
                  ["Document Issue Date", nisDate(data.passport_issue_date)],
                  ["Document Expiring Date", nisDate(data.passport_expiry)],
                  ["Validity Period", "2 Years from date of production"],
                  ...(data.national_id_number ? [["National ID Number", data.national_id_number] as [string, string | undefined]] : []),
                  ...(data.tax_id_number ? [["Tax ID Number", data.tax_id_number] as [string, string | undefined]] : []),
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Personal details" onEdit={() => goTo(2)}>
              <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                {photoUrl && (
                  <div className="shrink-0 self-start">
                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-slate-50 p-1 shadow-sm">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={photoUrl} alt="Passport photograph" className="h-36 w-28 rounded object-cover" />
                    </div>
                  </div>
                )}
                <ReviewGrid
                  items={[
                    ...(type !== "NEW" ? [[type === "REPLACE" ? "Card being replaced" : "Card being renewed", data.renewal_card_number] as [string, string | undefined]] : []),
                    ...(data.dependant_relationship ? [["Applying for", `Your ${data.dependant_relationship === "CHILD" ? "child" : "spouse"}`] as [string, string | undefined]] : []),
                    ["Surname", data.surname], ["Other names", data.forenames], ["Nationality", data.nationality], ["Gender", data.sex],
                    ["Date of birth", nisDate(data.date_of_birth)], ["Place of birth", data.place_of_birth], ["Profession", data.profession],
                    ["Phone number", data.phone], ["E-mail address", data.email],
                    ["Distinguishing features", data.distinguished_features || "NONE"],
                  ]}
                />
              </div>
            </ReviewSection>

            <ReviewSection title="Residence & contacts" onEdit={() => goTo(3)}>
              <div className="space-y-4">
                <div>
                  <h4 className="text-xs font-bold uppercase tracking-wider text-nis-primary-dark mb-2">{t("Residential Address in Home Country")}</h4>
                  <ReviewGrid
                    items={[
                      ["Country", data.home_country || data.nationality],
                      ["State / Province / Region", data.home_country_state],
                      ["City / Town", data.home_country_city],
                      ...(data.home_country_district ? [["District / Municipality / Local Area", data.home_country_district] as [string, string]] : []),
                      ["Street Address / House Number", data.home_country_address],
                      ...(data.home_country_postal_code ? [["Postal / ZIP Code", data.home_country_postal_code] as [string, string]] : []),
                    ]}
                  />
                </div>
                <div className="border-t border-slate-100 pt-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-nis-primary-dark mb-2">{t("Residential address in Nigeria (domicile)")}</h4>
                  <ReviewGrid
                    items={[
                      ["Address in Nigeria", data.domicile],
                      ["Local government area", data.domicile_lga],
                      ["State", data.domicile_state],
                      ...(data.change_of_address ? [["Change of address", data.change_of_address] as [string, string]] : []),
                    ]}
                  />
                </div>
              </div>
            </ReviewSection>

            <ReviewSection title="Emergency contact in Nigeria" onEdit={() => goTo(3)}>
              <ReviewGrid
                items={[
                  ["Full name", data.emergency_contact_name], ["Relationship", formatRelationship(data.emergency_contact_relation)], ["Phone number", data.emergency_contact_phone],
                  ["Address", data.emergency_contact_address], ["Local government area", data.emergency_contact_lga], ["State", data.emergency_contact_state],
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Emergency contact in Home Country" onEdit={() => goTo(3)}>
              <ReviewGrid
                items={[
                  ["Full name", data.home_country_emergency_contact_name],
                  ["Relationship", formatRelationship(data.home_country_emergency_contact_relation)],
                  ["Phone number", data.home_country_emergency_contact_phone],
                  ["Country", data.home_country_emergency_contact_country || data.home_country || data.nationality],
                  ["State / Province / Region", data.home_country_emergency_contact_state],
                  ["City / Town", data.home_country_emergency_contact_city],
                  ...(data.home_country_emergency_contact_district ? [["District / Municipality / Local Area", data.home_country_emergency_contact_district] as [string, string]] : []),
                  ["Street Address / House Number", data.home_country_emergency_contact_address],
                  ...(data.home_country_emergency_contact_postal_code ? [["Postal / ZIP Code", data.home_country_emergency_contact_postal_code] as [string, string]] : []),
                ]}
              />
            </ReviewSection>

            <ReviewSection title="Documents" onEdit={() => goTo(4)}>
              <ul className="divide-y divide-slate-100 text-sm">
                {[PHOTO, ...currentDocs].map((d) => {
                  const current = documents.find((x) => x.type === d.type);
                  if (!current && !d.required) return null;
                  const isContactDoc = d.type === "emergency_contact_id_nigeria" || d.type === "emergency_contact_id_home";
                  const selectedIdType = d.type === "emergency_contact_id_nigeria"
                    ? data.emergency_contact_id_type
                    : d.type === "emergency_contact_id_home"
                    ? data.home_country_emergency_contact_id_type
                    : null;
                  const idTypeLabel = selectedIdType === "NATIONAL_ID"
                    ? "National Identity Card / National Identification Document"
                    : selectedIdType === "PASSPORT"
                    ? "International Passport"
                    : selectedIdType === "ECOWAS_TC"
                    ? "ECOWAS Travel Certificate"
                    : selectedIdType;

                  return (
                    <li key={d.type} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 py-3">
                      <div className="flex flex-col gap-0.5 min-w-0">
                        <div className="text-sm font-semibold text-slate-800">
                          {isContactDoc
                            ? t(d.type === "emergency_contact_id_nigeria"
                                ? "Identification Document — Emergency Contact in Nigeria"
                                : "Identification Document — Emergency Contact in Home Country")
                            : t(d.label)}
                        </div>
                        {isContactDoc && idTypeLabel && (
                          <div className="text-xs text-slate-600">
                            <span className="font-medium text-slate-500">{t("Document Type")}:</span>{" "}
                            <span className="font-medium text-slate-800">{t(idTypeLabel)}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-2 text-xs">
                          <span className="font-medium text-slate-500">{t("Status")}:</span>{" "}
                          {current ? (
                            <span className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                              <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                              </svg>
                              {t("Uploaded")}
                              <span className="font-normal text-slate-500">({current.original_name})</span>
                            </span>
                          ) : (
                            <span className="font-semibold text-red-600">{t("Missing")}</span>
                          )}
                        </div>
                      </div>
                      {current && (
                        <div className="shrink-0 pt-1 sm:pt-0">
                          <button
                            type="button"
                            onClick={() => view(current)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-nis-primary transition hover:bg-slate-50 hover:text-nis-primary-dark hover:underline"
                          >
                            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                              <circle cx="12" cy="12" r="3" />
                            </svg>
                            <span>{t("View")}</span>
                          </button>
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            </ReviewSection>

            {hasCod && codChanges.length > 0 && (
              <ReviewSection title="Change of Data (COD) Request" onEdit={() => goTo(1)}>
                <div className="space-y-4">
                  <div className="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900 font-medium">
                    {t("The following corrections have been requested and will be reviewed during vetting and approval.")}
                  </div>
                  <div className="divide-y divide-slate-100">
                    {codChanges.map((change, idx) => (
                      <div key={idx} className="py-3 text-xs sm:text-sm space-y-1">
                        <div className="font-semibold text-slate-800">{t(change.field_label || change.field)}</div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                          <div><span className="font-medium text-slate-500">{t("Existing Information")}:</span> {change.original_value || "—"}</div>
                          <div><span className="font-medium text-nis-primary">{t("Requested New Information")}:</span> <strong className="text-slate-900 font-mono">{change.requested_value}</strong></div>
                        </div>
                        <div className="text-slate-600"><span className="font-medium text-slate-500">{t("Reason for Change")}:</span> {change.reason}</div>
                        {change.document_name && (
                          <div className="text-slate-600"><span className="font-medium text-slate-500">{t("Supporting Documents")}:</span> {change.document_name}</div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </ReviewSection>
            )}

            <ReviewSection title="Residence card fee" onEdit={() => goTo(6)}>
              <ReviewGrid
                items={[
                  ...(hasCod
                    ? [
                        ["Normal Application Fee", fee !== null ? naira(fee) : "—"] as [string, string],
                        ["Change of Data Administrative Fee", naira(15000)] as [string, string],
                        ["Total Amount Payable", fee !== null ? naira(fee + 15000) : "—"] as [string, string],
                      ]
                    : [
                        ["Amount", fee !== null ? naira(fee) : "—"] as [string, string],
                      ]),
                  ["Payment reference", data.payment_reference ?? "Created when you pay (next step)"],
                  ["Payment status", paid ? "PAID — confirmed by Paystack" : "Not paid yet: you pay in the next step"],
                ]}
              />
            </ReviewSection>

            <div
              className={`rounded-xl border p-4 sm:p-5 transition shadow-sm ${
                errors.declaration ? "border-red-300 bg-red-50/50" : "border-slate-200 bg-nis-mint/60"
              }`}
            >
              <label className="flex items-start gap-3.5 cursor-pointer">
                <input
                  type="checkbox"
                  id="declaration-checkbox"
                  name="declaration"
                  data-field="declaration"
                  className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-nis-primary focus:ring-nis-primary/30"
                  checked={data.declaration === "1"}
                  onChange={(e) => {
                    set("declaration", e.target.checked ? "1" : "");
                    setStepError({});
                  }}
                />
                <span className="text-xs sm:text-sm text-slate-800 leading-relaxed">
                  {t("I solemnly declare that the particulars given in this application are true and correct. I understand that giving false information is an offence under the Immigration Act 2015.")}
                </span>
              </label>
              {errors.declaration && (
                <p className="mt-2 text-xs font-semibold text-red-700 flex items-center gap-1.5 pl-7">
                  <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                  </svg>
                  <span>{t(errors.declaration)}</span>
                </p>
              )}
            </div>
          </div>
        )}

        {/* Step 6: Fee payment */}
        {step === 6 && (
          <div className="space-y-5">
            {hasCod ? (
              <div className="grid gap-4 sm:grid-cols-3">
                <Summary label={t("Normal Application Fee")} value={fee !== null ? naira(fee) : "…"} />
                <Summary label={t("Change of Data Fee")} value={naira(15000)} />
                <Summary label={t("Total Amount Payable")} value={fee !== null ? naira(fee + 15000) : "…"} />
              </div>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2">
                <Summary label={t("Residence card fee")} value={fee !== null ? naira(fee) : "…"} />
                <Summary label={t("Payment reference")} value={data.payment_reference ?? t("Created when you pay")} />
              </div>
            )}
            {paid ? (
              <div className="space-y-3">
                <Alert tone="success">{t("Payment confirmed by Paystack. Your payment has been verified.")}</Alert>
                <Button onClick={() => setStep(7)} className="!bg-nis-green">
                  {t("Continue to Biometrics Appointment →")}
                </Button>
              </div>
            ) : (
              <>
                {errors.payment_reference && <Alert tone="danger">{t(errors.payment_reference)}</Alert>}

                {/* Mandatory Non-Refundable Payment Acknowledgement */}
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                  <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {t("Payment Acknowledgement")}
                  </div>
                  <label className="flex items-start gap-3 cursor-pointer text-sm text-slate-700">
                    <input
                      type="checkbox"
                      checked={paymentAcknowledged}
                      onChange={(e) => {
                        setPaymentAcknowledged(e.target.checked);
                        if (e.target.checked) setAckError(null);
                      }}
                      className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-nis-green focus:ring-nis-green"
                    />
                    <span className="leading-relaxed">
                      {t("I understand that the application processing fee is generally non-refundable once payment has been successfully processed, except where a refund is required by applicable law or the officially published refund policy.")}
                    </span>
                  </label>
                  {ackError && (
                    <p className="mt-1 text-xs font-semibold text-red-600">
                      {t(ackError)}
                    </p>
                  )}
                </div>

                <Button
                  onClick={pay}
                  disabled={busy || !paymentAcknowledged}
                  className="!bg-nis-orange hover:!brightness-95 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {busy ? t("Opening Paystack…") : t("Pay securely with Paystack")}
                </Button>
                <p className="text-xs text-slate-500">{t("You pay on Paystack's secure page by card, bank transfer or USSD, then return here automatically. You cannot continue until the payment is confirmed.")}</p>
              </>
            )}
          </div>
        )}

        {/* Step 7: Biometrics appointment */}
        {step === 7 && (
          <div className="space-y-6">
            <AppointmentPicker {...props} />
            <div className="grid gap-4 sm:grid-cols-3">
              <Summary label="Declaration" value={data.declaration === "1" ? "Accepted" : "Not accepted"} />
              <Summary label="Residence card fee" value={fee !== null ? (hasCod ? naira(fee + 15000) : naira(fee)) : "…"} />
              <Summary label="Payment reference" value={paid ? data.payment_reference ?? "—" : "Not paid"} />
            </div>
            <p className="text-sm text-slate-600">{t("Choose your appointment, then click Submit application.")}</p>
          </div>
        )}
      </Panel>

      <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          {step > 1 && <Button variant="secondary" onClick={() => goTo(step - 1)} disabled={busy} className="min-h-[40px]">{t("Back")}</Button>}
          <Button variant="ghost" onClick={saveAndExit} disabled={busy} className="min-h-[40px]">{t("Save & exit")}</Button>
          <Button variant="ghost" onClick={discard} disabled={busy} className="min-h-[40px] text-red-700">{t("Discard")}</Button>
        </div>
        {step < 7 ? (
          <Button onClick={next} disabled={busy || (step === 6 && !paid)} className="min-h-[40px] w-full sm:w-auto">{busy ? t("Saving…") : t("Save & continue")}</Button>
        ) : (
          <Button onClick={submit} disabled={busy} className="min-h-[40px] w-full sm:w-auto">{busy ? t("Submitting…") : t("Submit application")}</Button>
        )}
      </div>
      <p className="text-xs text-slate-500">{t("Need help?")} <Link href="/track" className="underline">{t("Track an existing application")}</Link>.</p>

      {preview && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-label={preview.name} onClick={() => setPreview(null)}>
          <div className="max-h-full max-w-3xl overflow-auto rounded-xl bg-white p-3 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-2 flex items-center justify-between gap-4">
              <span className="truncate text-sm font-medium">{preview.name}</span>
              <Button variant="secondary" onClick={() => setPreview(null)}>{t("Close")}</Button>
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
  const { t } = useI18n();
  return (
    <div className="rounded-lg border border-slate-200 bg-nis-mint px-4 py-3">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{t(label)}</div>
      <div className="mt-1 text-base font-medium text-nis-primary-dark">{typeof value === "string" ? t(value) : value}</div>
    </div>
  );
}

function ReviewGrid({ items }: { items: [string, string | undefined][] }) {
  const { t } = useI18n();
  return (
    <dl className="grid flex-1 grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
      {items.map(([k, v]) => (
        <div key={k} className="flex flex-col">
          <dt className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t(k)}</dt>
          <dd className="mt-1 break-words text-sm font-medium text-slate-900 leading-snug">{v ? t(v) : "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

function ReviewSection({ title, onEdit, children }: { title: string; onEdit: () => void; children: ReactNode }) {
  const { t } = useI18n();
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-slate-300">
      <header className="flex items-center justify-between border-b border-slate-200/80 bg-nis-mint/60 px-5 py-3">
        <h3 className="text-sm font-bold text-nis-primary-dark">{t(title)}</h3>
        <button
          type="button"
          onClick={onEdit}
          className="inline-flex items-center gap-1 text-xs font-semibold text-nis-primary transition hover:text-nis-primary-dark hover:underline"
        >
          <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
          <span>{t("Edit")}</span>
        </button>
      </header>
      <div className="p-5">{children}</div>
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
  const { t } = useI18n();
  const displayUrl = localPreview || url;

  return (
    <div
      id="photo-upload-section"
      data-field="photo"
      className={`flex flex-col gap-5 rounded-xl border p-4 sm:flex-row sm:items-center ${error ? "border-red-400 bg-red-50" : "border-slate-200 bg-nis-mint"}`}
    >
      <div className="relative flex h-40 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-300 bg-white shadow-inner">
        {displayUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={displayUrl} alt={t("Passport photograph")} className="h-full w-full object-cover" />
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
            <span className="mt-1.5 text-[11px] font-medium tracking-wide">{t("Uploading…")}</span>
          </div>
        )}
      </div>
      <div className="space-y-2">
        <div className="font-semibold text-slate-900">
          {t("Passport photograph")} <span className="text-red-600">*</span>
        </div>
        <p className="text-sm text-slate-600">{t("A recent colour photograph of your face on a white background. JPG or PNG, smaller than 2 MB.")}</p>

        {/* Status badges */}
        {uploadStatus === "uploading" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">
            <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            {t("Uploading photograph…")}
          </div>
        )}
        {uploadStatus !== "uploading" && (uploadStatus === "success" || (photo && !error)) && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
            </svg>
            {t("Upload Successful")} {photo ? `(${photo.original_name})` : ""}
          </div>
        )}
        {uploadStatus === "error" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            {t("Upload Failed — please select a valid photo")}
          </div>
        )}
        {displayUrl && uploadStatus === "idle" && (
          <div className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
            {t("Photograph Selected")}
          </div>
        )}

        {error && <p className="text-sm font-medium text-red-700">{t(error)}</p>}
        <div className="flex flex-wrap gap-2 pt-1">
          <UploadButton
            label={displayUrl ? t("Change photograph") : t("Upload photograph")}
            accept={PHOTO.accept}
            disabled={busy || uploadStatus === "uploading"}
            onFile={onUpload}
          />
          {displayUrl && (
            <Button variant="secondary" onClick={onView} disabled={uploadStatus === "uploading"}>
              {t("View")}
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
