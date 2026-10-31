"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";
import { DocumentList, History } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Tracker } from "@/components/Tracker";
import { Alert, Button, Dl, Field, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate, applicationType, dateTime } from "@/lib/format";
import { useI18n } from "@/components/I18nProvider";
import type { Application } from "@/lib/types";

const UPLOADABLE = [
  ["photo", "Passport photograph"],
  ["passport_copy", "International passport data page / ECOWAS Travel Certificate"],
  ["quota_approval", "Expatriate quota approval"],
  ["domicile_proof", "Proof of Residence (Utility Bill / Tenancy Agreement)"],
  ["cac_certificate", "CAC Registration Document (Optional)"],
  ["admission_letter", "Institution Admission Letter"],
  ["acceptance_letter", "Acceptance Letter"],
  ["proof_of_payment", "Proof of Payment"],
  ["appointment_letter", "Appointment Letter / Contract Letter"],
  ["religious_invitation", "Appointment Letter / Contract Letter"],
  ["religious_registration", "Evidence of Host Organization Registration or Recognition"],
  ["additional", "Additional supporting document"],
];

function ApplicationDetail() {
  const { id } = useParams<{ id: string }>();
  const submitted = useSearchParams().get("submitted") === "1";
  const [app, setApp] = useState<Application | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [uploadSuccess, setUploadSuccess] = useState<string | null>(null);
  const [respondSuccess, setRespondSuccess] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [docType, setDocType] = useState("passport_copy");
  const [response, setResponse] = useState("");

  const load = useCallback(() => api<{ data: Application }>("applicant", `applications/${id}`).then((r) => setApp(r.data)), [id]);
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  async function reupload(file: File) {
    setBusy(true);
    setError(null);
    setUploadSuccess(null);
    try {
      const r = await upload<{ data: Application }>("applicant", `applications/${id}/documents`, { type: docType, file });
      setApp(r.data);
      const docLabel = UPLOADABLE.find(([v]) => v === docType)?.[1] || docType;
      setUploadSuccess(`Uploaded replacement for "${docLabel}" successfully. You can replace more documents or submit your response below.`);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  async function respond() {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ data: Application }>("applicant", `applications/${id}/respond`, { method: "POST", json: { response } });
      setApp(r.data);
      setResponse("");
      setRespondSuccess("Your query response has been submitted successfully and returned to the review queue. An immigration officer will re-vet your submission.");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (error && !app) return <Alert tone="danger">{error}</Alert>;
  if (!app) return <Spinner />;

  const { t } = useI18n();
  const appointmentReady = ["VETTED", "APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "APPROVED", "READY_FOR_COLLECTION", "ISSUED"].includes(app.status);
  const temporaryCardReady = Boolean(app.biometrics_captured_at) || ["BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(app.status);
  const queryEntry = [...(app.history ?? [])].reverse().find((h) => h.to === "QUERIED");

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant console")}
        title={<>{t("Application")} {app.application_number}</>}
        subtitle={<>{t("Reference")} {app.reference_number} · {applicationType(app, true)}</>}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href={`/portal/applications/${app.id}/slip`} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">{t("Application slip")}</Link>
            {appointmentReady && (
              <Link href={`/portal/applications/${app.id}/slip?kind=appointment`} className="rounded-lg bg-nis-green px-3 py-2 text-sm font-semibold text-white">{t("Appointment slip")}</Link>
            )}
            {temporaryCardReady && (
              <Link
                href={`/portal/applications/${app.id}/temporary-card`}
                className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800 shadow-xs hover:bg-emerald-100 transition"
              >
                <svg className="w-4 h-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                </svg>
                {t("Temporary Residence Card")}
              </Link>
            )}
          </div>
        }
      />

      {submitted && <Alert tone="success">{t("Your application has been submitted. We have e-mailed you a confirmation.")}</Alert>}
      {respondSuccess && <Alert tone="success">{respondSuccess}</Alert>}

      <FamilyPanel app={app} />

      <Panel title={<>{t("Status")} <StatusBadge status={app.status} label={app.status_label} /></>}>
        <div className="space-y-5">
          <Tracker step={app.tracker_step} status={app.status} />
          {app.status === "APPROVED_FOR_BIOMETRICS" && (
            <Alert tone="success">
              {t("Attend biometrics capture on")} <strong>{nisDate(app.appointment_date)}</strong> {t("at")} <strong>{app.appointment_time}</strong>,{" "}
              {app.enrollment_center?.name}. {t("Bring your original passport and your printed appointment slip.")}
            </Alert>
          )}
          {app.status === "BIOMETRICS_CAPTURED" && (
            <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-bold">✓</span>
                  <span className="text-sm font-bold text-emerald-900">
                    {t("Biometrics Successfully Completed")}
                  </span>
                </div>
                <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wider">
                  {t("Awaiting Card Production")}
                </span>
              </div>
              <p className="text-xs text-slate-700">
                {t("Your physical biometrics have been captured and verified. While your permanent ECOWAS Residence Card is in production, you can access, download and print your Temporary Residence Card below.")}
              </p>
              <div className="flex flex-wrap items-center gap-2 pt-1">
                <Link
                  href={`/portal/applications/${app.id}/temporary-card`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-800 transition"
                >
                  <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  {t("View Temporary Card")}
                </Link>
                <a
                  href={`/portal/applications/${app.id}/temporary-card/pdf`}
                  target="_blank"
                  rel="noopener noreferrer"
                  download={`NIS-temporary-card-${app.application_number}.pdf`}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                >
                  <svg className="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  {t("Download PDF")}
                </a>
                <Link
                  href={`/portal/applications/${app.id}/temporary-card`}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                >
                  <svg className="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                  </svg>
                  {t("Print Temporary Card")}
                </Link>
              </div>
            </div>
          )}
          {app.status === "READY_FOR_COLLECTION" && (
            <Alert tone="success">
              <strong>Your residence card is ready for collection</strong> at {app.enrollment_center?.name || "your designated centre"}. Please bring your original travel document (passport) and your application slip.
            </Alert>
          )}
          {app.status === "ISSUED" && (
            <Alert tone="success">
              ✓ <strong>Your residence card has been successfully issued and collected.</strong>
              {app.card?.card_number && <> Card Number: <strong>{app.card.card_number}</strong>.</>}
              {app.collected_at && <> Collected on {dateTime(app.collected_at)}.</>} Thank you.
            </Alert>
          )}
          {app.status === "REJECTED" && <Alert tone="danger">Reason: {app.decision_notes}</Alert>}
        </div>
      </Panel>

      {app.status === "QUERIED" && (
        <div id="query-response">
          <Panel title="Action Required: Respond to Application Query">
            <div className="space-y-4">
              <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200/60 pb-2 mb-2">
                  <span className="text-xs font-bold uppercase tracking-wider text-amber-900">
                    Query Instructions from Vetting Officer
                  </span>
                  {queryEntry?.at && (
                    <span className="text-xs text-amber-800">
                      Issued: {dateTime(queryEntry.at)}
                    </span>
                  )}
                </div>
                <p className="text-sm font-medium text-slate-800">
                  {app.decision_notes || "Please review and address the requested items below."}
                </p>
                <div className="pt-2">
                  <Link
                    href={`/portal/apply?query_app=${app.id}&step=4`}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white shadow-xs hover:bg-amber-700 transition"
                  >
                    Open Document Upload Section in Application Form →
                  </Link>
                </div>
              </div>

              {error && <Alert tone="danger">{error}</Alert>}
              {uploadSuccess && <Alert tone="success">{uploadSuccess}</Alert>}

              <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                  Step 1: Replace or Re-Upload Requested Documents (Optional)
                </div>
                <p className="text-xs text-slate-500">
                  If the officer requested a clearer or corrected document, select the document type and upload it here. Your unaffected documents remain valid.
                </p>
                <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                  <Field label="Select document type to replace">
                    <Select value={docType} onChange={(e) => { setDocType(e.target.value); setUploadSuccess(null); }}>
                      {UPLOADABLE.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </Select>
                  </Field>
                  <label className="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-slate-50 shadow-xs">
                    Choose &amp; Upload File
                    <input type="file" className="sr-only" accept="image/jpeg,image/png,application/pdf" disabled={busy} onChange={(e) => e.target.files?.[0] && reupload(e.target.files[0])} />
                  </label>
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                  Step 2: Written Explanation &amp; Resubmission (Compulsory)
                </div>
                <Field label="Your response to the officer" required hint="Provide a clear explanation of what was corrected or any additional context for the officer.">
                  <Textarea
                    value={response}
                    onChange={(e) => setResponse(e.target.value)}
                    placeholder="E.g., I have re-uploaded a clear copy of my passport data page as requested..."
                  />
                </Field>
                <Button onClick={respond} disabled={busy || response.trim().length < 3}>
                  Submit Response &amp; Resubmit for Approval
                </Button>
              </div>
            </div>
          </Panel>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Panel title="Particulars">
          <Dl
            items={[
              ["Name", `${app.surname}, ${app.forenames}`],
              ["Nationality", app.nationality],
              ["Date of birth", nisDate(app.date_of_birth)],
              ["Passport", app.passport_number],
              ["Address", app.domicile],
              ["Fee", `${naira(app.fee_amount_naira)} (${app.payment_status})`],
            ]}
          />
        </Panel>
        <Panel title="Documents">
          <DocumentList scope="applicant" applicationId={app.id} documents={app.documents ?? []} />
        </Panel>
      </div>

      <Panel title="History">{app.history && <History history={app.history} />}</Panel>
    </div>
  );
}

export default function Page() {
  return (
    <Suspense fallback={<Spinner />}>
      <ApplicationDetail />
    </Suspense>
  );
}

/** Spouse and children applying on the same account, linked to the account holder's own application. */
function FamilyPanel({ app }: { app: Application }) {
  const { t } = useI18n();
  if (app.principal) {
    return (
      <Alert tone="info">
        {t("This application is for your {relationship}, linked to your application", {
          relationship: app.dependant_relationship === "CHILD" ? t("child") : t("spouse"),
        })}{" "}
        <Link href={`/portal/applications/${app.principal.id}`} className="font-semibold underline">{app.principal.application_number}</Link>.
      </Alert>
    );
  }
  if (app.status === "REJECTED" || app.status === "DRAFT") return null;
  return (
    <Panel title={t("Family members (dependants)")}>
      <div className="space-y-4 text-sm">
        <p className="text-slate-600">{t("Your spouse and children can apply from this account. Each dependant needs their own passport, photograph and fee, and is linked to this application.")}</p>
        {app.dependants && app.dependants.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {app.dependants.map((d) => (
              <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>
                  <Link href={`/portal/applications/${d.id}`} className="font-medium text-nis-primary hover:underline">{d.application_number}</Link>{" "}
                  — {d.surname}, {d.forenames} ({d.relationship === "CHILD" ? t("child") : t("spouse")})
                </span>
                <StatusBadge status={d.status} />
              </li>
            ))}
          </ul>
        )}
        <div className="flex flex-wrap gap-2">
          <Link href={`/portal/apply?for=spouse&principal=${app.id}`} className="rounded-md border border-slate-300 bg-white px-4 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">{t("Apply for my spouse")}</Link>
          <Link href={`/portal/apply?for=child&principal=${app.id}`} className="rounded-md border border-slate-300 bg-white px-4 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">{t("Apply for my child")}</Link>
        </div>
      </div>
    </Panel>
  );
}
