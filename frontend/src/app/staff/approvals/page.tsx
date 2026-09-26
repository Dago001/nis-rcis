"use client";

import Link from "next/link";
import { useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type Approval = {
  id: number;
  action: string;
  label: string;
  status: "PENDING" | "APPROVED" | "REJECTED" | "CANCELLED";
  reason: string;
  payload: Record<string, string> | null;
  card: { id: number; card_number: string; holder: string; status: string } | null;
  requested_by: string;
  requested_at: string;
  decided_by: string | null;
  decided_at: string | null;
  decision_notes: string | null;
  can_decide: boolean;
  can_cancel: boolean;
};

const TABS: [string, string][] = [["PENDING", "Waiting for approval"], ["APPROVED", "Approved"], ["REJECTED", "Rejected"], ["ALL", "All"]];

/** Two-person rule: sensitive card actions waiting for a second officer. */
export default function ApprovalsPage() {
  const [tab, setTab] = useState("PENDING");
  const { data, reload } = useFetch<{ data: Approval[] }>("staff", `approvals?status=${tab}`);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  async function decide(a: Approval, action: "approve" | "reject" | "cancel") {
    if (action !== "cancel" && !confirm(`${action === "approve" ? "Approve" : "Reject"}: ${a.label} for card ${a.card?.card_number}?`)) return;
    setBusy(true);
    try {
      await api("staff", `approvals/${a.id}/${action}`, { method: "POST", json: { notes: notes[a.id] ?? "" } });
      setMessage({ tone: "success", text: `${a.label} ${action === "approve" ? "approved and applied" : action === "reject" ? "rejected" : "cancelled"}.` });
      reload();
    } catch (e) {
      setMessage({ tone: "danger", text: e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e) });
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Approvals" subtitle="Two-person rule: revoking, reinstating or correcting a card only takes effect after a second officer approves it." />
      {message && <Alert tone={message.tone}>{message.text}</Alert>}
      <div className="flex gap-1 border-b border-slate-200">
        {TABS.map(([v, l]) => (
          <button key={v} onClick={() => setTab(v)} className={`border-b-2 px-3 py-2 text-sm font-medium ${tab === v ? "border-nis-primary text-nis-primary" : "border-transparent text-slate-600 hover:text-slate-900"}`}>{l}</button>
        ))}
      </div>
      {!data ? <Spinner /> : data.data.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-600">Nothing here.</p>
      ) : (
        <ul className="space-y-3">
          {data.data.map((a) => (
            <li key={a.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="font-medium text-slate-900">{a.label}{a.card && <> · <Link href={`/staff/cards/${a.card.id}`} className="text-nis-primary hover:underline">card {a.card.card_number}</Link> ({a.card.holder})</>}</div>
                  <div className="mt-1 text-sm text-slate-600">Requested by {a.requested_by} · {dateTime(a.requested_at)}</div>
                  <div className="mt-2 text-sm"><span className="text-slate-500">Reason:</span> {a.reason}</div>
                  {a.payload && <div className="mt-1 text-sm"><span className="text-slate-500">Changes:</span> {Object.entries(a.payload).map(([k, v]) => `${k.replaceAll("_", " ")} → ${v}`).join("; ")}</div>}
                  {a.decided_by && <div className="mt-1 text-sm text-slate-600">Decided by {a.decided_by} · {dateTime(a.decided_at)}{a.decision_notes ? ` · “${a.decision_notes}”` : ""}</div>}
                </div>
                <StatusBadge status={a.status === "APPROVED" ? "ISSUED" : a.status === "PENDING" ? "PENDING_APPROVAL" : "REJECTED"} label={a.status} />
              </div>
              {a.can_decide && (
                <div className="mt-4 space-y-2">
                  <Textarea placeholder="Notes (required to reject)" value={notes[a.id] ?? ""} onChange={(e) => setNotes((n) => ({ ...n, [a.id]: e.target.value }))} rows={2} />
                  <div className="flex gap-2">
                    <Button disabled={busy} onClick={() => decide(a, "approve")}>Approve</Button>
                    <Button variant="danger" disabled={busy || !(notes[a.id] ?? "").trim()} onClick={() => decide(a, "reject")}>Reject</Button>
                  </div>
                </div>
              )}
              {a.status === "PENDING" && !a.can_decide && (
                <p className="mt-3 text-xs text-slate-500">{a.can_cancel ? "Waiting for a second officer. You cannot approve your own request." : "Your role cannot approve this request."}</p>
              )}
              {a.can_cancel && <Button variant="ghost" className="mt-2" disabled={busy} onClick={() => decide(a, "cancel")}>Cancel request</Button>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
