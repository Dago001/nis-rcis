"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { DocumentList, History } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira, nisDate, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";
import { useFetch } from "@/lib/use-fetch";

type ExternalCheck = { service: "INTERPOL_SLTD" | "MOI_QUOTA"; status: string; summary: string; reference: string | null; details: Record<string, unknown> | null; checked_at: string; checked_by: string };
type Detail = { data: Application; photo_url: string | null; integration_checks: ExternalCheck[]; payments: { reference: string; status: string; amount_kobo: number; verified_at: string | null }[] };

export default function StaffApplicationPage() {
  const { id } = useParams<{ id: string }>();
  const user = useStaff();
  const [detail, setDetail] = useState<Detail | null>(null);
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => api<Detail>("staff", `applications/${id}`).then(setDetail), [id]);
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  async function act(path: string, body?: unknown) {
    setBusy(true);
    setError(null);
    try {
      await api("staff", `applications/${id}/${path}`, { method: "POST", json: body ?? {} });
      setNotes("");
      await load();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  if (!detail) return error ? <Alert tone="danger">{error}</Alert> : <Spinner />;
  const a = detail.data;
  const canDecide = hasRole(user, "VettingOfficer", "ApprovingOfficer", "SuperAdmin", "Admin") && a.status === "PENDING_APPROVAL";
  const canCapture = hasRole(user, "EnrollmentOfficer", "ApprovingOfficer", "IssuingOfficer", "SuperAdmin") && a.status === "APPROVED_FOR_BIOMETRICS";
  const canCollect = hasRole(user, "ApprovingOfficer", "IssuingOfficer", "SuperAdmin") && a.status === "READY_FOR_COLLECTION";

  const lastQuery = [...(a.history ?? [])].reverse().find((h) => h.to === "QUERIED");
  const lastApplicantResponse = [...(a.history ?? [])].reverse().find((h) => h.notes?.startsWith("Applicant response:"));
  const hasRespondedToQuery = a.status === "PENDING_APPROVAL" && Boolean(lastQuery && lastApplicantResponse);

  return (
    <div className="space-y-6">
      <PageTitle
        title={<>{a.application_number} <StatusBadge status={a.status} label={a.status_label} /></>}
        subtitle={`${a.channel === "ASSISTED" ? "Assisted" : "Online"} ${applicationType(a, true).toLowerCase()} · submitted ${dateTime(a.submitted_at)}`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href={`/portal/applications/${a.id}/slip`} target="_blank" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50">Application slip</Link>
            {["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
              <Link href={`/portal/applications/${a.id}/slip?kind=appointment`} target="_blank" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50">Appointment slip</Link>
            )}
            {["BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
              <Link href={`/portal/applications/${a.id}/slip?kind=temporary`} target="_blank" className="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">Temporary slip</Link>
            )}
            {canCapture && <Link href={`/staff/applications/${a.id}/biometrics`} className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white">Open biometrics desk</Link>}
            {a.card && <Link href={`/staff/cards/${a.card.id}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Card {a.card.card_number}</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      {a.status === "QUERIED" && (
        <Alert tone="warning">
          <div className="space-y-1">
            <div className="font-semibold text-sm">Application Currently Queried</div>
            <p className="text-xs text-slate-800">
              <strong>Query instructions issued to applicant:</strong> {a.decision_notes || "Action required"}
            </p>
            <p className="text-xs text-slate-600">
              This application has been returned to the applicant and is awaiting their response or document replacements. It will automatically reappear in the approval queue once the applicant responds.
            </p>
          </div>
        </Alert>
      )}

      {hasRespondedToQuery && (
        <div className="rounded-2xl border-2 border-amber-300 bg-amber-50/70 p-5 space-y-3 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 pb-2">
            <div className="flex items-center gap-2">
              <span className="flex h-2.5 w-2.5 rounded-full bg-amber-600 animate-pulse" />
              <span className="text-xs font-bold uppercase tracking-wider text-amber-900">
                Applicant Query Response — Re-Vetting Required
              </span>
            </div>
            {lastApplicantResponse?.at && (
              <span className="text-xs font-medium text-amber-800">
                Responded: {dateTime(lastApplicantResponse.at)}
              </span>
            )}
          </div>
          <div className="text-xs text-slate-700">
            <span className="font-semibold text-amber-950">Original Officer Query:</span>{" "}
            <span className="italic">{lastQuery?.notes || a.decision_notes || "Action required"}</span>
            {lastQuery?.at && <span className="ml-1 text-slate-500">({dateTime(lastQuery.at)})</span>}
          </div>
          <div className="rounded-xl border border-amber-200 bg-white p-3.5 text-sm text-slate-900">
            <span className="font-bold text-nis-primary block mb-1">Applicant&apos;s Response:</span>
            <p className="whitespace-pre-wrap">{lastApplicantResponse?.notes?.replace(/^Applicant response:\s*/, "")}</p>
          </div>
          <p className="text-xs text-slate-500">
            Please cross-check any re-uploaded supporting documents above. When satisfied, click &ldquo;Approve for Biometrics&rdquo;. If further corrections are required, you may query the applicant again with reasons.
          </p>
        </div>
      )}

      <RiskPanel flags={a.risk_flags ?? []} busy={busy} onRecheck={() => act("risk-check")} />

      <ExternalChecks checks={detail.integration_checks} quota={a.quota_reference ?? null}
        canRun={hasRole(user, "VettingOfficer", "ApprovingOfficer", "SuperAdmin")} busy={busy} onRun={() => act("integration-checks")} />

      {a.sla && (
        <Alert tone={a.sla.overdue ? "danger" : "info"}>
          Waiting <strong>{a.sla.working_days} working day{a.sla.working_days === 1 ? "" : "s"}</strong> since submission. Target: a decision within {a.sla.target} working days{a.sla.overdue ? " — this application is overdue." : "."}
        </Alert>
      )}

      <WorkPanel applicationId={a.id} assignedTo={a.assigned_to ?? null} canAssign={hasRole(user, "VettingOfficer", "ApprovingOfficer", "IssuingOfficer", "SuperAdmin")} onChanged={load} />

      {a.principal && (
        <Alert tone="info">
          Dependant ({a.dependant_relationship === "CHILD" ? "child" : "spouse"}) of{" "}
          <Link href={`/staff/applications/${a.principal.id}`} className="font-semibold underline">{a.principal.application_number}</Link> — {a.principal.surname}, {a.principal.forenames}.
        </Alert>
      )}
      {a.dependants && a.dependants.length > 0 && (
        <Alert tone="info">
          Dependants on this application:{" "}
          {a.dependants.map((d, i) => (
            <span key={d.id}>
              {i > 0 && ", "}
              <Link href={`/staff/applications/${d.id}`} className="font-semibold underline">{d.application_number}</Link> ({d.surname}, {d.forenames}, {d.relationship === "CHILD" ? "child" : "spouse"})
            </span>
          ))}
        </Alert>
      )}

      {/* A. Applicant Information */}
      <Panel title="Applicant Information">
        <div className="flex flex-col gap-6 sm:flex-row">
          {detail.photo_url && (
            <div className="shrink-0 space-y-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={detail.photo_url}
                alt="Applicant"
                className="h-48 w-40 rounded-xl border border-slate-200 bg-slate-50 object-cover shadow-sm"
              />
              <span className="block text-center text-xs font-medium text-slate-500">Applicant Photo</span>
            </div>
          )}
          <div className="min-w-0 flex-1">
            <Dl
              items={[
                ["Full Name", `${a.surname}, ${a.forenames}`],
                ["Application Ref.", a.application_number],
                ["Category / Type", `${a.category || "General"} · ${applicationType(a, true)}`],
                ["Nationality", a.nationality],
                ["Sex", a.sex],
                ["Date of Birth", nisDate(a.date_of_birth)],
                ["Place of Birth", a.place_of_birth],
                ["Passport / Travel Doc", `${a.passport_number} (expires ${nisDate(a.passport_expiry)})`],
                ["Profession / Employer", `${a.profession || "—"}${a.employer_name ? ` · ${a.employer_name}` : ""}${a.quota_reference ? ` (Quota Ref: ${a.quota_reference})` : ""}`],
                ["Phone", a.phone],
                ["E-mail", a.email],
                ["Residential Address", a.domicile],
                ["Physical Features", `Height: ${a.height ?? "—"} · Complexion: ${a.complexion ?? "—"} · Eyes: ${a.eye_color ?? "—"} · Hair: ${a.hair_color ?? "—"}`],
                ["Emergency Contact", `${a.emergency_contact_name || "—"} (${a.emergency_contact_relation || "—"}) ${a.emergency_contact_phone || ""}`],
              ]}
            />
          </div>
        </div>
      </Panel>

      {/* B. Application Information */}
      <Panel title="Application & Appointment Information">
        <Dl
          items={[
            ["Application Type", `${a.channel === "ASSISTED" ? "Assisted" : "Online"} ${applicationType(a, true)}`],
            ["Submission Date", dateTime(a.submitted_at)],
            ["Current Status", a.status_label || a.status],
            ["Enrollment Center", a.enrollment_center?.name || "—"],
            ["Appointment", a.appointment_date ? `${nisDate(a.appointment_date)} at ${a.appointment_time}` : "Not scheduled"],
            ["Fee & Payment", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
            ...detail.payments.map((p) => [`Payment ${p.reference}`, `${p.status}${p.verified_at ? " (verified)" : ""}`] as [string, string]),
          ]}
        />
      </Panel>

      {/* C. Supporting Documents */}
      <Panel title="Supporting Documents" subtitle="Review uploaded supporting documents, identify requirements, and verify validity.">
        <DocumentList scope="staff" applicationId={a.id} documents={a.documents ?? []} />
      </Panel>

      {/* Workflow History */}
      <Panel title="Workflow history">{a.history && <History history={a.history} />}</Panel>

      {/* D. Vetting and Approval Controls */}
      {canDecide && (
        <Panel
          title="Vetting & Decision"
          subtitle="Review all applicant particulars and supporting documents above before making a decision."
        >
          <div className="space-y-4">
            <Field
              label="Notes / Reason for Query or Rejection"
              hint="Required when querying or rejecting. The applicant receives this message by email and on their portal notification bell."
            >
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Enter query instructions (e.g. Please upload a clear, legible copy of your passport data page) or rejection grounds..."
                rows={3}
              />
            </Field>

            <div className="grid gap-3 sm:grid-cols-3">
              {/* Option 1: Approve for Biometrics */}
              <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3 flex flex-col justify-between shadow-xs">
                <div>
                  <div className="font-semibold text-sm text-slate-900">Approve Application</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Application and documents are verified and vetted. Clears applicant for physical biometrics capture.
                  </p>
                </div>
                <Button
                  disabled={busy}
                  onClick={() => confirm("Approve this application for physical biometrics capture?") && act("decision", { decision: "APPROVE", notes: notes.trim() || "Application verified and vetted by immigration vetting officer." })}
                  className="w-full justify-center"
                >
                  ✓ Approve for Biometrics
                </Button>
              </div>

              {/* Option 2: Query Application */}
              <div className="rounded-xl border border-amber-200 bg-amber-50/50 p-4 space-y-3 flex flex-col justify-between shadow-xs">
                <div>
                  <div className="font-semibold text-sm text-amber-950">Query Application</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Requires correction or document replacement from the applicant. Moves out of approval queue until answered.
                  </p>
                </div>
                <Button
                  variant="gold"
                  disabled={busy || !notes.trim()}
                  onClick={() => act("decision", { decision: "QUERY", notes: notes.trim() })}
                  className="w-full justify-center"
                >
                  Query Applicant
                </Button>
              </div>

              {/* Option 3: Reject Application */}
              <div className="rounded-xl border border-red-200 bg-red-50/50 p-4 space-y-3 flex flex-col justify-between shadow-xs">
                <div>
                  <div className="font-semibold text-sm text-red-950">Reject Application</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Application is ineligible or rejected permanently. This cannot be undone.
                  </p>
                </div>
                <Button
                  variant="danger"
                  disabled={busy || !notes.trim()}
                  onClick={() => confirm("Reject this application permanently? This cannot be undone.") && act("decision", { decision: "REJECT", notes: notes.trim() })}
                  className="w-full justify-center"
                >
                  Reject Application
                </Button>
              </div>
            </div>
          </div>
        </Panel>
      )}

      {canCollect && (
        <Alert tone="success">
          The card is ready. Verify the holder&apos;s original passport, then{" "}
          <button className="font-semibold underline" disabled={busy} onClick={() => act("collect")}>record collection</button>.
        </Alert>
      )}
    </div>
  );
}

type RiskFlag = { code: string; severity: "HIGH" | "MEDIUM"; message: string; related: string[] };

/** Fraud and duplicate checks (advisory; they never decide on their own). */
function RiskPanel({ flags, busy, onRecheck }: { flags: RiskFlag[]; busy: boolean; onRecheck: () => void }) {
  return (
    <section className={`rounded-2xl border p-5 ${flags.length ? "border-red-200 bg-red-50" : "border-slate-200 bg-white"}`} aria-label="Fraud and duplicate checks">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-[15px] font-medium text-slate-900">{flags.length ? `⚠ ${flags.length} fraud / duplicate warning${flags.length > 1 ? "s" : ""}` : "✓ No fraud or duplicate warnings"}</h2>
        <Button variant="secondary" disabled={busy} onClick={onRecheck}>Re-run checks</Button>
      </div>
      {flags.length > 0 && (
        <ul className="mt-3 space-y-2 text-sm">
          {flags.map((f) => (
            <li key={f.code} className="flex gap-2">
              <span className={`mt-0.5 shrink-0 rounded px-1.5 text-[11px] font-semibold ${f.severity === "HIGH" ? "bg-nis-red text-white" : "bg-amber-200 text-amber-900"}`}>{f.severity}</span>
              <span>{f.message}{f.related.length > 0 && <span className="text-slate-600"> Related: {f.related.join(", ")}</span>}</span>
            </li>
          ))}
        </ul>
      )}
      <p className="mt-2 text-xs text-slate-500">Warnings are for the officer&apos;s judgement; verify the documents before deciding.</p>
    </section>
  );
}

type Note = { id: number; body: string; at: string; author: string | null };
type Officer = { id: number; fullname: string; service_number: string; role: string };

/** Assignment and internal notes. Notes are for officers only; the applicant never sees them. */
function WorkPanel({ applicationId, assignedTo, canAssign, onChanged }: { applicationId: number; assignedTo: Application["assigned_to"] | null; canAssign: boolean; onChanged: () => Promise<unknown> }) {
  const notes = useFetch<{ data: Note[] }>("staff", `applications/${applicationId}/notes`);
  const officers = useFetch<{ data: Officer[] }>("staff", canAssign ? "assignees" : null);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run(fn: () => Promise<unknown>) {
    setBusy(true);
    setError(null);
    try {
      await fn();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Panel title="Case work (staff only)">
      <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
        <div className="space-y-2 text-sm">
          <div className="text-xs font-medium uppercase tracking-wide text-slate-500">Assigned to</div>
          {canAssign ? (
            <Select
              aria-label="Assign to officer"
              value={assignedTo?.id ?? ""}
              disabled={busy || !officers.data}
              onChange={(e) => run(async () => {
                await api("staff", `applications/${applicationId}/assign`, { method: "POST", json: { user_id: e.target.value ? Number(e.target.value) : null } });
                await onChanged();
              })}
            >
              <option value="">Nobody</option>
              {officers.data?.data.map((o) => <option key={o.id} value={o.id}>{o.fullname} ({o.service_number})</option>)}
            </Select>
          ) : (
            <p>{assignedTo ? `${assignedTo.fullname} (${assignedTo.service_number})` : "Nobody"}</p>
          )}
          <p className="text-xs text-slate-500">The officer is told by e-mail.</p>
        </div>
        <div className="space-y-3">
          <div className="text-xs font-medium uppercase tracking-wide text-slate-500">Internal notes</div>
          {error && <Alert tone="danger">{error}</Alert>}
          <Textarea value={body} onChange={(e) => setBody(e.target.value)} placeholder="Visible to NIS staff only — never to the applicant" rows={2} maxLength={2000} aria-label="New internal note" />
          <Button variant="secondary" disabled={busy || !body.trim()} onClick={() => run(async () => {
            await api("staff", `applications/${applicationId}/notes`, { method: "POST", json: { body } });
            setBody("");
            notes.reload();
          })}>Add note</Button>
          <ul className="space-y-2 text-sm">
            {notes.data?.data.map((n) => (
              <li key={n.id} className="rounded-lg bg-slate-50 px-3 py-2">
                <p className="whitespace-pre-wrap">{n.body}</p>
                <p className="mt-1 text-xs text-slate-500">{n.author} · {dateTime(n.at)}</p>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </Panel>
  );
}

const SERVICE: Record<ExternalCheck["service"], string> = { INTERPOL_SLTD: "Interpol stolen & lost travel documents", MOI_QUOTA: "Ministry of Interior expatriate quota" };
const TONE: Record<string, string> = {
  CLEAR: "text-nis-primary", VALID: "text-nis-primary", HIT: "text-red-700 font-semibold", INVALID: "text-red-700", NOT_FOUND: "text-red-700",
  ERROR: "text-nis-orange", DISABLED: "text-slate-500",
};

/** Answers from other government systems. Advisory: the officer decides. */
function ExternalChecks({ checks, quota, canRun, busy, onRun }: { checks: ExternalCheck[]; quota: string | null; canRun: boolean; busy: boolean; onRun: () => void }) {
  const rows: ExternalCheck["service"][] = ["INTERPOL_SLTD", ...(quota ? (["MOI_QUOTA"] as const) : [])];
  return (
    <Panel title="External checks" actions={canRun && <Button variant="secondary" className="!py-1.5" disabled={busy} onClick={onRun}>Run checks again</Button>}>
      <ul className="divide-y divide-slate-100 text-sm">
        {rows.map((service) => {
          const c = checks.find((x) => x.service === service);
          return (
            <li key={service} className="flex flex-wrap items-start justify-between gap-2 py-2">
              <div>
                <div className="font-medium">{SERVICE[service]}{service === "MOI_QUOTA" && quota && <span className="font-normal text-slate-500"> · {quota}</span>}</div>
                <div className={c ? TONE[c.status] ?? "" : "text-slate-500"}>{c ? c.summary : "Not checked yet."}</div>
              </div>
              {c && <div className="text-right text-xs text-slate-500">{c.status.replace("_", " ")}{c.reference ? ` · ref ${c.reference}` : ""}<br />{c.checked_by} · {dateTime(c.checked_at)}</div>}
            </li>
          );
        })}
      </ul>
      {!quota && <p className="mt-2 text-xs text-slate-500">No expatriate quota number was given, so the quota was not checked.</p>}
    </Panel>
  );
}
