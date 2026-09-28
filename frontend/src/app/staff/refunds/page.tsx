"use client";

import Link from "next/link";
import { useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type RefundRow = {
  id: number;
  status: "REQUESTED" | "PROCESSED" | "REJECTED" | "FAILED";
  amount_naira: number;
  reason: string;
  payment_reference: string | null;
  paid_at: string | null;
  applicant: string | null;
  application: { id: number; application_number: string; status: string } | null;
  requested_at: string;
  decided_by: string | null;
  decided_at: string | null;
  decision_notes: string | null;
  gateway_reference: string | null;
};

const TABS: [string, string][] = [["REQUESTED", "Waiting for a decision"], ["PROCESSED", "Refunded"], ["FAILED", "Failed at Paystack"], ["REJECTED", "Rejected"], ["ALL", "All"]];
const BADGE: Record<RefundRow["status"], string> = { REQUESTED: "PENDING_APPROVAL", PROCESSED: "ISSUED", REJECTED: "REJECTED", FAILED: "QUERIED" };

/** Fee refund requests from applicants; approved refunds are sent back through Paystack. */
export default function RefundsPage() {
  const [tab, setTab] = useState("REQUESTED");
  const { data, reload } = useFetch<{ data: RefundRow[] }>("staff", `refunds?status=${tab}`);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  async function decide(r: RefundRow, action: "approve" | "reject") {
    if (!confirm(`${action === "approve" ? "Refund" : "Reject the refund of"} ${naira(r.amount_naira)} to ${r.applicant}?`)) return;
    setBusy(true);
    try {
      const result = await api<{ message?: string; status: string }>("staff", `refunds/${r.id}/${action}`, { method: "POST", json: { notes: notes[r.id] || undefined } });
      setMessage({ tone: result.status === "FAILED" ? "danger" : "success", text: result.message ?? "Refund rejected. The applicant has been told by e-mail." });
      reload();
    } catch (e) {
      setMessage({ tone: "danger", text: e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e) });
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Fee refunds" subtitle="Refunds are allowed for unused payments and for applications that were rejected. Approving sends the money back through Paystack." />
      {message && <Alert tone={message.tone}>{message.text}</Alert>}
      <div className="flex flex-wrap gap-1 border-b border-slate-200">
        {TABS.map(([v, l]) => (
          <button key={v} onClick={() => setTab(v)} className={`border-b-2 px-3 py-2 text-sm font-medium ${tab === v ? "border-nis-primary text-nis-primary" : "border-transparent text-slate-600 hover:text-slate-900"}`}>{l}</button>
        ))}
      </div>
      {!data ? <Spinner /> : data.data.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-600">Nothing here.</p>
      ) : (
        <ul className="space-y-3">
          {data.data.map((r) => (
            <li key={r.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1 text-sm">
                  <div className="font-medium text-slate-900">{naira(r.amount_naira)} · {r.applicant}</div>
                  <div className="text-slate-600">
                    Payment <span className="font-mono">{r.payment_reference}</span> of {dateTime(r.paid_at)} ·{" "}
                    {r.application ? <Link href={`/staff/applications/${r.application.id}`} className="text-nis-primary hover:underline">{r.application.application_number} ({r.application.status.replaceAll("_", " ").toLowerCase()})</Link> : "not used for an application"}
                  </div>
                  <div><span className="text-slate-500">Reason:</span> {r.reason}</div>
                  <div className="text-slate-500">Requested {dateTime(r.requested_at)}</div>
                  {r.decided_by && <div className="text-slate-600">Decided by {r.decided_by} · {dateTime(r.decided_at)}{r.decision_notes ? ` · “${r.decision_notes}”` : ""}{r.gateway_reference ? ` · Paystack ${r.gateway_reference}` : ""}</div>}
                </div>
                <StatusBadge status={BADGE[r.status]} label={r.status} />
              </div>
              {(r.status === "REQUESTED" || r.status === "FAILED") && (
                <div className="mt-4 space-y-2">
                  <Textarea placeholder="Notes (required to reject; sent to the applicant)" value={notes[r.id] ?? ""} onChange={(e) => setNotes((n) => ({ ...n, [r.id]: e.target.value }))} rows={2} />
                  <div className="flex gap-2">
                    <Button disabled={busy} onClick={() => decide(r, "approve")}>{r.status === "FAILED" ? "Retry refund" : "Approve refund"}</Button>
                    {r.status === "REQUESTED" && <Button variant="danger" disabled={busy || !(notes[r.id] ?? "").trim()} onClick={() => decide(r, "reject")}>Reject</Button>}
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
