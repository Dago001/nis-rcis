"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";

type Summary = {
  queue: Record<string, number>;
  cards: Record<string, number>;
  cards_expired: number;
  cards_expiring_30_days: number;
  watchlisted: number;
  todays_appointments: number;
  by_nationality: { nationality: string; total: number }[];
  my_activity: { decided: number; captured: number; cards_approved: number };
};

function Stat({ label, value, href, tone = "slate" }: { label: string; value: number; href?: string; tone?: "slate" | "amber" | "red" | "green" }) {
  const tones = { slate: "text-slate-900", amber: "text-amber-700", red: "text-red-700", green: "text-nis-green" };
  const body = (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-nis-green">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className={`mt-1 text-3xl font-bold tabular-nums ${tones[tone]}`}>{value}</div>
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

      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <Stat label="Awaiting approval" value={s.queue.PENDING_APPROVAL} href="/staff/applications?status=PENDING_APPROVAL" tone="amber" />
        <Stat label="Queried" value={s.queue.QUERIED} href="/staff/applications?status=QUERIED" />
        <Stat label="Biometrics today" value={s.todays_appointments} href="/staff/applications?status=APPROVED_FOR_BIOMETRICS" />
        <Stat label="Cards awaiting approval" value={s.cards.APPROVED} href="/staff/cards?status=APPROVED" tone="amber" />
        <Stat label="Ready for collection" value={s.queue.READY_FOR_COLLECTION} href="/staff/applications?status=READY_FOR_COLLECTION" tone="green" />
        <Stat label="Active cards" value={s.cards.ISSUED + s.cards.RENEWED} href="/staff/cards" tone="green" />
        <Stat label="Expiring in 30 days" value={s.cards_expiring_30_days} />
        <Stat label="Watchlisted" value={s.watchlisted} href="/staff/cards?watchlisted=1" tone="red" />
      </div>

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
