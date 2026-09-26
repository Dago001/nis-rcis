"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useMemo, useState, type ReactNode } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { ContactFields, PassportFields, PersonalFields } from "@/components/ParticularsFields";
import { Alert, Button, Field, Input, Panel, Spinner } from "@/components/ui";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application, EnrollmentCenter } from "@/lib/types";
import { APPOINTMENT_FIELDS, CONTACT_FIELDS, fieldError, PASSPORT_FIELDS, PERSONAL_FIELDS, validate } from "@/lib/validation";

// Review & declaration come before payment; the appointment is booked last, then the application is submitted.
const STEPS = ["Personal details", "Passport", "Residence & contacts", "Documents", "Review & declaration", "Fee payment", "Biometrics appointment"];

const MAX_BYTES = 2 * 1024 * 1024;

type DocSpec = { type: string; label: string; required: boolean; accept: string; kinds: string };

const PHOTO: DocSpec = { type: "photo", label: "Passport photograph (white background)", required: true, accept: "image/jpeg,image/png", kinds: "JPG or PNG" };

const DOCUMENTS: DocSpec[] = [
  { type: "passport_copy", label: "International passport data page", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
  { type: "residence_visa", label: "Subject-to-Regularization / residence visa", required: true, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
  { type: "quota_approval", label: "Expatriate quota approval", required: false, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
  { type: "domicile_proof", label: "Proof of domicile", required: false, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
  { type: "additional", label: "Additional document", required: false, accept: "image/jpeg,image/png,application/pdf", kinds: "JPG, PNG or PDF" },
];

/** Which step each field lives on (to jump back when the API rejects one). */
const FIELD_STEP: Record<string, number> = {
  ...Object.fromEntries(PERSONAL_FIELDS.map((f) => [f, 1])),
  blood_group: 1, renewal_card_number: 1, photo: 1,
  ...Object.fromEntries(PASSPORT_FIELDS.map((f) => [f, 2])),
  ...Object.fromEntries(CONTACT_FIELDS.map((f) => [f, 3])),
  documents: 4, declaration: 5, payment_reference: 6,
  ...Object.fromEntries(APPOINTMENT_FIELDS.map((f) => [f, 7])),
};

type DraftDoc = { id: number; type: string; label: string; original_name: string; size_bytes: number; mime_type: string };
type Draft = { type: "NEW" | "RENEWAL"; current_step: number; data: Record<string, string>; documents: DraftDoc[]; saved_at: string };

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
  const [loaded, setLoaded] = useState(false);
  const [step, setStep] = useState(1);
  const [type, setType] = useState<"NEW" | "RENEWAL">(params.get("type") === "renewal" ? "RENEWAL" : "NEW");
  const [data, setData] = useState<Record<string, string>>({});
  const [documents, setDocuments] = useState<DraftDoc[]>([]);
  const [touched, setTouched] = useState<Set<string>>(new Set());
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [stepError, setStepError] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<{ tone: "success" | "danger" | "info"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [paid, setPaid] = useState(false);
  const [fee, setFee] = useState<number | null>(null);
  const [photoLink, setPhotoLink] = useState<{ id: number; url: string } | null>(null);
  const [preview, setPreview] = useState<{ url: string; name: string } | null>(null);

  const set = useCallback((key: string, value: string) => {
    setData((d) => ({ ...d, [key]: value }));
    setServerErrors((e) => (e[key] ? { ...e, [key]: "" } : e));
  }, []);
  const touch = useCallback((key: string) => setTouched((t) => (t.has(key) ? t : new Set(t).add(key))), []);

  const photo = documents.find((d) => d.type === "photo");
  const photoUrl = photo && photoLink?.id === photo.id ? photoLink.url : null;

  /** Fields validated on each step. */
  const stepFields = useCallback(
    (n: number) =>
      n === 1 ? [...(type === "RENEWAL" ? ["renewal_card_number"] : []), ...PERSONAL_FIELDS]
        : n === 2 ? PASSPORT_FIELDS
          // Phone and e-mail are locked (taken from the account), so they are not re-checked here.
          : n === 3 ? CONTACT_FIELDS.filter((f) => f !== "phone" && f !== "email")
            : n === 7 ? APPOINTMENT_FIELDS
              : [],
    [type],
  );

  /** Errors that are not about a single form field (photo, documents, payment, declaration). */
  const extraErrors = useCallback(
    (n: number): Record<string, string> => {
      const e: Record<string, string> = {};
      if (n === 1 && !photo) e.photo = "Upload your passport photograph.";
      if (n === 4) {
        const absent = DOCUMENTS.filter((d) => d.required && !documents.some((x) => x.type === d.type));
        if (absent.length) e.documents = `Please upload: ${absent.map((d) => d.label).join(", ")}.`;
      }
      if (n === 5 && data.declaration !== "1") e.declaration = "You must accept the declaration before you pay.";
      if (n === 6 && !paid) e.payment_reference = "Pay the residence card fee before you continue.";
      return e;
    },
    [photo, documents, paid, data.declaration],
  );

  // Errors shown now: every visited field is checked as the applicant types.
  const errors = useMemo(() => {
    const shown: Record<string, string> = {};
    for (const field of touched) {
      const message = fieldError(field, data);
      if (message) shown[field] = message;
    }
    for (const [field, message] of Object.entries(serverErrors)) if (message) shown[field] = message;
    return { ...shown, ...stepError };
  }, [touched, data, serverErrors, stepError]);

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
    (async () => {
      const [{ draft }, me, centerList] = await Promise.all([
        api<{ draft: Draft | null }>("applicant", "draft"),
        api<{ surname: string; forenames: string; email: string; phone: string }>("applicant", "me"),
        api<{ data: EnrollmentCenter[]; fee_naira: number }>("public", "enrollment-centers"),
      ]);
      setFee(centerList.fee_naira);

      let restored: Record<string, string> = { surname: me.surname, forenames: me.forenames };
      if (draft) {
        restored = { ...restored, ...draft.data };
        setType(draft.type);
        setStep(draft.current_step);
        setDocuments(draft.documents);
        setNotice({ tone: "info", text: `Restored your application saved on ${new Date(draft.saved_at).toLocaleString("en-GB")}.` });
      }
      // Phone and e-mail always come from the registered account.
      restored.phone = me.phone;
      restored.email = me.email;

      const reference = params.get("reference") || params.get("trxref") || restored.payment_reference;
      if (reference) {
        const payment = await api<{ paid: boolean; used: boolean }>("applicant", `payments/${encodeURIComponent(reference)}/verify`, { method: "POST" }).catch(() => null);
        if (payment?.paid && !payment.used) {
          restored.payment_reference = reference;
          setPaid(true);
          if (params.get("payment") === "callback") {
            setStep(6);
            setNotice({ tone: "success", text: "Payment confirmed by Paystack. Click “Save & continue” to book your biometrics appointment." });
          }
        } else if (params.get("payment") === "callback") {
          setStep(6);
          setNotice({ tone: "danger", text: "We could not confirm your payment. If you were charged, contact support with your payment reference." });
        }
      }
      setData(restored);
      setLoaded(true);
    })();
  }, [params]);

  // Passport photograph preview.
  useEffect(() => {
    if (!photo) return;
    let cancelled = false;
    documentUrl(photo.id).then((url) => !cancelled && setPhotoLink({ id: photo.id, url })).catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [photo]);

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

  async function uploadDocument(spec: DocSpec, file: File) {
    const key = spec.type === "photo" ? "photo" : "documents";
    if (file.size > MAX_BYTES) {
      setStepError({ [key]: `${spec.label}: the file is ${kb(file.size)}. Please upload a file smaller than 2 MB.` });
      return;
    }
    if (!spec.accept.split(",").includes(file.type)) {
      setStepError({ [key]: `${spec.label}: please upload a ${spec.kinds} file.` });
      return;
    }
    setBusy(true);
    setStepError({});
    try {
      await saveDraft(step);
      const result = await upload<{ draft: Draft }>("applicant", "draft/documents", { type: spec.type, file });
      setDocuments(result.draft.documents);
    } catch (e) {
      if (e instanceof ApiError) setStepError({ [key]: Object.values(e.fieldErrors())[0] ?? e.message });
    } finally {
      setBusy(false);
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
      await saveDraft(6, { payment_reference: payment.reference });
      if (payment.fake) {
        const verified = await api<{ paid: boolean }>("applicant", `payments/${payment.reference}/verify`, { method: "POST" });
        setData((d) => ({ ...d, payment_reference: payment.reference }));
        setPaid(verified.paid);
        setStepError({});
        setNotice({ tone: "info", text: "Test mode: no Paystack keys are configured, so the payment was simulated." });
        setBusy(false);
      } else if (payment.authorization_url) {
        window.location.href = payment.authorization_url; // Paystack secure checkout; returns to this page
      }
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
      setBusy(false);
    }
  }

  async function submit() {
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
        json: { ...data, type, declaration: true },
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

  if (!loaded) return <Spinner />;

  const props = { data, errors, set, touch };

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">{type === "RENEWAL" ? "Residence card renewal" : "Residence card application"}</p>
        <h1 className="mt-1 text-2xl font-semibold text-slate-900">Step {step} of 7 — {STEPS[step - 1]}</h1>
        <ol className="mt-4 grid grid-cols-7 gap-1.5" aria-label="Progress">
          {STEPS.map((label, i) => (
            <li key={label} title={label} className={`h-2 rounded-full ${i + 1 < step ? "bg-nis-primary" : i + 1 === step ? "bg-nis-orange" : "bg-slate-200"}`} />
          ))}
        </ol>
      </div>

      {notice && <Alert tone={notice.tone}>{notice.text}</Alert>}
      {currentStepHasErrors && <Alert tone="danger">Please correct the highlighted details.</Alert>}

      <Panel title={STEPS[step - 1]}>
        {step === 1 && (
          <div className="space-y-6">
            <PhotoUpload photo={photo} url={photoUrl} error={errors.photo} busy={busy} onUpload={(f) => uploadDocument(PHOTO, f)} onView={() => photo && view(photo)} />
            {type === "RENEWAL" && (
              <Field label="Residence card number being renewed" required error={errors.renewal_card_number}>
                <Input value={data.renewal_card_number ?? ""} inputMode="numeric" maxLength={12} onChange={(e) => set("renewal_card_number", e.target.value.replace(/\D/g, ""))} onBlur={() => touch("renewal_card_number")} />
              </Field>
            )}
            <PersonalFields {...props} />
          </div>
        )}
        {step === 2 && <PassportFields {...props} />}
        {step === 3 && <ContactFields {...props} lockContact />}
        {step === 4 && (
          <div className="space-y-4">
            <p className="text-sm text-slate-600">Each file must be <strong>smaller than 2 MB</strong>: JPG, PNG or PDF. Your passport photograph was uploaded in step 1.</p>
            {errors.documents && <Alert tone="danger">{errors.documents}</Alert>}
            <ul className="divide-y divide-slate-100">
              {DOCUMENTS.map((d) => {
                const current = documents.find((x) => x.type === d.type);
                return (
                  <li key={d.type} className="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                      <div className="text-sm font-medium">{d.label}{d.required && <span className="text-red-600"> *</span>}</div>
                      <div className={`text-xs ${current ? "text-nis-primary" : "text-slate-500"}`}>{current ? `✓ ${current.original_name} (${kb(current.size_bytes)})` : "Not uploaded"}</div>
                    </div>
                    <div className="flex gap-2">
                      {current && <Button variant="secondary" onClick={() => view(current)}>View</Button>}
                      <UploadButton label={current ? "Replace" : "Upload"} accept={d.accept} disabled={busy} onFile={(f) => uploadDocument(d, f)} />
                    </div>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
        {step === 5 && (
          <div className="space-y-6">
            <p className="text-sm text-slate-600">Check every detail carefully before you pay. Use <strong>Edit</strong> to change a section.</p>
            <ReviewSection title="Personal details" onEdit={() => goTo(1)}>
              <div className="flex flex-col gap-5 sm:flex-row">
                {photoUrl && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photoUrl} alt="Passport photograph" className="h-40 w-32 shrink-0 rounded-lg border object-cover" />
                )}
                <ReviewGrid
                  items={[
                    ...(type === "RENEWAL" ? [["Card being renewed", data.renewal_card_number] as [string, string | undefined]] : []),
                    ["Surname", data.surname], ["Other names", data.forenames], ["Nationality", data.nationality], ["Sex", data.sex],
                    ["Date of birth", nisDate(data.date_of_birth)], ["Place of birth", data.place_of_birth], ["Profession", data.profession],
                    ["Blood group", data.blood_group || "UNKNOWN"], ["Height", data.height], ["Complexion", data.complexion],
                    ["Colour of eyes", data.eye_color], ["Colour of hair", data.hair_color], ["Distinguishing features", data.distinguished_features || "NONE"],
                  ]}
                />
              </div>
            </ReviewSection>
            <ReviewSection title="Passport" onEdit={() => goTo(2)}>
              <ReviewGrid items={[["Passport number", data.passport_number], ["Issue date", nisDate(data.passport_issue_date)], ["Expiry date", nisDate(data.passport_expiry)], ["NIN", data.national_id_number], ["Tax ID", data.tax_id_number]]} />
            </ReviewSection>
            <ReviewSection title="Residence & contacts" onEdit={() => goTo(3)}>
              <ReviewGrid
                items={[
                  ["Address in Nigeria", data.domicile], ["Local government area", data.domicile_lga], ["State", data.domicile_state],
                  ["Change of address", data.change_of_address], ["Phone number", data.phone], ["E-mail address", data.email],
                ]}
              />
            </ReviewSection>
            <ReviewSection title="Emergency contact" onEdit={() => goTo(3)}>
              <ReviewGrid
                items={[
                  ["Full name", data.emergency_contact_name], ["Relationship", data.emergency_contact_relation], ["Phone number", data.emergency_contact_phone],
                  ["Address", data.emergency_contact_address], ["Local government area", data.emergency_contact_lga], ["State", data.emergency_contact_state],
                ]}
              />
            </ReviewSection>
            <ReviewSection title="Documents" onEdit={() => goTo(4)}>
              <ul className="divide-y divide-slate-100 text-sm">
                {[PHOTO, ...DOCUMENTS].map((d) => {
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
            <ReviewSection title="Residence card fee" onEdit={() => goTo(6)}>
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
        {step === 6 && (
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
                <Button onClick={pay} disabled={busy} className="!bg-nis-orange hover:!brightness-95">{busy ? "Opening Paystack…" : "Pay securely with Paystack"}</Button>
                <p className="text-xs text-slate-500">You pay on Paystack&apos;s secure page by card, bank transfer or USSD, then return here automatically. You cannot continue until the payment is confirmed.</p>
              </>
            )}
          </div>
        )}
        {step === 7 && (
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
          {step > 1 && <Button variant="secondary" onClick={() => goTo(step - 1)} disabled={busy}>Back</Button>}
          <Button variant="ghost" onClick={saveAndExit} disabled={busy}>Save &amp; exit</Button>
          <Button variant="ghost" onClick={discard} disabled={busy} className="text-red-700">Discard</Button>
        </div>
        {step < 7 ? (
          <Button onClick={next} disabled={busy || (step === 6 && !paid)}>{busy ? "Saving…" : "Save & continue"}</Button>
        ) : (
          <Button onClick={submit} disabled={busy}>{busy ? "Submitting…" : "Submit application"}</Button>
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

function UploadButton({ label, accept, disabled, onFile }: { label: string; accept: string; disabled?: boolean; onFile: (file: File) => void }) {
  return (
    <label className={`inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 ${disabled ? "pointer-events-none opacity-50" : ""}`}>
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

function PhotoUpload({ photo, url, error, busy, onUpload, onView }: { photo?: DraftDoc; url: string | null; error?: string; busy: boolean; onUpload: (f: File) => void; onView: () => void }) {
  return (
    <div className={`flex flex-col gap-5 rounded-xl border p-4 sm:flex-row sm:items-center ${error ? "border-red-400 bg-red-50" : "border-slate-200 bg-nis-mint"}`}>
      <div className="flex h-40 w-32 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-300 bg-white">
        {url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={url} alt="Your passport photograph" className="h-full w-full object-cover" />
        ) : (
          <svg className="h-14 w-14 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden>
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" />
          </svg>
        )}
      </div>
      <div className="space-y-2">
        <div className="font-semibold text-slate-900">Passport photograph <span className="text-red-600">*</span></div>
        <p className="text-sm text-slate-600">A recent colour photograph of your face on a white background. JPG or PNG, smaller than 2 MB.</p>
        {photo && <p className="text-xs text-nis-primary">✓ {photo.original_name}</p>}
        {error && <p className="text-sm text-red-700">{error}</p>}
        <div className="flex gap-2">
          <UploadButton label={photo ? "Change photograph" : "Upload photograph"} accept={PHOTO.accept} disabled={busy} onFile={onUpload} />
          {photo && <Button variant="secondary" onClick={onView}>View</Button>}
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
