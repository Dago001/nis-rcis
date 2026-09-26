"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BarList, ChartCard, ColumnChart, compact, DataTable, Legend, LineChart, Meter, SERIES, SplitBar } from "@/components/charts";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { naira } from "@/lib/format";

type Analytics = {
  monthly: { month: string; submitted: number; issued: number; revenue_naira: number }[];
  totals: {
    applications: number;
    applications_this_month: number;
    applications_last_month: number;
    cards_issued: number;
    revenue_naira: number;
    revenue_this_month_naira: number;
    payments: number;
  };
  decisions: { approved: number; queried: number; rejected: number; approval_rate: number | null };
  processing_days: { submission_to_decision: number; decision_to_biometrics: number; biometrics_to_ready: number; ready_to_collection: number };
  nationalities: { label: string; total: number }[];
  mix: { type: Record<string, number>; channel: Record<string, number>; sex: Record<string, number> };
  appointments: { date: string; total: number }[];
};

type Summary = {
  queue: Record<string, number>;
  cards: Record<string, number>;
  cards_expired: number;
  cards_expiring_30_days: number;
  watchlisted: number;
  paid_awaiting_submission: number;
  todays_appointments: number;
  my_activity: { decided: number; captured: number; cards_approved: number };
  analytics: Analytics;
};

const PIPELINE: [string, string][] = [
  ["PENDING_APPROVAL", "Pending approval"],
  ["QUERIED", "Queried"],
  ["APPROVED_FOR_BIOMETRICS", "Approved for biometrics"],
  ["BIOMETRICS_CAPTURED", "In production"],
  ["READY_FOR_COLLECTION", "Ready for collection"],
  ["ISSUED", "Collected"],
  ["REJECTED", "Rejected"],
];

const monthLabel = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "short" });
const monthLong = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "long", year: "numeric" });
const dayLabel = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString("en-GB", { weekday: "short", day: "numeric" });
const title = (s: string) => s.charAt(0) + s.slice(1).toLowerCase();

