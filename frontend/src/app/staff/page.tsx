"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  BarList,
  ChartCard,
  DataTable,
  DoughnutChart,
  Legend,
  LineChart,
  ProcessingOverviewChart,
  SERIES,
  StateHorizontalBarChart,
  Top5StatesChart,
  VerticalTwoBarChart,
} from "@/components/charts";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";

type RecentActivityItem = {
  id: number;
  application_number: string;
  type: string;
  to_status: string;
  status_label: string;
  officer_name: string;
  created_at: string;
};

type StateCountItem = {
  state: string;
  total: number;
};

type TopStateItem = {
  rank: number;
  state: string;
  total: number;
};

type ProcessingOverview = {
  cards_produced: number;
  cards_issued: number;
  new_cards_issued: number;
  renewal_cards_issued: number;
  queried: number;
  rejected: number;
};

type Analytics = {
  monthly: {
    month: string;
    submitted: number;
    new_applications?: number;
    renewal_applications?: number;
    issued: number;
    revenue_naira: number;
  }[];
  totals: {
    applications: number;
    applications_this_month: number;
    applications_last_month: number;
    cards_issued: number;
    cards_produced?: number;
    new_cards_produced?: number;
    renewal_cards_produced?: number;
    new_cards_issued?: number;
    renewal_cards_issued?: number;
    revenue_naira: number;
    revenue_this_month_naira: number;
    payments: number;
  };
  decisions: { approved: number; queried: number; rejected: number; approval_rate: number | null };
  processing_days: { submission_to_decision: number; decision_to_biometrics: number; biometrics_to_ready: number; ready_to_collection: number };
  nationalities: { label: string; total: number }[];
  applications_by_state?: StateCountItem[];
  top5_states?: TopStateItem[];
  processing_overview?: ProcessingOverview;
  mix: { type: Record<string, number>; channel: Record<string, number>; sex: Record<string, number> };
  appointments: { date: string; total: number }[];
  recent_activity?: RecentActivityItem[];
};

type Summary = {
  queue: Record<string, number>;
  cards: Record<string, number>;
  cards_expired: number;
  cards_expiring_30_days: number;
  watchlisted: number;
  paid_awaiting_submission: number;
  total_cards_produced?: number;
  new_cards_produced?: number;
  renewal_cards_produced?: number;
  new_cards_issued?: number;
  renewal_cards_issued?: number;
  applications_by_state?: StateCountItem[];
  top5_states?: TopStateItem[];
  processing_overview?: ProcessingOverview;
  todays_appointments: number;
  sla: { target_working_days: number; overdue: number; assigned_to_me: number };
  card_stock: { remaining: number; low: boolean };
  queue_today: Record<string, number>;
  analytics: Analytics;
};

const monthLabel = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "short" });
const monthLong = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "long", year: "numeric" });

function StatCard({ label, value, href }: { label: string; value: string | number; href?: string }) {
  const content = (
    <div className="h-full min-h-[92px] sm:min-h-[96px] rounded-xl border border-slate-200/90 bg-white px-4 py-3 shadow-xs transition hover:border-nis-primary/40 hover:shadow-sm flex flex-col justify-between">
      <div className="text-[12px] sm:text-[13px] font-medium tracking-wide text-slate-500 uppercase leading-snug whitespace-normal" title={label}>{label}</div>
      <div className="text-xl sm:text-2xl font-medium sm:font-semibold text-slate-900 tracking-tight tabular-nums mt-1">{value}</div>
    </div>
  );
  return href ? <Link href={href} className="block h-full">{content}</Link> : content;
}

