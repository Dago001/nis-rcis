"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Input, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import type { Card } from "@/lib/types";

type Detail = { data: Card; photo_url: string | null; signature_url: string | null };

function addYears(date: string, years: number) {
  const d = new Date(`${date}T00:00:00`);
  d.setFullYear(d.getFullYear() + years);
  d.setDate(d.getDate() - 1);
  return d.toISOString().slice(0, 10);
}

export default function CardDetailPage() {
  const { id } = useParams<{ id: string }>();
  const user = useStaff();
  const [detail, setDetail] = useState<Detail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [reason, setReason] = useState("");

  const load = useCallback(() => api<Detail>("staff", `cards/${id}`).then(setDetail), [id]);
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  async function act(path: string, body: unknown = {}, done?: string) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await api("staff", `cards/${id}/${path}`, { method: "POST", json: body });
      setReason("");
      setNotice(done ?? "Saved.");
      await load();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  function renew(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = Object.fromEntries(new FormData(event.currentTarget));
    void act("renew", form, "Card renewed and endorsement recorded.");
  }

  if (!detail) return error ? <Alert tone="danger">{error}</Alert> : <Spinner />;
  const c = detail.data;
  const approver = hasRole(user, "ApprovingOfficer", "SuperAdmin", "Admin");
  const issuer = hasRole(user, "ApprovingOfficer", "IssuingOfficer", "SuperAdmin", "Admin");
  const active = c.status === "ISSUED" || c.status === "RENEWED";
  const renewFrom = c.expires_on && new Date(c.expires_on) > new Date() ? c.expires_on : new Date().toISOString().slice(0, 10);

  return (
    <div className="space-y-6">
      <PageTitle
        title={<>Card {c.card_number} <StatusBadge status={c.status} /> {c.is_watchlisted && <StatusBadge status="WATCHLISTED" />}</>}
        subtitle={`Booklet ${c.booklet_number} · ${c.verification_status}`}
        actions={
          <div className="flex gap-2">
            {c.application_id && <Link href={`/staff/applications/${c.application_id}`} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50">Application</Link>}
            {issuer && active && <Link href={`/staff/cards/${c.id}/print`} className="rounded-lg bg-nis-green px-3 py-2 text-sm font-semibold text-white">Print card</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}
      {c.is_watchlisted && <Alert tone="danger"><strong>WATCHLIST:</strong> {c.watchlist_reason}</Alert>}
      {c.status === "REVOKED" && <Alert tone="danger"><strong>Revoked:</strong> {c.revocation_reason}</Alert>}
      {c.reported_lost_at && c.status !== "REVOKED" && (
        <Alert tone="danger">
          <strong>Reported {c.lost_report_type?.toLowerCase()} by the holder</strong> on {dateTime(c.reported_lost_at)}: {c.lost_report_details}
          {c.police_report_number && <> · Police report {c.police_report_number}</>}
          {issuer && (
            <span className="mt-2 flex flex-wrap items-center gap-2">
              <Input className="!w-72" placeholder="Notes, e.g. holder brought the card to HQ" value={reason} onChange={(e) => setReason(e.target.value)} aria-label="Notes for withdrawing the report" />
              <Button variant="secondary" disabled={busy || !reason.trim()} onClick={() => act("clear-lost-report", { notes: reason }, "Report withdrawn: the card is valid again.")}>Card found — withdraw report</Button>
            </span>
          )}
        </Alert>
      )}
      {c.status === "QUERIED" && <Alert tone="warning"><strong>Queried:</strong> {c.query_reason} — correct the particulars, then resubmit for approval.</Alert>}

      {/* 1. Card Holder Details */}
      <Panel title="Card Holder Details" subtitle="Personal particulars and biometric specimens for this card holder.">
        <div className="flex flex-col gap-6 sm:flex-row">
          <div className="shrink-0 space-y-3">
            {detail.photo_url && (
              <div>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={detail.photo_url}
                  alt="Holder"
                  className="h-48 w-40 rounded-xl border border-slate-200 bg-slate-50 object-cover shadow-sm"
                />
                <span className="mt-1 block text-center text-xs font-medium text-slate-500">Holder Photo</span>
              </div>
            )}
            {detail.signature_url && (
              <div>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={detail.signature_url}
                  alt="Signature"
                  className="h-14 w-40 rounded-lg border border-slate-200 bg-white object-contain p-1 shadow-sm"
                />
                <span className="mt-1 block text-center text-xs font-medium text-slate-500">Specimen Signature</span>
              </div>
            )}
          </div>
          <div className="min-w-0 flex-1">
            <Dl
              items={[
                ["Full Name", `${c.surname}, ${c.forenames}`],
                ["Nationality", c.nationality],
                ["Sex", c.sex],
                ["Date of Birth", nisDate(c.date_of_birth)],
                ["Place of Birth", c.place_of_birth],
                ["Profession", c.profession || "—"],
                ["Passport / Travel Doc", c.passport_number],
                ["Residential Address", c.domicile],
              ]}
            />
          </div>
        </div>
      </Panel>

      {/* 2. Card Information */}
      <Panel title="Card Information" subtitle="Official issuance parameters, validity dates and verification details.">
        <Dl
          items={[
            ["Card Number", c.card_number],
            ["Booklet Number", c.booklet_number || "—"],
            ["Card Status", c.status],
            ["Verification Status", c.verification_status || "—"],
            ["Issued Date & Location", `${nisDate(c.issued_on)} at ${c.issued_at || "NIS HQ"}`],
            ["Expiry Date", nisDate(c.expires_on)],
            ["Issuing Officer", `${c.issuing_officer_name} (${c.issuing_officer_service_no})`],
            ["Decision Reference", c.decision_reference || "—"],
            ...(c.application_id ? [["Associated Application", <Link key="app" href={`/staff/applications/${c.application_id}`} className="font-semibold text-nis-primary underline">Application #{c.application_id}</Link>] as [string, any]] : []),
          ]}
        />
      </Panel>

      {/* 3. Supporting Review Information & Security Controls */}
      <div className="grid gap-6 lg:grid-cols-2">
        {approver && (active || c.status === "APPROVED") && (
          <Panel title="Security Actions" subtitle="Administrative flags, watchlist status, and revocation requests.">
            <div className="space-y-3">
              <Field label="Reason"><Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} /></Field>
              <div className="flex flex-wrap gap-2">
                {c.is_watchlisted ? (
                  <Button variant="secondary" disabled={busy} onClick={() => act("watchlist", { watchlisted: false }, "Removed from watchlist.")}>Clear watchlist</Button>
                ) : (
                  <Button variant="gold" disabled={busy || !reason.trim()} onClick={() => act("watchlist", { watchlisted: true, reason }, "Card watchlisted.")}>Watchlist</Button>
                )}
                <Button variant="danger" disabled={busy || !reason.trim()} onClick={() => confirm("Request revocation of this card? A second officer must approve it.") && act("revoke", { reason }, "Revocation requested. It takes effect when a second officer approves it under Approvals.")}>Request revocation</Button>
              </div>
            </div>
          </Panel>
        )}
        {c.status === "REVOKED" && hasRole(user, "SuperAdmin") && (
          <Panel title="Reinstate" subtitle="Restore a revoked card with administrative justification.">
            <div className="space-y-3">
              <Field label="Reason for reinstatement"><Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} /></Field>
              <Button disabled={busy || !reason.trim()} onClick={() => act("reinstate", { reason }, "Reinstatement requested. It takes effect when a second officer approves it under Approvals.")}>Request reinstatement</Button>
            </div>
          </Panel>
        )}
        {(c.status === "APPROVED" || c.status === "QUERIED") && hasRole(user, "ApprovingOfficer", "IssuingOfficer", "SuperAdmin", "Admin") && (
          <CorrectionPanel cardId={c.id} onDone={(m) => { setNotice(m); void load(); }} />
        )}
      </div>

      {c.print_jobs && c.print_jobs.length > 0 && (
        <Panel title="Print Log" subtitle="Physical card printing history and spoil records.">
          <ul className="divide-y divide-slate-100 text-sm">
            {c.print_jobs.map((j, i) => (
              <li key={i} className="flex flex-wrap justify-between gap-2 py-2">
                <span className={j.outcome === "PRINTED" ? "font-medium text-nis-primary" : "font-medium text-red-700"}>{j.outcome === "PRINTED" ? "✓ Printed" : `✕ Spoiled: ${j.spoil_reason}`}</span>
                <span className="text-slate-500">{j.printed_by} · {dateTime(j.at)}</span>
              </li>
            ))}
          </ul>
        </Panel>
      )}

      <Panel title="Renewal Endorsements" subtitle="Official residence card validity extensions.">
        <div className="space-y-5">
          {c.renewals?.length ? (
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase text-slate-500"><tr><th>#</th><th>Period</th><th>At</th><th>Officer</th><th>Receipt</th></tr></thead>
              <tbody>
                {c.renewals.map((r) => (
                  <tr key={r.renewal_number} className="border-t border-slate-100">
                    <td className="py-2">{r.renewal_number}</td><td>{nisDate(r.from_date)} – {nisDate(r.to_date)}</td>
                    <td>{r.renewed_at}</td><td>{r.endorsing_officer}</td><td>{r.receipt_number ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="text-sm text-slate-600">No renewals recorded.</p>
          )}
          {issuer && active && !c.is_watchlisted && (
            <form onSubmit={renew} className="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
              <Field label="From"><Input type="date" name="from_date" defaultValue={renewFrom} required /></Field>
              <Field label="To"><Input type="date" name="to_date" defaultValue={addYears(renewFrom, 2)} required /></Field>
              <Field label="Renewed at"><Input name="renewed_at" defaultValue={user.command} /></Field>
              <Field label="Fee paid (₦)"><Input type="number" name="fee_paid_naira" min={0} /></Field>
              <Field label="Receipt number"><Input name="receipt_number" /></Field>
              <Field label="Remarks"><Input name="remarks" defaultValue="RENEWAL GRANTED" /></Field>
              <div className="sm:col-span-3"><Button type="submit" disabled={busy}>Record renewal</Button></div>
            </form>
          )}
        </div>
      </Panel>

      {/* 4. Approval and Issuance Actions (at the bottom of the page) */}
      {c.status === "APPROVED" && approver && (
        <Panel
          title="Final Approval & Issuance"
          subtitle="Check the captured photo, signature, and particulars above before approving the card for printing."
        >
          <div className="space-y-3">
            <Field label="Query reason (only if querying)">
              <Input
                placeholder="Enter reason if querying this card..."
                value={reason}
                onChange={(e) => setReason(e.target.value)}
              />
            </Field>
            <div className="flex flex-wrap gap-2">
              <Button disabled={busy} onClick={() => act("decision", { decision: "APPROVE" }, "Card approved and issued.")}>
                ✓ Approve &amp; issue
              </Button>
              <Button variant="gold" disabled={busy || !reason.trim()} onClick={() => act("decision", { decision: "QUERY", notes: reason }, "Card queried.")}>
                Query
              </Button>
            </div>
          </div>
        </Panel>
      )}

      {active && issuer && (
        <Alert tone="info">
          Card printed?{" "}
          <button className="font-semibold underline" disabled={busy} onClick={() => act("ready-for-collection", {}, "Applicant notified: card ready for collection.")}>
            Mark ready for collection
          </button>{" "}
          or manage handover under{" "}
          <Link href="/staff/card-issuance" className="font-semibold underline">
            Card Issuance &amp; Collection
          </Link>.
        </Alert>
      )}
    </div>
  );
}

const CORRECTABLE: [string, string][] = [
  ["surname", "Surname"], ["forenames", "Other names"], ["place_of_birth", "Place of birth"], ["profession", "Profession"],
  ["domicile", "Address in Nigeria"], ["passport_number", "Passport number"], ["height", "Height"], ["complexion", "Complexion"],
];

/** Request a correction to a card not yet issued; a second officer approves it (two-person rule). */
function CorrectionPanel({ cardId, onDone }: { cardId: number; onDone: (message: string) => void }) {
  const [field, setField] = useState("surname");
  const [value, setValue] = useState("");
  const [why, setWhy] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ message: string }>("staff", `cards/${cardId}`, { method: "PUT", json: { [field]: value.toUpperCase(), change_reason: why } });
      setValue("");
      setWhy("");
      onDone(r.message);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Panel title="Request a correction">
      <div className="space-y-3">
        {error && <Alert tone="danger">{error}</Alert>}
        <Field label="Field"><Select value={field} onChange={(e) => setField(e.target.value)}>{CORRECTABLE.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</Select></Field>
        <Field label="Correct value"><Input value={value} onChange={(e) => setValue(e.target.value)} /></Field>
        <Field label="Reason"><Textarea value={why} onChange={(e) => setWhy(e.target.value)} rows={2} /></Field>
        <Button variant="secondary" disabled={busy || !value.trim() || !why.trim()} onClick={submit}>Request correction</Button>
        <p className="text-xs text-slate-500">A second officer must approve the correction before it is applied.</p>
      </div>
    </Panel>
  );
}