function Tile({ label, value, href, tone = "slate" }: { label: string; value: number; href?: string; tone?: "slate" | "amber" | "red" | "green" }) {
  const tones = { slate: "text-slate-900", amber: "text-nis-orange", red: "text-nis-red", green: "text-nis-primary" };
  const body = (
    <div className="h-full rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:border-nis-primary/40 hover:shadow-md">
      <div className="text-[11px] font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className={`mt-1 text-xl font-medium ${tones[tone]}`}>{value}</div>
    </div>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

function Kpi({ label, value, note }: { label: string; value: string; note?: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-2 text-3xl font-medium text-slate-900">{value}</div>
      {note && <div className="mt-1 text-xs text-slate-500">{note}</div>}
    </div>
  );
}

export default function StaffDashboard() {
  const user = useStaff();
  const [s, setS] = useState<Summary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<Summary>("staff", "dashboard").then(setS).catch((e) => setError(e.message));
  }, []);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!s) return <Spinner />;

  const a = s.analytics;
  const t = a.totals;
  const delta = t.applications_this_month - t.applications_last_month;
  const months = a.monthly.map((m) => monthLabel(m.month));
  const pipeline = PIPELINE.map(([key, label]) => ({ label, value: s.queue[key] ?? 0, href: `/staff/applications?status=${key}` }));
  const days = a.processing_days;
  const stages = [
    { label: "Submission → decision", value: days.submission_to_decision },
    { label: "Decision → biometrics", value: days.decision_to_biometrics },
    { label: "Biometrics → card ready", value: days.biometrics_to_ready },
    { label: "Ready → collected", value: days.ready_to_collection },
  ];
  const parts = (r: Record<string, number>, names: Record<string, string> = {}) => Object.entries(r).map(([k, v]) => ({ label: names[k] ?? title(k), value: v }));

  return (
    <div className="space-y-6">
      <PageTitle title="Dashboard" subtitle={`${user.command} · ${user.role_label}`} />

      {/* Headline figures */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          label="Applications this month"
          value={String(t.applications_this_month)}
          note={
            <>
              <span className={delta > 0 ? "text-nis-primary" : delta < 0 ? "text-nis-red" : ""}>{delta > 0 ? "▲" : delta < 0 ? "▼" : "•"} {delta > 0 ? "+" : ""}{delta}</span> vs last month · {t.applications} in total
            </>
          }
        />
        <Kpi label="Fees collected" value={naira(t.revenue_naira)} note={`${naira(t.revenue_this_month_naira)} this month · ${t.payments} payments`} />
        <Kpi label="Cards issued" value={String(t.cards_issued)} note={`${(s.cards.ISSUED ?? 0) + (s.cards.RENEWED ?? 0)} currently active`} />
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <Meter label="Approval rate" value={a.decisions.approval_rate} />
          <p className="mt-2 text-xs text-slate-500">{a.decisions.approved} approved · {a.decisions.queried} queried · {a.decisions.rejected} rejected</p>
        </div>
      </div>

      {/* Work queues */}
      <section aria-label="Work queues">
        <h2 className="mb-2 text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">Work queues</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <Tile label="Paid – not submitted" value={s.paid_awaiting_submission} href="/staff/payments?filter=awaiting_submission" tone="amber" />
          <Tile label="Awaiting approval" value={s.queue.PENDING_APPROVAL} href="/staff/applications?status=PENDING_APPROVAL" tone="amber" />
          <Tile label="Queried" value={s.queue.QUERIED} href="/staff/applications?status=QUERIED" />
          <Tile label="Biometrics today" value={s.todays_appointments} href="/staff/applications?status=APPROVED_FOR_BIOMETRICS" />
          <Tile label="Cards to approve" value={s.cards.APPROVED} href="/staff/cards?status=APPROVED" tone="amber" />
          <Tile label="Ready for collection" value={s.queue.READY_FOR_COLLECTION} href="/staff/applications?status=READY_FOR_COLLECTION" tone="green" />
          <Tile label="Active cards" value={(s.cards.ISSUED ?? 0) + (s.cards.RENEWED ?? 0)} href="/staff/cards" tone="green" />
          <Tile label="Expiring in 30 days" value={s.cards_expiring_30_days} />
          <Tile label="Expired cards" value={s.cards_expired} href="/staff/cards?expired=1" />
          <Tile label="Watchlisted" value={s.watchlisted} href="/staff/cards?watchlisted=1" tone="red" />
        </div>
      </section>

      {/* Trends */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title="Applications and cards issued"
          subtitle="Last 12 months"
          legend={<Legend items={[{ label: "Applications submitted", color: SERIES[0], kind: "line" }, { label: "Cards issued", color: SERIES[1], kind: "line" }]} />}
          table={<DataTable head={["Month", "Applications", "Cards issued"]} rows={a.monthly.map((m) => [monthLong(m.month), m.submitted, m.issued])} />}
        >
          <LineChart labels={months} series={[{ name: "Applications submitted", values: a.monthly.map((m) => m.submitted) }, { name: "Cards issued", values: a.monthly.map((m) => m.issued) }]} />
        </ChartCard>
        <ChartCard
          title="Fees collected"
          subtitle="Confirmed Paystack payments per month (₦)"
          table={<DataTable head={["Month", "Fees (₦)"]} rows={a.monthly.map((m) => [monthLong(m.month), naira(m.revenue_naira)])} />}
        >
          <ColumnChart name="Fees collected per month" labels={months} values={a.monthly.map((m) => m.revenue_naira)} format={(n) => `₦${compact(n)}`} />
        </ChartCard>
      </div>

      {/* Pipeline and nationalities */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard title="Application pipeline" subtitle="Applications at each stage now" table={<DataTable head={["Stage", "Applications"]} rows={pipeline.map((p) => [p.label, p.value])} />}>
          <BarList items={pipeline} />
        </ChartCard>
        <ChartCard title="Top nationalities" subtitle="All applications" table={<DataTable head={["Nationality", "Applications"]} rows={a.nationalities.map((n) => [n.label, n.total])} />}>
          <BarList items={a.nationalities.map((n) => ({ label: n.label, value: n.total }))} />
        </ChartCard>
      </div>

      {/* Processing, mix, appointments */}
      <div className="grid gap-6 xl:grid-cols-3">
        <ChartCard title="Average processing time" subtitle="Days between workflow stages" table={<DataTable head={["Stage", "Average days"]} rows={stages.map((x) => [x.label, x.value])} />}>
          <BarList stacked items={stages} format={(n) => `${n} day${n === 1 ? "" : "s"}`} />
        </ChartCard>
        <ChartCard title="Application mix" subtitle="All applications">
          <div className="space-y-5">
            <SplitBar title="Type" parts={parts(a.mix.type, { NEW: "New card", RENEWAL: "Renewal" })} />
            <SplitBar title="Channel" parts={parts(a.mix.channel, { ONLINE: "Online", ASSISTED: "Assisted (walk-in)" })} />
            <SplitBar title="Sex" parts={parts(a.mix.sex)} />
          </div>
        </ChartCard>
        <ChartCard
          title="Upcoming biometrics appointments"
          subtitle="Next 10 working days, NIS Headquarters"
          table={<DataTable head={["Day", "Appointments"]} rows={a.appointments.map((d) => [dayLabel(d.date), d.total])} />}
        >
          <ColumnChart name="Appointments per day" labels={a.appointments.map((d) => dayLabel(d.date))} values={a.appointments.map((d) => d.total)} />
        </ChartCard>
      </div>

      <Panel title="My activity">
        <div className="grid grid-cols-3 gap-3">
          <Tile label="Decisions" value={s.my_activity.decided} />
          <Tile label="Biometrics captured" value={s.my_activity.captured} />
          <Tile label="Cards approved" value={s.my_activity.cards_approved} />
        </div>
      </Panel>
    </div>
  );
}
