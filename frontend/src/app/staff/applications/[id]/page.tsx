"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { DocumentList, History } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira, nisDate, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";

type Detail = { data: Application; photo_url: string | null; payments: { reference: string; status: string; amount_kobo: number; verified_at: string | null }[] };

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
  const canDecide = hasRole(user, "ApprovingOfficer") && a.status === "PENDING_APPROVAL";
  const canCapture = hasRole(user, "ApprovingOfficer", "IssuingOfficer") && a.status === "APPROVED_FOR_BIOMETRICS";
  const canCollect = hasRole(user, "ApprovingOfficer", "IssuingOfficer") && a.status === "READY_FOR_COLLECTION";

  return (
    <div className="space-y-6">
      <PageTitle
        title={<>{a.application_number} <StatusBadge status={a.status} label={a.status_label} /></>}
        subtitle={`${a.channel === "ASSISTED" ? "Assisted" : "Online"} ${applicationType(a, true).toLowerCase()} · submitted ${dateTime(a.submitted_at)}`}
        actions={
          <div className="flex gap-2">
            {canCapture && <Link href={`/staff/applications/${a.id}/biometrics`} className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white">Open biometrics desk</Link>}
            {a.card && <Link href={`/staff/cards/${a.card.id}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Card {a.card.card_number}</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      <RiskPanel flags={a.risk_flags ?? []} busy={busy} onRecheck={() => act("risk-check")} />

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

      {canDecide && (
        <Panel title="Decision">
          <div className="space-y-3">
            <Field label="Notes to the applicant" hint="Required when querying or rejecting. The applicant receives these by e-mail.">
              <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} />
            </Field>
            <div className="flex flex-wrap gap-2">
              <Button disabled={busy} onClick={() => act("decision", { decision: "APPROVE", notes: notes || undefined })}>Approve for biometrics</Button>
              <Button variant="gold" disabled={busy || !notes.trim()} onClick={() => act("decision", { decision: "QUERY", notes })}>Query applicant</Button>
              <Button variant="danger" disabled={busy || !notes.trim()} onClick={() => confirm("Reject this application? This cannot be undone.") && act("decision", { decision: "REJECT", notes })}>Reject</Button>
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

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="Particulars" className="lg:col-span-2">
          <div className="flex flex-col gap-5 sm:flex-row">
            {detail.photo_url && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={detail.photo_url} alt="Applicant" className="h-44 w-36 shrink-0 rounded-lg border object-cover" />
            )}
            <Dl
              items={[
                ["Surname", a.surname], ["Other names", a.forenames], ["Nationality", a.nationality], ["Sex", a.sex],
                ["Date of birth", nisDate(a.date_of_birth)], ["Place of birth", a.place_of_birth], ["Profession", a.profession],
                ["Passport", `${a.passport_number} (expires ${nisDate(a.passport_expiry)})`], ["Domicile", a.domicile],
                ["Phone", a.phone], ["E-mail", a.email],
                ["Emergency contact", `${a.emergency_contact_name} (${a.emergency_contact_relation}) ${a.emergency_contact_phone}`],
                ["Height / complexion", `${a.height ?? "—"} / ${a.complexion ?? "—"}`],
                ["Eyes / hair", `${a.eye_color ?? "—"} / ${a.hair_color ?? "—"}`],
              ]}
            />
          </div>
        </Panel>
        <div className="space-y-6">
          <Panel title="Appointment & payment">
            <Dl
              items={[
                ["Center", a.enrollment_center?.name], ["Date", `${nisDate(a.appointment_date)} ${a.appointment_time}`],
                ["Fee", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
                ...detail.payments.map((p) => [`Payment ${p.reference}`, `${p.status}${p.verified_at ? " (verified)" : ""}`] as [string, string]),
              ]}
            />
          </Panel>
          <Panel title="Documents"><DocumentList scope="staff" applicationId={a.id} documents={a.documents ?? []} /></Panel>
        </div>
      </div>

      <Panel title="Workflow history">{a.history && <History history={a.history} />}</Panel>
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
