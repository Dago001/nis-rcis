"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Input, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { nisDate } from "@/lib/format";
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
  const approver = hasRole(user, "ApprovingOfficer");
  const issuer = hasRole(user, "ApprovingOfficer", "IssuingOfficer");
  const active = c.status === "ISSUED" || c.status === "RENEWED";
  const renewFrom = c.expires_on && new Date(c.expires_on) > new Date() ? c.expires_on : new Date().toISOString().slice(0, 10);

  return (
    <div className="space-y-6">
      <PageTitle
        title={<>Card {c.card_number} <StatusBadge status={c.status} /> {c.is_watchlisted && <StatusBadge status="WATCHLISTED" />}</>}
        subtitle={`Booklet ${c.booklet_number} · ${c.verification_status}`}
        actions={
          <div className="flex gap-2">
            {c.application_id && <Link href={`/staff/applications/${c.application_id}`} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Application</Link>}
            {issuer && active && <Link href={`/staff/cards/${c.id}/print`} className="rounded-lg bg-nis-green px-3 py-2 text-sm font-semibold text-white">Print card</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}
      {c.is_watchlisted && <Alert tone="danger"><strong>WATCHLIST:</strong> {c.watchlist_reason}</Alert>}
      {c.status === "REVOKED" && <Alert tone="danger"><strong>Revoked:</strong> {c.revocation_reason}</Alert>}
      {c.status === "QUERIED" && <Alert tone="warning"><strong>Queried:</strong> {c.query_reason} — correct the particulars, then resubmit for approval.</Alert>}

      {c.status === "APPROVED" && approver && (
        <Panel title="Final approval">
          <div className="space-y-3">
            <p className="text-sm text-slate-600">Check the captured photo, signature and particulars before approving the card for printing.</p>
            <Field label="Query reason (only if querying)"><Input value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
            <div className="flex gap-2">
              <Button disabled={busy} onClick={() => act("decision", { decision: "APPROVE" }, "Card approved and issued.")}>Approve &amp; issue</Button>
              <Button variant="gold" disabled={busy || !reason.trim()} onClick={() => act("decision", { decision: "QUERY", notes: reason }, "Card queried.")}>Query</Button>
            </div>
          </div>
        </Panel>
      )}

      {active && issuer && (
        <Alert tone="info">
          Card printed? <button className="font-semibold underline" disabled={busy} onClick={() => act("ready-for-collection", {}, "Applicant notified: card ready for collection.")}>Mark ready for collection</button> — the applicant is notified by e-mail.
        </Alert>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="Holder" className="lg:col-span-2">
          <div className="flex flex-col gap-5 sm:flex-row">
            <div className="space-y-2">
              {detail.photo_url && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={detail.photo_url} alt="Holder" className="h-44 w-36 rounded-lg border object-cover" />
              )}
              {detail.signature_url && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={detail.signature_url} alt="Signature" className="h-12 w-36 rounded border bg-white object-contain" />
              )}
            </div>
            <Dl
              items={[
                ["Name", `${c.surname}, ${c.forenames}`], ["Nationality", c.nationality], ["Sex", c.sex],
                ["Date of birth", nisDate(c.date_of_birth)], ["Place of birth", c.place_of_birth], ["Profession", c.profession],
                ["Passport", c.passport_number], ["Domicile", c.domicile],
                ["Issued", `${nisDate(c.issued_on)} at ${c.issued_at}`], ["Expires", nisDate(c.expires_on)],
                ["Issuing officer", `${c.issuing_officer_name} (${c.issuing_officer_service_no})`], ["Decision ref.", c.decision_reference],
              ]}
            />
          </div>
        </Panel>

        <div className="space-y-6">
          {approver && (active || c.status === "APPROVED") && (
            <Panel title="Security actions">
              <div className="space-y-3">
                <Field label="Reason"><Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} /></Field>
                <div className="flex flex-wrap gap-2">
                  {c.is_watchlisted ? (
                    <Button variant="secondary" disabled={busy} onClick={() => act("watchlist", { watchlisted: false }, "Removed from watchlist.")}>Clear watchlist</Button>
                  ) : (
                    <Button variant="gold" disabled={busy || !reason.trim()} onClick={() => act("watchlist", { watchlisted: true, reason }, "Card watchlisted.")}>Watchlist</Button>
                  )}
                  <Button variant="danger" disabled={busy || !reason.trim()} onClick={() => confirm("Revoke this card?") && act("revoke", { reason }, "Card revoked.")}>Revoke</Button>
                </div>
              </div>
            </Panel>
          )}
          {c.status === "REVOKED" && hasRole(user, "SuperAdmin") && (
            <Panel title="Reinstate">
              <Button disabled={busy} onClick={() => act("reinstate", {}, "Card reinstated.")}>Reinstate card</Button>
            </Panel>
          )}
        </div>
      </div>

      <Panel title="Renewal endorsements">
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
    </div>
  );
}
