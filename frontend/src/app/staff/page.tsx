"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { dateTime, naira } from "@/lib/format";

type Summary = {
  queue: Record<string, number>;
  cards: Record<string, number>;
  cards_expired: number;
  cards_expiring_30_days: number;
  watchlisted: number;
  paid_awaiting_submission: number;
  recent_payments: { id: number; reference: string; amount_naira: number; paid_at: string | null; name: string | null; submitted: boolean }[];
  todays_appointments: number;
  by_nationality: { nationality: string; total: number }[];
  my_activity: { decided: number; captured: number; cards_approved: number };
};

function Stat({ label, value, href, tone = "slate" }: { label: string; value: number; href?: string; tone?: "slate" | "amber" | "red" | "green" }) {
  const tones = { slate: "text-slate-900", amber: "text-nis-orange", red: "text-nis-red", green: "text-nis-primary" };
  const body = (
    <div className="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-nis-primary/40 hover:shadow-md">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className={`mt-1.5 text-2xl font-medium tabular-nums ${tones[tone]}`}>{value}</div>
      {href && <div className="mt-2 text-xs font-medium text-nis-primary">View ›</div>}
    </div>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

export default function StaffDashboard() {
  const user = useStaff();
  const [s, setS] = useState<Summary | null>(null);

  useEffect(() => {
    api<Summary>("staff", "dashboard").then(setS);
  }, []);

  if (!s) return <Spinner />;
  const max = Math.max(1, ...s.by_nationality.map((n) => n.total));

  return (
    <div className="space-y-6">
      <PageTitle title="Dashboard" subtitle={`${user.command} · ${user.role_label}`} />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <Stat label="Paid – not yet submitted" value={s.paid_awaiting_submission} href="/staff/payments?filter=awaiting_submission" tone="amber" />
        <Stat label="Awaiting approval" value={s.queue.PENDING_APPROVAL} href="/staff/applications?status=PENDING_APPROVAL" tone="amber" />
        <Stat label="Queried" value={s.queue.QUERIED} href="/staff/applications?status=QUERIED" />
        <Stat label="Biometrics today" value={s.todays_appointments} href="/staff/applications?status=APPROVED_FOR_BIOMETRICS" />
        <Stat label="Cards awaiting approval" value={s.cards.APPROVED} href="/staff/cards?status=APPROVED" tone="amber" />
        <Stat label="Ready for collection" value={s.queue.READY_FOR_COLLECTION} href="/staff/applications?status=READY_FOR_COLLECTION" tone="green" />
        <Stat label="Active cards" value={s.cards.ISSUED + s.cards.RENEWED} href="/staff/cards" tone="green" />
        <Stat label="Expiring in 30 days" value={s.cards_expiring_30_days} />
        <Stat label="Cards expired" value={s.cards_expired} />
        <Stat label="Watchlisted" value={s.watchlisted} href="/staff/cards?watchlisted=1" tone="red" />
      </div>

      <Panel title="Latest fee payments" actions={<Link href="/staff/payments?filter=all" className="text-sm font-medium text-nis-primary hover:underline">All payments ›</Link>}>
        {s.recent_payments.length === 0 ? (
          <p className="text-sm text-slate-600">No payments yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100 text-sm">
            {s.recent_payments.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                <Link href={`/staff/payments/${p.id}`} className="font-medium text-nis-primary hover:underline">{p.name ?? p.reference}</Link>
                <span className="text-slate-600">{naira(p.amount_naira)} · {dateTime(p.paid_at)}</span>
                <span className={p.submitted ? "text-nis-primary" : "text-nis-orange"}>{p.submitted ? "Application submitted" : "Not yet submitted"}</span>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <div className="grid gap-6 lg:grid-cols-2">
        <Panel title="Cards by nationality">
          {s.by_nationality.length === 0 ? (
            <p className="text-sm text-slate-600">No cards issued yet.</p>
          ) : (
            <ul className="space-y-2.5">
              {s.by_nationality.map((n) => (
                <li key={n.nationality} className="text-sm">
                  <div className="flex justify-between"><span>{n.nationality}</span><span className="tabular-nums text-slate-600">{n.total}</span></div>
                  <div className="mt-1 h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-nis-green" style={{ width: `${(n.total / max) * 100}%` }} /></div>
                </li>
              ))}
            </ul>
          )}
        </Panel>
        <Panel title="My activity">
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Decisions" value={s.my_activity.decided} />
            <Stat label="Captures" value={s.my_activity.captured} />
            <Stat label="Cards approved" value={s.my_activity.cards_approved} />
          </div>
        </Panel>
      </div>
    </div>
  );
}
