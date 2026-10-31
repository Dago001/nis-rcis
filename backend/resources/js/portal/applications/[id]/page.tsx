"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Tracker } from "@/components/Tracker";
import { Alert, Button, Field, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError, upload } from "@/lib/api-client";
import { nisDate, applicationType, dateTime } from "@/lib/format";
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

  async function uploadReplacement(typeToUpload: string, file: File) {
    setBusy(true);
    setError(null);
    setUploadSuccess(null);
    try {
      const r = await upload<{ data: Application }>("applicant", `applications/${id}/documents`, { type: typeToUpload, file });
      setApp(r.data);
      const docLabel = UPLOADABLE.find(([v]) => v === typeToUpload)?.[1] || typeToUpload;
      setUploadSuccess(`Uploaded replacement for "${docLabel}" successfully.`);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  async function reupload(file: File) {
    await uploadReplacement(docType, file);
  }

  async function respond() {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ data: Application }>("applicant", `applications/${id}/respond`, { method: "POST", json: { response } });
      setApp(r.data);
      setResponse("");
      setRespondSuccess("Your corrections and response have been submitted successfully and returned to the review queue.");
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
          <Tracker
            step={app.tracker_step}
            status={app.status}
            biometricsCompleted={Boolean(app.biometrics_captured_at || app.biometrics_status === "COMPLETED")}
          />
          {app.status === "APPROVED_FOR_BIOMETRICS" && (
            <Alert tone="success">
              {t("Attend biometrics capture on")} <strong>{nisDate(app.appointment_date)}</strong> {t("at")} <strong>{app.appointment_time}</strong>,{" "}
              {app.enrollment_center?.name}. {t("Bring your original passport and your printed appointment slip.")}
            </Alert>
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

      {app.status === "QUERIED" && (() => {
        const activeDocQueries = (app.document_queries ?? []).filter((q) => q.status !== "RESOLVED");
        const queriedDocTypes = new Set(activeDocQueries.map((q) => q.document_type));
        const unqueriedDocs = (app.documents ?? []).filter((d) => !queriedDocTypes.has(d.type));

        return (
          <div id="query-response">
            <Panel title="Action Required: Resolve Application Query">
              <div className="space-y-5">
                {/* Query Overview banner */}
                <div className="rounded-xl border border-amber-200 bg-amber-50/90 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200/60 pb-2 mb-2">
                    <span className="text-xs font-bold uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                      <span className="h-2 w-2 rounded-full bg-amber-600 animate-pulse" />
                      Official Application Query Notice
                    </span>
                    {queryEntry?.at && (
                      <span className="text-xs text-amber-800">
                        Issued: {dateTime(queryEntry.at)}
                      </span>
                    )}
                  </div>
                  <p className="text-sm font-medium text-slate-800">
                    {app.decision_notes || "One or more supporting documents require your correction or replacement. Please upload the requested document(s) below and submit your response."}
                  </p>
                </div>

                {error && <Alert tone="danger">{error}</Alert>}
                {uploadSuccess && <Alert tone="success">{uploadSuccess}</Alert>}

                {/* 1. Queried Documents Section */}
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-amber-900">
                      Queried Supporting Documents ({activeDocQueries.length})
                    </h3>
                    <span className="text-xs text-slate-500">Replacement document(s) required</span>
                  </div>

                  {activeDocQueries.length > 0 ? (
                    <div className="space-y-3">
                      {activeDocQueries.map((q) => {
                        const doc = app.documents?.find((d) => d.id === q.document_id || d.type === q.document_type);
                        const isSubmitted = q.status === "RESPONSE_SUBMITTED";
                        const docLabel = doc?.label || UPLOADABLE.find(([v]) => v === q.document_type)?.[1] || q.document_type;

                        return (
                          <div
                            key={q.id}
                            className="rounded-xl border border-amber-300 bg-amber-50/40 p-4 space-y-3 shadow-xs"
                          >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                              <div>
                                <div className="flex items-center gap-2">
                                  <span className="font-bold text-sm text-slate-900">{docLabel}</span>
                                  {doc?.version && doc.version > 1 && (
                                    <span className="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800">
                                      Current v{doc.version}
                                    </span>
                                  )}
                                </div>
                                <div className="text-xs text-slate-500 mt-0.5">
                                  Queried during <strong className="text-amber-800">{q.stage === "APPROVAL" ? "Approval Review" : "Vetting Review"}</strong> on {dateTime(q.queried_at)}
                                </div>
                              </div>

                              <div>
                                {isSubmitted ? (
                                  <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                                    ✓ Replacement Uploaded
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                    Awaiting Replacement
                                  </span>
                                )}
                              </div>
                            </div>

                            <div className="rounded-lg border border-amber-200 bg-white p-3 text-xs">
                              <span className="font-semibold text-amber-900">Query Reason / Instructions:</span>
                              <p className="mt-1 text-slate-800 leading-relaxed">{q.query_reason}</p>
                            </div>

                            {/* Upload action for this specific queried document */}
                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                              <label className="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3.5 py-2 text-xs font-bold text-amber-800 hover:bg-amber-50 shadow-xs transition-colors">
                                <svg className="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span>{isSubmitted ? "Upload Different Version" : "Choose & Upload Replacement File"}</span>
                                <input
                                  type="file"
                                  className="sr-only"
                                  accept="image/jpeg,image/png,application/pdf"
                                  disabled={busy}
                                  onChange={(e) => e.target.files?.[0] && uploadReplacement(q.document_type, e.target.files[0])}
                                />
                              </label>

                              {isSubmitted && (
                                <span className="text-xs text-emerald-700 font-medium">
                                  ✓ Ready for resubmission
                                </span>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    /* Fallback for legacy query without specific document queries */
                    <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                      <div className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        Replace or Re-Upload Requested Documents (Optional)
                      </div>
                      <p className="text-xs text-slate-500">
                        Select a document type and upload a replacement file. Your unaffected documents remain valid.
                      </p>
                      <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        <Field label="Select document type to replace">
                          <Select value={docType} onChange={(e) => { setDocType(e.target.value); setUploadSuccess(null); }}>
                            {UPLOADABLE.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                          </Select>
                        </Field>
                        <label className="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-slate-50 shadow-xs">
                          Choose &amp; Upload File
                          <input type="file" className="sr-only" accept="image/jpeg,image/png,application/pdf" disabled={busy} onChange={(e) => e.target.files?.[0] && uploadReplacement(docType, e.target.files[0])} />
                        </label>
                      </div>
                    </div>
                  )}
                </div>

                {/* 2. Unaffected Supporting Documents */}
                {unqueriedDocs.length > 0 && (
                  <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <div className="text-xs font-bold uppercase tracking-wider text-slate-700">
                      Accepted / Unaffected Documents ({unqueriedDocs.length})
                    </div>
                    <p className="text-xs text-slate-500">
                      The following documents were reviewed and do not require any replacement:
                    </p>
                    <ul className="divide-y divide-slate-100 text-xs">
                      {unqueriedDocs.map((doc) => (
                        <li key={doc.id} className="py-2 flex items-center justify-between">
                          <span className="text-slate-800 font-medium">{doc.label}</span>
                          <span className="inline-flex items-center gap-1 text-emerald-700 font-semibold text-[11px]">
                            ✓ Valid (No action needed)
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* 3. Written Explanation & Resubmission */}
                <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                  <div className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                    Applicant Explanation &amp; Resubmission
                  </div>
                  <Field
                    label="Your response to the reviewing officer (Optional)"
                    hint="Provide any additional explanation or context regarding the corrected documents."
                  >
                    <Textarea
                      value={response}
                      onChange={(e) => setResponse(e.target.value)}
                      placeholder="E.g., I have uploaded a clear, valid copy of the requested document as instructed..."
                      rows={3}
                    />
                  </Field>
                  <div className="pt-1">
                    <Button
                      onClick={respond}
                      disabled={busy}
                      className="w-full sm:w-auto"
                    >
                      {busy ? "Submitting Corrections..." : "Submit Corrections & Resubmit for Review"}
                    </Button>
                  </div>
                </div>
              </div>
            </Panel>
          </div>
        );
      })()}
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
