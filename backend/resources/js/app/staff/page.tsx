"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BarList, ChartCard, ColumnChart, compact, DataTable, DoughnutChart, Legend, LineChart, Meter, SERIES, SplitBar, VerticalTwoBarChart } from "@/components/charts";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";
import { naira } from "@/lib/format";

type Analytics = {
  monthly: { month: string; submitted: number; issued: number; revenue_naira: number }[];
  totals: {
    applications: number;
    applications_this_month: number;
    applications_last_month: number;
    cards_issued: number;
    new_cards_issued?: number;
    renewal_cards_issued?: number;
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
  new_cards_issued?: number;
  renewal_cards_issued?: number;
  todays_appointments: number;
  sla: { target_working_days: number; overdue: number; assigned_to_me: number };
  card_stock: { remaining: number; low: boolean };
  queue_today: Record<string, number>;
  my_activity: {
    decided: number;
    captured: number;
    cards_approved: number;
    assisted_created?: number;
    cards_printed?: number;
    cards_collected?: number;
  };
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
  const { t } = useI18n();
  const [s, setS] = useState<Summary | null>(() => {
    if (typeof window === "undefined") return null;
    try {
      const cached = sessionStorage.getItem("nis_staff_dashboard");
      return cached ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<Summary>("staff", "dashboard")
      .then((data) => {
        setS(data);
        try {
          sessionStorage.setItem("nis_staff_dashboard", JSON.stringify(data));
        } catch {}
      })
      .catch((e) => {
        if (!s) setError(e.message);
      });
  }, []);

  if (error && !s) return <Alert tone="danger">{error}</Alert>;
  if (!s || !s.analytics?.totals || !s.queue) return <Spinner />;

  const a = s.analytics;
  const totals = a.totals ?? {
    applications: 0,
    applications_this_month: 0,
    applications_last_month: 0,
    cards_issued: 0,
    new_cards_issued: 0,
    renewal_cards_issued: 0,
    revenue_naira: 0,
    revenue_this_month_naira: 0,
    payments: 0,
  };
  const delta = (totals.applications_this_month ?? 0) - (totals.applications_last_month ?? 0);
  const months = (a.monthly ?? []).map((m) => monthLabel(m.month));
  const pipeline = PIPELINE.map(([key, label]) => ({ label, value: s.queue?.[key] ?? 0, href: `/staff/applications?status=${key}` }));

  return (
    <div className="space-y-6">
      <PageTitle title="Dashboard" subtitle={`${user.command} · ${user.role_label}`} />

      {/* Headline figures */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          label={t("Applications this month")}
          value={String(totals.applications_this_month ?? 0)}
          note={
            <>
              <span className={delta > 0 ? "text-nis-primary" : delta < 0 ? "text-nis-red" : ""}>{delta > 0 ? "▲" : delta < 0 ? "▼" : "•"} {delta > 0 ? "+" : ""}{delta}</span> {t("vs last month")} · {totals.applications ?? 0} {t("in total")}
            </>
          }
        />
        <Kpi
          label={t("Cards issued")}
          value={String(totals.cards_issued ?? 0)}
          note={`${(s.cards?.ISSUED ?? 0) + (s.cards?.RENEWED ?? 0)} ${t("currently active")}`}
        />
        <Kpi
          label={t("New Cards Issued")}
          value={String(totals.new_cards_issued ?? s.new_cards_issued ?? 0)}
          note={t("Officially issued new cards")}
        />
        <Kpi
          label={t("Renewal Cards Issued")}
          value={String(totals.renewal_cards_issued ?? s.renewal_cards_issued ?? 0)}
          note={t("Officially issued renewal cards")}
        />
      </div>

      {/* Work queues */}
      <section aria-label="Work queues">
        <h2 className="mb-2 text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">{t("Work queues")}</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <Tile label={t("Paid – not submitted")} value={s.paid_awaiting_submission ?? 0} href="/staff/payments?filter=awaiting_submission" tone="amber" />
          <Tile label={t("Awaiting approval")} value={s.queue?.PENDING_APPROVAL ?? 0} href="/staff/applications?status=PENDING_APPROVAL" tone="amber" />
          <Tile label={t("Queried")} value={s.queue?.QUERIED ?? 0} href="/staff/applications?status=QUERIED" />
          <Tile label={t("Biometrics today")} value={s.todays_appointments ?? 0} href="/staff/applications?status=APPROVED_FOR_BIOMETRICS" />
          <Tile label={t("Cards to approve")} value={s.cards?.APPROVED ?? 0} href="/staff/cards?status=APPROVED" tone="amber" />
          <Tile label={t("Ready for collection")} value={s.queue?.READY_FOR_COLLECTION ?? 0} href="/staff/card-issuance?stage=awaiting_collection" tone="green" />
          <Tile label={t("Active cards")} value={(s.cards?.ISSUED ?? 0) + (s.cards?.RENEWED ?? 0)} href="/staff/cards" tone="green" />
          <Tile label={t("Expiring in 30 days")} value={s.cards_expiring_30_days ?? 0} />
          <Tile label={t("Expired cards")} value={s.cards_expired ?? 0} href="/staff/cards?expired=1" />
          <Tile label={t("Watchlisted")} value={s.watchlisted ?? 0} href="/staff/cards?watchlisted=1" tone="red" />
        </div>
      </section>

      {/* Trends */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title="Applications and cards issued"
          subtitle="Last 12 months"
          legend={<Legend items={[{ label: "Applications submitted", color: SERIES[0], kind: "line" }, { label: "Cards issued", color: SERIES[1], kind: "line" }]} />}
          table={<DataTable head={["Month", "Applications", "Cards issued"]} rows={(a.monthly ?? []).map((m) => [monthLong(m.month), m.submitted, m.issued])} />}
        >
          <LineChart labels={months} series={[{ name: "Applications submitted", values: (a.monthly ?? []).map((m) => m.submitted) }, { name: "Cards issued", values: (a.monthly ?? []).map((m) => m.issued) }]} />
        </ChartCard>
        <ChartCard
          title="Fees collected"
          subtitle="Confirmed Paystack payments per month (₦)"
          table={<DataTable head={["Month", "Fees (₦)"]} rows={(a.monthly ?? []).map((m) => [monthLong(m.month), naira(m.revenue_naira)])} />}
        >
          <ColumnChart name="Fees collected per month" labels={months} values={(a.monthly ?? []).map((m) => m.revenue_naira)} format={(n) => `₦${compact(n)}`} />
        </ChartCard>
      </div>

      {/* Pipeline and nationalities */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard title="Application pipeline" subtitle="Applications at each stage now" table={<DataTable head={["Stage", "Applications"]} rows={pipeline.map((p) => [p.label, p.value])} />}>
          <BarList items={pipeline} />
        </ChartCard>
        <ChartCard title="Top nationalities" subtitle="All applications" table={<DataTable head={["Nationality", "Applications"]} rows={(a.nationalities ?? []).map((n) => [n.label, n.total])} />}>
          <BarList items={(a.nationalities ?? []).map((n) => ({ label: n.label, value: n.total }))} />
        </ChartCard>
      </div>

      {/* Applications by Type & Applications by Gender */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title="Applications by Type"
          subtitle="New vs Renewal"
          table={
            <DataTable
              head={["Application Type", "Total"]}
              rows={[
                ["New", a.mix?.type?.NEW ?? 0],
                ["Renewal", a.mix?.type?.RENEWAL ?? 0],
              ]}
            />
          }
        >
          <div className="py-2">
            <VerticalTwoBarChart
              items={[
                { label: "New", value: a.mix?.type?.NEW ?? 0, color: "#2b892b" },
                { label: "Renewal", value: a.mix?.type?.RENEWAL ?? 0, color: "#58b258" },
              ]}
            />
          </div>
        </ChartCard>

        <ChartCard
          title={t("Applications by Gender")}
          subtitle={t("Male vs Female")}
          table={
            <DataTable
              head={[t("Gender"), t("Total")]}
              rows={[
                [t("Male"), a.mix?.sex?.MALE ?? a.mix?.sex?.Male ?? 0],
                [t("Female"), a.mix?.sex?.FEMALE ?? a.mix?.sex?.Female ?? 0],
              ]}
            />
          }
        >
          <div className="py-2">
            <DoughnutChart
              items={[
                { label: t("Male"), value: a.mix?.sex?.MALE ?? a.mix?.sex?.Male ?? 0, color: "#2E7D32" },
                { label: t("Female"), value: a.mix?.sex?.FEMALE ?? a.mix?.sex?.Female ?? 0, color: "#8E44AD" },
              ]}
              centerTitle={t("Gender")}
              centerSubtitle={t("Distribution")}
            />
          </div>
        </ChartCard>
      </div>

      <Panel title={`Officer performance (${user.role_label})`}>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <Tile label="Assisted forms" value={s.my_activity?.assisted_created ?? 0} />
          <Tile label="Vetting & decisions" value={s.my_activity?.decided ?? 0} />
          <Tile label="Biometrics captured" value={s.my_activity?.captured ?? 0} />
          <Tile label="Cards approved" value={s.my_activity?.cards_approved ?? 0} />
          <Tile label="Cards printed" value={s.my_activity?.cards_printed ?? 0} />
          <Tile label="Cards issued out" value={s.my_activity?.cards_collected ?? 0} />
        </div>
      </Panel>
    </div>
  );
}
