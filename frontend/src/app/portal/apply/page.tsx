"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { ContactFields, PassportFields, PersonalFields, STEP_FIELDS } from "@/components/ParticularsFields";
import { Alert, Button, Dl, Field, Input, Panel, Spinner } from "@/components/ui";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application } from "@/lib/types";

const STEPS = ["Personal details", "Passport", "Residence & contacts", "Documents", "Fee payment", "Biometrics appointment", "Review & declaration"];

const DOCUMENTS: { type: string; label: string; required: boolean; accept: string }[] = [
  { type: "photo", label: "Passport photograph (white background)", required: true, accept: "image/jpeg,image/png" },
  { type: "passport_copy", label: "International passport data page", required: true, accept: "image/jpeg,image/png,application/pdf" },
  { type: "residence_visa", label: "Subject-to-Regularization / residence visa", required: true, accept: "image/jpeg,image/png,application/pdf" },
  { type: "quota_approval", label: "Expatriate quota approval", required: false, accept: "image/jpeg,image/png,application/pdf" },
  { type: "domicile_proof", label: "Proof of domicile", required: false, accept: "image/jpeg,image/png,application/pdf" },
  { type: "additional", label: "Additional document", required: false, accept: "image/jpeg,image/png,application/pdf" },
];

const FIELD_STEP: Record<string, number> = {
  ...Object.fromEntries(STEP_FIELDS[1].map((f) => [f, 1])),
  height: 1, complexion: 1, eye_color: 1, hair_color: 1, distinguished_features: 1, blood_group: 1, renewal_card_number: 1,
  ...Object.fromEntries(STEP_FIELDS[2].map((f) => [f, 2])),
  passport_issue_date: 2, national_id_number: 2, tax_id_number: 2,
  ...Object.fromEntries(STEP_FIELDS[3].map((f) => [f, 3])),
  change_of_address: 3, documents: 4, payment_reference: 5,
  enrollment_center_id: 6, appointment_date: 6, appointment_time: 6, declaration: 7,
};

type DraftDoc = { id: number; type: string; label: string; original_name: string };
type Draft = { type: "NEW" | "RENEWAL"; current_step: number; data: Record<string, string>; documents: DraftDoc[]; saved_at: string };

