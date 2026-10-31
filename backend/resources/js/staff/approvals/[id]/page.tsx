"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { DocumentList } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira, nisDate, applicationType, conciseApplicationType } from "@/lib/format";
import type { Application } from "@/lib/types";
import { useFetch } from "@/lib/use-fetch";
import { formatRelationship } from "@/lib/validation";

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

export default function StaffApprovalDetailPage({ id: propId }: { id?: string | number } = {}) {
  const params = useParams<{ id: string }>();
  const id = propId ? String(propId) : params?.id;
  const user = useStaff();

  const [detail, setDetail] = useState<Detail | null>(null);
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    if (!id) return Promise.reject(new Error("Application ID is required"));
    return api<Detail>("staff", `applications/${id}`).then(setDetail);
  }, [id]);

  useEffect(() => {
    if (id) {
      load().catch((e) => setError(e.message));
    } else {
      setError("No valid application ID was provided.");
    }
  }, [id, load]);

  async function act(path: string, body?: unknown) {
    if (!id) return;
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

  if (!id) {
    return (
      <div className="space-y-4 p-6">
        <div className="flex items-center gap-2 text-xs text-slate-500">
          <Link href="/staff/approvals" className="hover:text-nis-primary font-medium">← Back to Approvers</Link>
        </div>
        <Alert tone="danger">
          <div className="space-y-2">
            <div className="font-semibold text-base">Invalid or Missing Application Identifier</div>
            <p className="text-sm">No valid application identifier was provided in the URL or request.</p>
            <div className="pt-2">
              <Link
                href="/staff/approvals"
                className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              >
                Return to Approvers Queue
              </Link>
            </div>
          </div>
        </Alert>
      </div>
    );
  }

  if (!detail) {
    if (error) {
      return (
        <div className="space-y-4 p-6">
          <div className="flex items-center gap-2 text-xs text-slate-500">
            <Link href="/staff/approvals" className="hover:text-nis-primary font-medium">← Back to Approvers</Link>
          </div>
          <Alert tone="danger">
            <div className="space-y-2">
              <div className="font-semibold text-base">Application Not Found or Inaccessible</div>
              <p className="text-sm">{error}</p>
              <div className="pt-2">
                <Link
                  href="/staff/approvals"
                  className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Return to Approvers Queue
                </Link>
              </div>
            </div>
          </Alert>
        </div>
      );
    }
    return (
      <div className="flex min-h-[400px] flex-col items-center justify-center gap-3">
        <Spinner />
        <p className="text-xs text-slate-500">Loading application details for final approval review...</p>
      </div>
    );
  }

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
        title={
          <>
            {a.application_number}{" "}
            <StatusBadge
              status={a.approval_status ?? (["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) ? "APPROVED" : (a.status === "VETTED" ? "AWAITING_APPROVAL" : a.status))}
              label={a.approval_status_label ?? (["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) ? "Approved" : (a.status === "VETTED" ? "Awaiting Approval" : a.status_label))}
            />
          </>
        }
        subtitle={`${conciseApplicationType(a.type)} · submitted ${dateTime(a.submitted_at)}`}
        actions={
          <div className="flex flex-wrap gap-2">
            {["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
              <Link
                href={`/portal/applications/${a.id}/slip?kind=appointment&from=staff&return_to=${encodeURIComponent(`/staff/approvals/${a.id}`)}`}
                className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50 text-slate-800"
              >
                Appointment slip
              </Link>
            )}
            {["BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
              <Link href={`/portal/applications/${a.id}/slip?kind=temporary&from=staff&return_to=${encodeURIComponent(`/staff/approvals/${a.id}`)}`} className="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">Temporary slip</Link>
            )}
            {["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status) && (
              <Link
                href={`/staff/applications/${a.id}/biometrics`}
                className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#005028]"
              >
                Open biometrics desk
              </Link>
            )}
            {a.card && <Link href={`/staff/cards/${a.card.id}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Card {a.card.card_number}</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      {/* Stage 1 Vetting Outcome Banner */}
      {a.status === "VETTED" && (
        <div
          className="rounded-2xl p-5 space-y-3 shadow-xs"
          style={{ backgroundColor: "#F0F7F2", border: "1px solid #D4E8DA" }}
        >
          <div
            className="flex flex-wrap items-center justify-between gap-2 pb-2"
            style={{ borderBottom: "1px solid #D4E8DA" }}
          >
            <span
              className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
              style={{ backgroundColor: "#E1EFE6", color: "#246B45", border: "1px solid #D4E8DA" }}
            >
              Vetted – Awaiting Final Approval
            </span>
          </div>
          <div className="grid gap-2 sm:grid-cols-2 text-xs text-slate-800">
            <div>
              <span className="font-semibold text-slate-900">Vetted By:</span>{" "}
              {a.vetted_by ? `${a.vetted_by.fullname} (${a.vetted_by.service_number})` : "Immigration Vetting Officer"}
            </div>
            {a.vetted_at && (
              <div>
                <span className="font-semibold text-slate-900">Date/Time:</span> {dateTime(a.vetted_at)}
              </div>
            )}
          </div>
          {a.vetting_notes && (
            <div
              className="rounded-xl bg-white p-3.5 text-xs text-slate-800"
              style={{ border: "1px solid #D4E8DA" }}
            >
              <span className="font-semibold block mb-1" style={{ color: "#246B45" }}>
                Vetting Remarks:
              </span>
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

      {/* 1. Application Information */}
      <Panel title="Application Information">
        <Dl
          items={[
            ["Application Reference", a.application_number],
            ["Application Type", conciseApplicationType(a.type)],
            ["Category", a.category || "General"],
            ["Classification", `${conciseApplicationType(a.type)} Application`],
            ["Current Status", a.status === "VETTED" ? "Vetted – Awaiting Final Approval" : (a.status_label || a.status)],
            ["Submission Date", dateTime(a.submitted_at)],
            ["Enrollment Center", a.enrollment_center?.name || a.enrollment_center?.code || "—"],
            ["Scheduled Appointment", a.appointment_date ? `${nisDate(a.appointment_date)} at ${a.appointment_time}` : "Not scheduled"],
            ["Fee & Payment", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
            ...detail.payments.map((p) => [`Payment Ref ${p.reference}`, `${p.status}${p.verified_at ? " (verified)" : ""}`] as [string, string]),
          ]}
        />
      </Panel>

      {/* 2. Applicant Personal Information */}
      <Panel title="Applicant Personal Information">
        <div className="flex flex-col gap-6 sm:flex-row">
          <div className="shrink-0 space-y-2">
            {detail.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={detail.photo_url}
                alt="Applicant"
                className="h-48 w-40 rounded-xl border border-slate-200 bg-slate-50 object-cover shadow-sm"
              />
            ) : (
              <div className="flex h-48 w-40 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-400">
                No Photo Uploaded
              </div>
            )}
            <span className="block text-center text-xs font-medium text-slate-500">Applicant Photo</span>
          </div>
          <div className="min-w-0 flex-1">
            {(() => {
              const homeAddress = [
                a.home_country_address,
                (a as any).home_country_district,
                (a as any).home_country_city,
                (a as any).home_country_state,
                (a as any).home_country_postal_code,
                (a as any).home_country || a.nationality,
              ].filter(Boolean).join(", ");

              const homeContactAddress = [
                a.home_country_emergency_contact_address,
                (a as any).home_country_emergency_contact_district,
                (a as any).home_country_emergency_contact_city,
                (a as any).home_country_emergency_contact_state,
                (a as any).home_country_emergency_contact_postal_code,
                (a as any).home_country_emergency_contact_country || (a as any).home_country || a.nationality,
              ].filter(Boolean).join(", ");

              return (
                <Dl
                  items={[
                    ["Full Name", `${a.surname}, ${a.forenames}`],
                    ["Gender", a.sex],
                    ["Date of Birth", nisDate(a.date_of_birth)],
                    ["Place of Birth", a.place_of_birth],
                    ["Nationality", a.nationality],
                    ["Phone Number", a.phone],
                    ["E-mail Address", a.email],
                    ["Residential Address (Nigeria)", a.domicile ? `${a.domicile}${a.domicile_lga ? `, ${a.domicile_lga} LGA` : ""}${a.domicile_state ? `, ${a.domicile_state} State` : ""}` : "—"],
                    ...(homeAddress ? [["Residential Address (Home Country)", homeAddress] as [string, string]] : []),
                    ["Physical Features", `Height: ${a.height ?? "—"} · Complexion: ${a.complexion ?? "—"} · Eyes: ${a.eye_color ?? "—"} · Hair: ${a.hair_color ?? "—"}`],
                    ["Emergency Contact (Nigeria)", `${a.emergency_contact_name || "—"} (${formatRelationship(a.emergency_contact_relation) || "—"}) ${a.emergency_contact_phone || ""}${a.emergency_contact_address ? ` · ${a.emergency_contact_address}` : ""}`],
                    ...(a.home_country_emergency_contact_name || homeContactAddress ? [["Emergency Contact (Home Country)", `${a.home_country_emergency_contact_name || "—"} (${formatRelationship(a.home_country_emergency_contact_relation) || "—"}) ${a.home_country_emergency_contact_phone || ""}${homeContactAddress ? ` · ${homeContactAddress}` : ""}`] as [string, string]] : []),
                  ]}
                />
              );
            })()}
          </div>
        </div>
      </Panel>

      {/* Change of Data (COD) Requested Section */}
      {Boolean(a.has_cod) && (
        <Panel
          title="Change of Data (COD) Requested"
          subtitle="Applicant has requested formal modifications to their recorded particulars with supporting justification."
        >
          <div className="space-y-4">
            <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 font-medium">
              <svg className="h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
              <span>
                <strong>Change of Data Requested:</strong> Approving this application will automatically apply the approved proposed values to the official application record and log the action.
              </span>
            </div>

            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3">Field Name</th>
                    <th className="px-4 py-3">Original Recorded Value</th>
                    <th className="px-4 py-3">Proposed New Value</th>
                    <th className="px-4 py-3">Reason for Change</th>
                    <th className="px-4 py-3">Supporting Document</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 bg-white">
                  {Array.isArray(a.cod_changes) && a.cod_changes.length > 0 ? (
                    a.cod_changes.map((c, i) => (
                      <tr key={i} className="hover:bg-slate-50/50">
                        <td className="px-4 py-3 font-medium text-slate-900">{c.field_label || c.field}</td>
                        <td className="px-4 py-3 text-slate-500 font-mono text-xs">{c.original_value || "—"}</td>
                        <td className="px-4 py-3 font-semibold text-nis-primary font-mono text-xs">{c.requested_value}</td>
                        <td className="px-4 py-3 text-slate-700">{c.reason}</td>
                        <td className="px-4 py-3 text-slate-600">
                          {c.document_name ? (
                            <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-800">
                              📄 {c.document_name}
                            </span>
                          ) : (
                            <span className="text-xs text-slate-400">See uploaded docs</span>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="px-4 py-4 text-center text-slate-500 italic">
                        No specific field changes recorded.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </Panel>
      )}

      {/* 3. Passport & Travel Document Information */}
      <Panel title="Passport and Travel Document Information">
        <Dl
          items={[
            ["Travel Document Type", "National Passport / ECOWAS Travel Certificate"],
            ["Document / Passport Number", a.passport_number],
            ["Date of Issue", nisDate(a.passport_issued_date) || "—"],
            ["Expiry Date", `${nisDate(a.passport_expiry)}`],
            ["Place of Issue", a.passport_issue_place || "—"],
          ]}
        />
      </Panel>

      {/* 4. Application-Specific Information */}
      {(a.profession || a.employer_name || a.employer_address || a.quota_reference || a.category) && (
        <Panel title="Employment, Business & Institution Information">
          <Dl
            items={[
              ["Application Class / Category", a.category || "General"],
              ["Profession / Occupation", a.profession || "—"],
              ["Employer / Business / School", a.employer_name || "—"],
              ["Employer / Organization Address", a.employer_address || "—"],
              ...(a.quota_reference ? [["Expatriate Quota Reference", a.quota_reference] as [string, string]] : []),
            ]}
          />
        </Panel>
      )}

      {/* 5. Supporting Documents */}
      <Panel
        title="Supporting Documents"
      >
        <DocumentList scope="staff" applicationId={a.id} documents={a.documents ?? []} />
      </Panel>

      {/* 6. Approving Officer Final Decision Controls */}
      {isVetted && (
        <Panel
          title="Approving Officer Decision"
        >
          {canApprove ? (
            <div className="space-y-4">
              <Field
                label="Decision Remarks / Instructions"
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
                Application Approved — Authorized for Biometrics Attendance
              </span>
            </div>
            {a.decided_at && (
              <span
                className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                style={{ backgroundColor: "#E1EFE6", color: "#246B45", border: "1px solid #D4E8DA" }}
              >
                Approved: {dateTime(a.decided_at)}
              </span>
            )}
          </div>
          <div className="text-xs text-slate-800 space-y-1.5">
            {a.vetted_by && (
              <div>
                <span className="font-semibold text-slate-900">Vetted by:</span> {a.vetted_by.fullname} ({a.vetted_by.service_number})
                {a.vetted_at && <span className="text-slate-600"> on {dateTime(a.vetted_at)}</span>}
                {a.vetting_notes && <span className="italic text-slate-700"> — &quot;{a.vetting_notes}&quot;</span>}
              </div>
            )}
            {a.decided_by && (
              <div>
                <span className="font-semibold text-slate-900">Approved by:</span> {a.decided_by.fullname} ({a.decided_by.service_number})
                {a.decided_at && <span className="text-slate-600"> on {dateTime(a.decided_at)}</span>}
                {a.decision_notes && <span className="italic text-slate-700"> — &quot;{a.decision_notes}&quot;</span>}
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
