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
  const isVettingStaff = hasRole(user, "VettingOfficer", "SuperAdmin", "Admin");
  const isApprovingStaff = hasRole(user, "ApprovingOfficer", "SuperAdmin", "Admin");

  const canVet = isVettingStaff && a.status === "PENDING_APPROVAL";
  const canApprove = isApprovingStaff && a.status === "VETTED";
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
            {["VETTED", "APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
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
              This application has been returned to the applicant and is awaiting their response or document replacements. It will automatically reappear in the vetting queue once the applicant responds.
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
            Please cross-check any re-uploaded supporting documents above. When satisfied, click &ldquo;Vet&rdquo; to forward the application to the Approvers queue. If further corrections are required, you may query the applicant again with reasons.
          </p>
        </div>
      )}

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

      {/* Stage 1: Vetting Review and Controls */}
      {a.status === "PENDING_APPROVAL" && (
        <>
          {canVet ? (
            <Panel
              title="Vetting"
              subtitle="Review all applicant particulars, security checks and supporting documents above before taking action."
            >
              <div className="space-y-4">
                <Field
                  label="Notes / Reason for Query or Rejection"
                  hint="Required when querying or rejecting. Optional recommendation notes for vetting."
                >
                  <Textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Enter query instructions (e.g. Please upload a clear copy of your passport data page), rejection grounds, or vetting remarks..."
                    rows={3}
                  />
                </Field>

                <div className="flex flex-wrap items-center gap-3 pt-1">
                  <Button
                    disabled={busy}
                    onClick={() =>
                      confirm("Vet this application? This confirms that submitted details and supporting documents have been verified. The application will be forwarded to the Approvers queue.") &&
                      act("decision", {
                        decision: "VET",
                        notes: notes.trim() || "Application particulars and documents verified by Vetting Officer.",
                      })
                    }
                  >
                    Vet
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
            </Panel>
          ) : isApprovingStaff ? (
            <Alert tone="info">
              <div className="space-y-1">
                <div className="font-semibold text-sm">Awaiting Vetting</div>
                <p className="text-xs text-slate-800">
                  This application has not yet been vetted by a Vetting Officer. All applications must be examined and vetted before an Approving Officer can grant final approval.
                </p>
              </div>
            </Alert>
          ) : null}
        </>
      )}

      {/* Stage 2: Approver Review and Controls */}
      {a.status === "VETTED" && (
        <div className="space-y-5">
          <div
            className="rounded-2xl p-5 space-y-3 shadow-xs"
            style={{ backgroundColor: "#F0F7F2", border: "1px solid #D4E8DA" }}
          >
            <div
              className="flex flex-wrap items-center justify-between gap-2 pb-2"
              style={{ borderBottom: "1px solid #D4E8DA" }}
            >
              <div className="flex items-center gap-2">
                <span className="flex h-2.5 w-2.5 rounded-full" style={{ backgroundColor: "#246B45" }} />
                <span className="text-xs font-bold uppercase tracking-wider" style={{ color: "#246B45" }}>
                  Stage One Completed
                </span>
              </div>
              <span
                className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                style={{ backgroundColor: "#E1EFE6", color: "#246B45", border: "1px solid #D4E8DA" }}
              >
                Vetted – Awaiting Final Approval
              </span>
            </div>
            <div className="grid gap-2 sm:grid-cols-2 text-xs text-slate-700">
              {a.vetted_by && (
                <div>
                  <span className="font-semibold text-slate-900">Vetted By:</span> {a.vetted_by.fullname} ({a.vetted_by.service_number})
                </div>
              )}
              {a.vetted_at && (
                <div>
                  <span className="font-semibold text-slate-900">Date/Time:</span> {dateTime(a.vetted_at)}
                </div>
              )}
            </div>
            {a.vetting_notes && (
              <div
                className="rounded-xl bg-white p-3 text-xs text-slate-800"
                style={{ border: "1px solid #D4E8DA" }}
              >
                <span className="font-semibold block mb-0.5" style={{ color: "#246B45" }}>Vetting Remarks:</span>
                <p className="whitespace-pre-wrap">{a.vetting_notes}</p>
              </div>
            )}
            <p className="text-xs text-slate-600">
              This application has passed internal vetting and is currently in the Approvers queue. Final approval must be performed by an Approving Officer under the Approvers section. Biometrics attendance is not yet authorized.
            </p>
            <div className="pt-2">
              <Link
                href={`/staff/approvals/${a.id}`}
                className="inline-flex items-center justify-center rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-800 transition-colors"
              >
                Open in Approvals for Final Review →
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Stage 3: Approved Status Audit Box */}
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

      {canCollect && (
        <Alert tone="success">
          The card is ready. Verify the holder&apos;s original passport, then{" "}
          <button className="font-semibold underline" disabled={busy} onClick={() => act("collect")}>record collection</button>.
        </Alert>
      )}
    </div>
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