function Wizard() {
  const router = useRouter();
  const params = useSearchParams();
  const [loaded, setLoaded] = useState(false);
  const [step, setStep] = useState(1);
  const [type, setType] = useState<"NEW" | "RENEWAL">(params.get("type") === "renewal" ? "RENEWAL" : "NEW");
  const [data, setData] = useState<Record<string, string>>({});
  const [documents, setDocuments] = useState<DraftDoc[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<{ tone: "success" | "danger" | "info"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [paid, setPaid] = useState(false);

  const set = useCallback((key: string, value: string) => setData((d) => ({ ...d, [key]: value })), []);

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
      const [{ draft }, me] = await Promise.all([
        api<{ draft: Draft | null }>("applicant", "draft"),
        api<{ surname: string; forenames: string; email: string; phone: string }>("applicant", "me"),
      ]);
      let restored: Record<string, string> = { surname: me.surname, forenames: me.forenames, email: me.email, phone: me.phone };
      if (draft) {
        restored = { ...restored, ...draft.data };
        setType(draft.type);
        setStep(draft.current_step);
        setDocuments(draft.documents);
        setNotice({ tone: "info", text: `Restored your application saved on ${new Date(draft.saved_at).toLocaleString("en-GB")}.` });
      }

      const reference = params.get("reference") || params.get("trxref") || restored.payment_reference;
      if (reference) {
        const payment = await api<{ paid: boolean; used: boolean }>("applicant", `payments/${encodeURIComponent(reference)}/verify`, { method: "POST" }).catch(() => null);
        if (payment?.paid && !payment.used) {
          restored.payment_reference = reference;
          setPaid(true);
          if (params.get("payment") === "callback") {
            setStep(6);
            setNotice({ tone: "success", text: "Payment confirmed. Now book your biometrics appointment." });
          }
        } else if (params.get("payment") === "callback") {
          setNotice({ tone: "danger", text: "We could not confirm your payment. If you were charged, contact support with your payment reference." });
        }
      }
      setData(restored);
      setLoaded(true);
    })();
  }, [params]);

  function validateStep(n: number): boolean {
    const missing: Record<string, string> = {};
    for (const f of STEP_FIELDS[n] ?? []) if (!data[f]?.trim()) missing[f] = "This field is required.";
    if (n === 1 && type === "RENEWAL" && !data.renewal_card_number?.trim()) missing.renewal_card_number = "Enter the card number you are renewing.";
    if (n === 4) {
      const absent = DOCUMENTS.filter((d) => d.required && !documents.some((x) => x.type === d.type));
      if (absent.length) missing.documents = `Please upload: ${absent.map((d) => d.label).join(", ")}.`;
    }
    if (n === 5 && !paid) missing.payment_reference = "Payment must be completed before you continue.";
    if (n === 6) for (const f of ["enrollment_center_id", "appointment_date", "appointment_time"]) if (!data[f]) missing[f] = "Required.";
    setErrors(missing);
    return Object.keys(missing).length === 0;
  }

  async function next() {
    if (!validateStep(step)) return;
    setBusy(true);
    try {
      await saveDraft(step + 1);
      setStep(step + 1);
      setNotice(null);
      window.scrollTo({ top: 0, behavior: "smooth" });
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

  async function uploadDocument(docType: string, file: File) {
    setBusy(true);
    setErrors({});
    try {
      await saveDraft(step);
      const result = await upload<{ draft: Draft }>("applicant", "draft/documents", { type: docType, file });
      setDocuments(result.draft.documents);
    } catch (e) {
      if (e instanceof ApiError) setErrors({ documents: Object.values(e.fieldErrors())[0] ?? e.message });
    } finally {
      setBusy(false);
    }
  }

  async function pay() {
    setBusy(true);
    try {
      const payment = await api<{ reference: string; authorization_url: string | null; fake: boolean }>("applicant", "payments", { method: "POST" });
      await saveDraft(5, { payment_reference: payment.reference });
      if (payment.fake) {
        const verified = await api<{ paid: boolean }>("applicant", `payments/${payment.reference}/verify`, { method: "POST" });
        setData((d) => ({ ...d, payment_reference: payment.reference }));
        setPaid(verified.paid);
        setNotice({ tone: "info", text: "Development mode: payment simulated." });
      } else if (payment.authorization_url) {
        window.location.href = payment.authorization_url; // Paystack hosted checkout
      }
    } catch (e) {
      setNotice({ tone: "danger", text: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  async function submit() {
    if (!validateStep(7) || data.declaration !== "1") {
      setErrors({ declaration: "You must accept the declaration." });
      return;
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
        setErrors(fieldErrors);
        const first = Math.min(...Object.keys(fieldErrors).map((f) => FIELD_STEP[f] ?? 7));
        setStep(first);
        setNotice({ tone: "danger", text: "Please correct the highlighted details." });
      } else {
        setNotice({ tone: "danger", text: (e as Error).message });
      }
    } finally {
      setBusy(false);
    }
  }

  if (!loaded) return <Spinner />;

  const props = { data, errors, set };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">{type === "RENEWAL" ? "Residence card renewal" : "Residence card application"}</h1>
        <p className="text-sm text-slate-600">Step {step} of 7 — {STEPS[step - 1]}</p>
        <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200" aria-hidden>
          <div className="h-full bg-nis-green transition-all" style={{ width: `${(step / 7) * 100}%` }} />
        </div>
      </div>

      {notice && <Alert tone={notice.tone}>{notice.text}</Alert>}

      <Panel title={STEPS[step - 1]}>
        {step === 1 && (
          <div className="space-y-5">
            {type === "RENEWAL" && (
              <Field label="Residence card number being renewed" required error={errors.renewal_card_number}>
                <Input value={data.renewal_card_number ?? ""} onChange={(e) => set("renewal_card_number", e.target.value)} />
              </Field>
            )}
            <PersonalFields {...props} />
          </div>
        )}
        {step === 2 && <PassportFields {...props} />}
        {step === 3 && <ContactFields {...props} />}
        {step === 4 && (
          <div className="space-y-4">
            <p className="text-sm text-slate-600">JPG, PNG or PDF, up to 5 MB each. Photographs must be JPG or PNG.</p>
            {errors.documents && <Alert tone="danger">{errors.documents}</Alert>}
            <ul className="divide-y divide-slate-100">
              {DOCUMENTS.map((d) => {
                const current = documents.find((x) => x.type === d.type);
                return (
                  <li key={d.type} className="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                      <div className="text-sm font-medium">{d.label}{d.required && <span className="text-red-600"> *</span>}</div>
                      <div className="text-xs text-slate-500">{current ? `✓ ${current.original_name}` : "Not uploaded"}</div>
                    </div>
                    <label className="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                      {current ? "Replace" : "Upload"}
                      <input
                        type="file"
                        accept={d.accept}
                        className="sr-only"
                        disabled={busy}
                        onChange={(e) => e.target.files?.[0] && uploadDocument(d.type, e.target.files[0])}
                      />
                    </label>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
        {step === 5 && (
          <div className="space-y-4">
            <Dl items={[["Residence card fee", naira(35000)], ["Payment reference", data.payment_reference ?? "—"]]} />
            {paid ? (
              <Alert tone="success">Payment confirmed.</Alert>
            ) : (
              <>
                {errors.payment_reference && <Alert tone="danger">{errors.payment_reference}</Alert>}
                <Button variant="gold" onClick={pay} disabled={busy}>Pay securely with Paystack</Button>
                <p className="text-xs text-slate-500">Your application is saved; you will return here after payment.</p>
              </>
            )}
          </div>
        )}
        {step === 6 && <AppointmentPicker {...props} />}
        {step === 7 && (
          <div className="space-y-5">
            <Dl
              items={[
                ["Name", `${data.surname} ${data.forenames}`],
                ["Nationality", data.nationality],
                ["Date of birth", nisDate(data.date_of_birth)],
                ["Passport", data.passport_number],
                ["Address", data.domicile],
                ["Documents", `${documents.length} uploaded`],
                ["Appointment", `${nisDate(data.appointment_date)} at ${data.appointment_time}`],
                ["Payment", paid ? "Confirmed" : "Not paid"],
              ]}
            />
            <label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
              <input type="checkbox" className="mt-1" checked={data.declaration === "1"} onChange={(e) => set("declaration", e.target.checked ? "1" : "")} />
              <span>
                I solemnly declare that the particulars given in this application are true and correct. I understand that giving false
                information is an offence under the Immigration Act 2015.
              </span>
            </label>
            {errors.declaration && <p className="text-sm text-red-700">{errors.declaration}</p>}
          </div>
        )}
      </Panel>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex gap-2">
          {step > 1 && <Button variant="secondary" onClick={() => setStep(step - 1)} disabled={busy}>Back</Button>}
          <Button variant="ghost" onClick={saveAndExit} disabled={busy}>Save &amp; exit</Button>
          <Button variant="ghost" onClick={discard} disabled={busy} className="text-red-700">Discard</Button>
        </div>
        {step < 7 ? (
          <Button onClick={next} disabled={busy}>{busy ? "Saving…" : "Save & continue"}</Button>
        ) : (
          <Button onClick={submit} disabled={busy}>{busy ? "Submitting…" : "Submit application"}</Button>
        )}
      </div>
      <p className="text-xs text-slate-500">Need help? <Link href="/track" className="underline">Track an existing application</Link>.</p>
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
