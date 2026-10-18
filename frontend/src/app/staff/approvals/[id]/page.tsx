"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { DocumentList } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira, nisDate, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";
import { useFetch } from "@/lib/use-fetch";

type ExternalCheck = {
  service: "INTERPOL_SLTD" | "MOI_QUOTA";
  status: string;
  summary: string;
  reference: string | null;
  details: Record<string, unknown> | null;
  checked_at: string;
  checked_by: string;
};

type Detail = {
  data: Application;
  photo_url: string | null;
  integration_checks: ExternalCheck[];
  payments: { reference: string; status: string; amount_kobo: number; verified_at: string | null }[];
};

export default function StaffApprovalDetailPage() {
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
  const isApprovingStaff = hasRole(user, "ApprovingOfficer", "SuperAdmin", "Admin");
  const isVetted = a.status === "VETTED";
  const canApprove = isApprovingStaff && isVetted;

  const lastQuery = [...(a.history ?? [])].reverse().find((h) => h.to === "QUERIED");
  const lastApplicantResponse = [...(a.history ?? [])].reverse().find((h) => h.notes?.startsWith("Applicant response:"));
  const hasRespondedToQuery = Boolean(lastQuery && lastApplicantResponse);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2 text-xs text-slate-500">
        <Link href="/staff/approvals" className="hover:text-nis-primary font-medium">← Back to Approvers</Link>
        <span>/</span>
        <span>Application {a.application_number}</span>
      </div>

      <PageTitle
        title={<>{a.application_number} <StatusBadge status={a.status} label={a.status === "VETTED" ? "Awaiting Approval" : a.status_label} /></>}
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
            {a.card && <Link href={`/staff/cards/${a.card.id}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Card {a.card.card_number}</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      {/* Stage 1 Vetting Outcome Banner */}
      {a.status === "VETTED" && (
        <div className="rounded-2xl border-2 border-emerald-300 bg-emerald-50/80 p-5 space-y-3 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200 pb-2">
            <div className="flex items-center gap-2">
              <span className="flex h-3 w-3 rounded-full bg-emerald-600" />
              <span className="text-xs font-bold uppercase tracking-wider text-emerald-950">
                Stage 1 Completed: Vetted by Vetting Officer — Ready for Final Approval
              </span>
            </div>
            {a.vetted_at && (
              <span className="text-xs font-medium text-emerald-800">
                Vetted on {dateTime(a.vetted_at)}
              </span>
            )}
          </div>
          <div className="grid gap-2 sm:grid-cols-2 text-sm text-slate-800">
            <div>
              <span className="font-semibold text-emerald-950">Vetting Officer:</span>{" "}
              {a.vetted_by ? `${a.vetted_by.fullname} (${a.vetted_by.service_number})` : "Immigration Vetting Officer"}
            </div>
            {a.vetted_at && (
              <div>
                <span className="font-semibold text-emerald-950">Vetting Timestamp:</span> {dateTime(a.vetted_at)}
              </div>
            )}
          </div>
          {a.vetting_notes && (
            <div className="rounded-xl border border-emerald-200 bg-white p-3.5 text-sm text-slate-900">
              <span className="font-bold text-nis-primary block mb-1">Vetting Verification Remarks:</span>
              <p className="whitespace-pre-wrap">{a.vetting_notes}</p>
            </div>
          )}
          <p className="text-xs text-slate-600">
            All submitted particulars and supporting documents have been examined and verified in Stage 1. As an Approving Officer, please review the complete application file below and make a final authorization decision.
          </p>
        </div>
      )}

      {a.status === "PENDING_APPROVAL" && (
        <Alert tone="warning">
          <div className="space-y-1">
            <div className="font-semibold text-sm">Awaiting Vetting (Stage 1 Incomplete)</div>
            <p className="text-xs text-slate-800">
              This application has not yet completed initial examination and verification by a Vetting Officer. Final approval cannot be granted until vetting is completed.
            </p>
          </div>
        </Alert>
      )}

      {a.status === "QUERIED" && (
        <Alert tone="warning">
          <div className="space-y-1">
            <div className="font-semibold text-sm">Application Currently Queried</div>
            <p className="text-xs text-slate-800">
              <strong>Query note:</strong> {a.decision_notes || "Action required"}
            </p>
            <p className="text-xs text-slate-600">
              This application has an outstanding query and cannot be approved until the query is resolved.
            </p>
          </div>
        </Alert>
      )}

      {hasRespondedToQuery && (
        <div className="rounded-2xl border-2 border-amber-300 bg-amber-50/70 p-5 space-y-3 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 pb-2">
            <div className="flex items-center gap-2">
              <span className="flex h-2.5 w-2.5 rounded-full bg-amber-600" />
              <span className="text-xs font-bold uppercase tracking-wider text-amber-900">
                Resolved Query History
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
          </div>
          <div className="rounded-xl border border-amber-200 bg-white p-3.5 text-sm text-slate-900">
            <span className="font-bold text-nis-primary block mb-1">Applicant&apos;s Query Response:</span>
            <p className="whitespace-pre-wrap">{lastApplicantResponse?.notes?.replace(/^Applicant response:\s*/, "")}</p>
          </div>
        </div>
      )}

      {/* External Checks (Interpol and MOI Quota) */}
      <ExternalChecks
        checks={detail.integration_checks}
        quota={a.quota_reference ?? null}
        canRun={hasRole(user, "ApprovingOfficer", "SuperAdmin")}
        busy={busy}
        onRun={() => act("integration-checks")}
      />

      {/* 1. Applicant Information */}
      <Panel title="Applicant Personal Information">
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

      {/* 2. Application & Appointment Information */}
      <Panel title="Application, Payment & Appointment Details">
        <Dl
          items={[
            ["Application Type", `${a.channel === "ASSISTED" ? "Assisted" : "Online"} ${applicationType(a, true)}`],
            ["Submission Date", dateTime(a.submitted_at)],
            ["Internal Vetting Outcome", a.vetted_at ? `Vetted on ${dateTime(a.vetted_at)} by ${a.vetted_by?.fullname || "Vetting Officer"}` : "Awaiting Vetting"],
            ["Current Status", a.status === "VETTED" ? "Vetted – Awaiting Approval" : (a.status_label || a.status)],
            ["Enrollment Center", a.enrollment_center?.name || "—"],
            ["Scheduled Appointment", a.appointment_date ? `${nisDate(a.appointment_date)} at ${a.appointment_time}` : "Not scheduled"],
            ["Fee & Payment", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
            ...detail.payments.map((p) => [`Payment Reference ${p.reference}`, `${p.status}${p.verified_at ? " (verified)" : ""}`] as [string, string]),
          ]}
        />
      </Panel>

      {/* 3. Supporting Documents */}
      <Panel
        title="Supporting Documents"
        subtitle="Inspect uploaded supporting documents thoroughly before granting final authorization."
      >
        <DocumentList scope="staff" applicationId={a.id} documents={a.documents ?? []} />
      </Panel>

      {/* 4. Approving Officer Final Decision Controls */}
      {isVetted && (
        <Panel
          title="Approving Officer Decision"
          subtitle="Final administrative authorization. Approving authorizes the applicant to attend biometrics and triggers their official approval notification."
        >
          {canApprove ? (
            <div className="space-y-4">
              <Field
                label="Decision Notes / Remarks"
                hint="Required when querying or rejecting. Optional for final approval notes."
              >
                <Textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="Enter approval remarks (or query instructions/rejection grounds)..."
                  rows={3}
                />
              </Field>

              <div className="flex flex-wrap items-center gap-3 pt-1">
                <Button
                  disabled={busy}
                  onClick={() =>
                    confirm("Grant final approval for this application? This will authorize the applicant for biometrics attendance and send the official approval notification.") &&
                    act("decision", {
                      decision: "APPROVE",
                      notes: notes.trim() || "Final approval granted for biometrics attendance.",
                    })
                  }
                >
                  Approve
                </Button>
                <Button
                  variant="gold"
                  disabled={busy || !notes.trim()}
                  onClick={() => act("decision", { decision: "QUERY", notes: notes.trim() })}
                >
                  Query
                </Button>
                <Button
                  variant="danger"
                  disabled={busy || !notes.trim()}
                  onClick={() =>
                    confirm("Reject this application permanently? This cannot be undone.") &&
                    act("decision", { decision: "REJECT", notes: notes.trim() })
                  }
                >
                  Reject
                </Button>
              </div>
            </div>
          ) : (
            <Alert tone="info">
              Only an authorized Approving Officer or Administrator can take final approval decisions.
            </Alert>
          )}
        </Panel>
      )}

      {/* Approved Status Audit Box */}
      {["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
        <div className="rounded-2xl border border-sky-200 bg-sky-50/70 p-5 space-y-2.5 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-sky-200 pb-2">
            <div className="flex items-center gap-2">
              <span className="flex h-2.5 w-2.5 rounded-full bg-sky-600" />
              <span className="text-xs font-bold uppercase tracking-wider text-sky-900">
                Application Approved — Authorized for Biometrics Attendance
              </span>
            </div>
            {a.decided_at && (
              <span className="text-xs font-medium text-sky-800">
                Approved: {dateTime(a.decided_at)}
              </span>
            )}
          </div>
          <div className="text-xs text-slate-700 space-y-1">
            {a.vetted_by && (
              <div>
                <span className="font-semibold text-slate-900">Vetted by:</span> {a.vetted_by.fullname} ({a.vetted_by.service_number})
                {a.vetted_at && <span className="text-slate-500"> on {dateTime(a.vetted_at)}</span>}
                {a.vetting_notes && <span className="italic text-slate-600"> — &quot;{a.vetting_notes}&quot;</span>}
              </div>
            )}
            {a.decided_by && (
              <div>
                <span className="font-semibold text-slate-900">Approved by:</span> {a.decided_by.fullname} ({a.decided_by.service_number})
                {a.decided_at && <span className="text-slate-500"> on {dateTime(a.decided_at)}</span>}
                {a.decision_notes && <span className="italic text-slate-600"> — &quot;{a.decision_notes}&quot;</span>}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Case Work Panel */}
      <WorkPanel
        applicationId={a.id}
        assignedTo={a.assigned_to ?? null}
        canAssign={hasRole(user, "ApprovingOfficer", "SuperAdmin")}
        onChanged={load}
      />
    </div>
  );
}

type Note = { id: number; body: string; at: string; author: string | null };
type Officer = { id: number; fullname: string; service_number: string; role: string };

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

const SERVICE: Record<ExternalCheck["service"], string> = {
  INTERPOL_SLTD: "Interpol stolen & lost travel documents",
  MOI_QUOTA: "Ministry of Interior expatriate quota",
};

const TONE: Record<string, string> = {
  CLEAR: "text-nis-primary font-medium",
  VALID: "text-nis-primary font-medium",
  HIT: "text-red-700 font-bold",
  INVALID: "text-red-700",
  NOT_FOUND: "text-red-700",
  ERROR: "text-nis-orange",
  DISABLED: "text-slate-500",
};

function ExternalChecks({
  checks,
  quota,
  canRun,
  busy,
  onRun,
}: {
  checks: ExternalCheck[];
  quota: string | null;
  canRun: boolean;
  busy: boolean;
  onRun: () => void;
}) {
  const rows: ExternalCheck["service"][] = ["INTERPOL_SLTD", ...(quota ? (["MOI_QUOTA"] as const) : [])];
  return (
    <Panel
      title="External Security & Agency Checks"
      actions={
        canRun && (
          <Button variant="secondary" className="!py-1.5" disabled={busy} onClick={onRun}>
            Run checks again
          </Button>
        )
      }
    >
      <ul className="divide-y divide-slate-100 text-sm">
        {rows.map((service) => {
          const c = checks.find((x) => x.service === service);
          return (
            <li key={service} className="flex flex-wrap items-start justify-between gap-2 py-2">
              <div>
                <div className="font-medium">
                  {SERVICE[service]}
                  {service === "MOI_QUOTA" && quota && <span className="font-normal text-slate-500"> · {quota}</span>}
                </div>
                <div className={c ? TONE[c.status] ?? "" : "text-slate-500"}>
                  {c ? c.summary : "Not checked yet."}
                </div>
              </div>
              {c && (
                <div className="text-right text-xs text-slate-500">
                  {c.status.replace("_", " ")}
                  {c.reference ? ` · ref ${c.reference}` : ""}
                  <br />
                  {c.checked_by} · {dateTime(c.checked_at)}
                </div>
              )}
            </li>
          );
        })}
      </ul>
      {!quota && (
        <p className="mt-2 text-xs text-slate-500">
          No expatriate quota number was given, so the quota was not checked.
        </p>
      )}
    </Panel>
  );
}