export default function StaffDashboard() {
  const user = useStaff();
  const { t } = useI18n();
  const [s, setS] = useState<Summary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = (isRefresh = false) => {
    setLoading(true);
    setError(null);
    const endpoint = isRefresh ? "dashboard?refresh=1" : "dashboard";
    api<Summary>("staff", endpoint)
      .then((data) => {
        setS(data);
        setError(null);
      })
      .catch((e) => {
        setError(e.message || "Unable to connect to the database reporting service.");
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchDashboardData(false);
  }, []);

  if (loading && !s) return <Spinner label={t("Loading dashboard statistics…")} />;
  if (!s && error) {
    return (
      <div className="space-y-4">
        <PageTitle title={t("Dashboard")} subtitle={`${user.command} · ${user.role_label}`} />
        <Alert tone="danger">{error}</Alert>
        <button
          type="button"
          onClick={() => fetchDashboardData(true)}
          className="inline-flex items-center gap-2 rounded-lg bg-nis-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-nis-primary-dark"
        >
          <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
          <span>{t("Retry Loading Dashboard")}</span>
        </button>
      </div>
    );
  }
  if (!s || !s.analytics?.totals || !s.queue) return <Spinner label={t("Loading dashboard statistics…")} />;

  const a = s.analytics;
  const totals = a.totals ?? {
    applications: 0,
    applications_this_month: 0,
    applications_last_month: 0,
    cards_produced: 0,
    new_cards_produced: 0,
    renewal_cards_produced: 0,
    cards_issued: 0,
    new_cards_issued: 0,
    renewal_cards_issued: 0,
    revenue_naira: 0,
    revenue_this_month_naira: 0,
    payments: 0,
  };

  const months = (a.monthly ?? []).map((m) => monthLabel(m.month));

  const processingStages = [
    { label: t("Awaiting Vetting"), value: s.queue?.PENDING_APPROVAL ?? 0, href: "/staff/applications?status=PENDING_APPROVAL" },
    { label: t("Queried"), value: s.queue?.QUERIED ?? 0, href: "/staff/applications?status=QUERIED" },
    { label: t("Awaiting Approval"), value: s.queue?.VETTED ?? 0, href: "/staff/approvals" },
    { label: t("Approved & Awaiting Biometrics"), value: s.queue?.APPROVED_FOR_BIOMETRICS ?? 0, href: "/staff/applications?status=APPROVED_FOR_BIOMETRICS" },
    { label: t("Biometrics Completed"), value: s.queue?.BIOMETRICS_CAPTURED ?? 0, href: "/staff/applications?status=BIOMETRICS_CAPTURED" },
    { label: t("Awaiting Card Production"), value: s.cards?.APPROVED ?? 0, href: "/staff/cards?status=APPROVED" },
    { label: t("Awaiting Card Issuance"), value: s.queue?.READY_FOR_COLLECTION ?? 0, href: "/staff/card-issuance?stage=awaiting_collection" },
    { label: t("Total Cards Issued"), value: totals.cards_issued ?? 0, href: "/staff/reports" },
  ];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Dashboard")}
        subtitle={`${user.command} · ${user.role_label}`}
        actions={
          <button
            type="button"
            onClick={() => fetchDashboardData(true)}
            disabled={loading}
            className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
              <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
              <path d="M3 3v5h5" />
              <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
              <path d="M21 21v-5h-5" />
            </svg>
            <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
          </button>
        }
      />

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 flex items-center justify-between gap-3">
          <div>
            <span className="font-semibold">{t("Notice")}:</span> {error}
          </div>
          <button
            type="button"
            onClick={() => fetchDashboardData(true)}
            className="shrink-0 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
          >
            {t("Retry")}
          </button>
        </div>
      )}

      {/* Primary Application & Card Statistics */}
      <div>
        <h2 className="mb-2 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Application & Card Statistics")}
        </h2>
        <div className="grid grid-cols-1 min-[380px]:grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
          <StatCard
            label={t("Total Applications")}
            value={totals.applications ?? 0}
            href="/staff/applications"
          />
          <StatCard
            label={t("Applications This Month")}
            value={totals.applications_this_month ?? 0}
          />
          <StatCard
            label={t("Biometrics Completed")}
            value={s.queue?.BIOMETRICS_CAPTURED ?? 0}
            href="/staff/applications?status=BIOMETRICS_CAPTURED"
          />
          <StatCard
            label={t("Total Cards Produced")}
            value={totals.cards_produced ?? s.total_cards_produced ?? 0}
            href="/staff/cards"
          />
          <StatCard
            label={t("Total Cards Issued")}
            value={totals.cards_issued ?? 0}
            href="/staff/card-issuance"
          />
          <StatCard
            label={t("New Cards Issued")}
            value={totals.new_cards_issued ?? s.new_cards_issued ?? 0}
            href="/staff/reports?category=NEW"
          />
          <StatCard
            label={t("Renewal Cards Issued")}
            value={totals.renewal_cards_issued ?? s.renewal_cards_issued ?? 0}
            href="/staff/reports?category=RENEWAL"
          />
        </div>
      </div>

      {/* Operational Charts Row 1: Monthly Trends (New vs Renewal) & Processing Overview */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title={t("Monthly Application Trends")}
          subtitle={t("New vs Renewal applications submitted over the last 12 months")}
          legend={
            <Legend
              items={[
                { label: t("New Applications"), color: SERIES[0], kind: "line" },
                { label: t("Renewal Applications"), color: SERIES[1], kind: "line" },
              ]}
            />
          }
          table={
            <DataTable
              head={[t("Month"), t("New Applications"), t("Renewal Applications")]}
              rows={(a.monthly ?? []).map((m) => [
                monthLong(m.month),
                m.new_applications ?? m.submitted,
                m.renewal_applications ?? 0,
              ])}
            />
          }
        >
          <LineChart
            labels={months}
            series={[
              { name: t("New Applications"), values: (a.monthly ?? []).map((m) => m.new_applications ?? m.submitted) },
              { name: t("Renewal Applications"), values: (a.monthly ?? []).map((m) => m.renewal_applications ?? 0) },
            ]}
          />
        </ChartCard>

        <ChartCard
          title={t("Application Processing Overview")}
          subtitle={t("Outcomes for card production, issuance, queries and rejections")}
          table={
            <DataTable
              head={[t("Processing Outcome"), t("Total Count"), t("Classification / Status")]}
              rows={[
                [t("Total Cards Produced"), procOverview.cards_produced, t("Physical production confirmed")],
                [t("Total Cards Issued"), procOverview.cards_issued, t("Officially issued to applicants")],
                [`  ↳ ${t("New Cards Issued")}`, procOverview.new_cards_issued, t("Breakdown of Total Issued")],
                [`  ↳ ${t("Renewal Cards Issued")}`, procOverview.renewal_cards_issued, t("Breakdown of Total Issued")],
                [t("Queried Applications"), procOverview.queried, t("Currently queried (action required)")],
                [t("Rejected Applications"), procOverview.rejected, t("Currently rejected")],
              ]}
            />
          }
        >
          <ProcessingOverviewChart data={procOverview} t={t} />
        </ChartCard>
      </div>

      {/* Operational Charts Row 2: State Distribution & Top 5 States */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title={t("Total Applications by State")}
          subtitle={t("Distribution based on biometric enrollment State / Command")}
          table={
            <DataTable
              head={[t("State / Location"), t("Applications")]}
              rows={statesData.map((st) => [
                st.state.toLowerCase() === "not yet assigned" ? t("Not Yet Assigned") : st.state,
                st.total,
              ])}
            />
          }
        >
          <div className="max-h-[340px] overflow-y-auto pr-2">
            <StateHorizontalBarChart items={statesData} notAssignedLabel={t("Not Yet Assigned")} />
          </div>
        </ChartCard>

        <ChartCard
          title={t("Top 5 States by Applications")}
          subtitle={t("Highest volume biometric enrollment States")}
          table={
            <DataTable
              head={[t("Rank"), t("State"), t("Applications")]}
              rows={top5Data.map((st) => [`#${st.rank}`, st.state, st.total])}
            />
          }
        >
          <Top5StatesChart items={top5Data} />
        </ChartCard>
      </div>

      {/* Operational Charts Row 3: Top Nationalities & Applications by Gender */}
      <div className="grid gap-6 xl:grid-cols-2">
        <ChartCard
          title={t("Top Nationalities")}
          subtitle={t("ECOWAS member state applicants")}
          table={
            <DataTable
              head={[t("Nationality"), t("Applications")]}
              rows={(a.nationalities ?? []).map((n) => [n.label, n.total])}
            />
          }
        >
          <BarList items={(a.nationalities ?? []).map((n) => ({ label: n.label, value: n.total }))} />
        </ChartCard>

        <ChartCard
          title={t("Applications by Gender")}
          subtitle={t("Male vs Female")}
          legend={
            <Legend
              items={[
                { label: t("Male"), color: "#2E7D32" },
                { label: t("Female"), color: "#8E44AD" },
              ]}
            />
          }
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
            {(a.mix?.sex?.MALE ?? 0) === 0 && (a.mix?.sex?.FEMALE ?? 0) === 0 ? (
              <div className="flex h-48 items-center justify-center text-xs text-slate-400">
                {t("No gender records available")}
              </div>
            ) : (
              <DoughnutChart
                items={[
                  { label: t("Male"), value: a.mix?.sex?.MALE ?? a.mix?.sex?.Male ?? 0, color: "#2E7D32" },
                  { label: t("Female"), value: a.mix?.sex?.FEMALE ?? a.mix?.sex?.Female ?? 0, color: "#8E44AD" },
                ]}
                centerTitle={t("Gender")}
                centerSubtitle={t("Distribution")}
              />
            )}
          </div>
        </ChartCard>
      </div>

      {/* Feature E: Recent Operational Activity */}
      {Array.isArray(a.recent_activity) && a.recent_activity.length > 0 && (
        <Panel title={t("Recent Operational Activity")}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-medium">
                <tr>
                  <th className="px-4 py-2.5">{t("Application Number")}</th>
                  <th className="px-4 py-2.5">{t("Type")}</th>
                  <th className="px-4 py-2.5">{t("Action / Status")}</th>
                  <th className="px-4 py-2.5">{t("Officer / System")}</th>
                  <th className="px-4 py-2.5">{t("Timestamp")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {a.recent_activity.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="px-4 py-2.5 font-mono font-medium text-slate-800">
                      <Link href={`/staff/applications?search=${item.application_number}`} className="hover:text-nis-primary hover:underline">
                        {item.application_number}
                      </Link>
                    </td>
                    <td className="px-4 py-2.5 text-slate-600">{item.type}</td>
                    <td className="px-4 py-2.5 font-medium text-slate-900">{t(item.status_label)}</td>
                    <td className="px-4 py-2.5 text-slate-600">{item.officer_name}</td>
                    <td className="px-4 py-2.5 text-slate-400">{item.created_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>
      )}
    </div>
  );
}
