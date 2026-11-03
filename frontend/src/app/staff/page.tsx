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
import type { StaffUser } from "@/lib/types";

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

type RoleData = {
  role: string;
  stats?: Record<string, any>;
  vetted_by_me?: {
    id: number;
    application_number: string;
    reference_number: string;
    applicant_name: string;
    type: string;
    vetted_at: string;
    status: string;
  }[];
  pending_queue?: {
    id: number;
    application_number: string;
    reference_number: string;
    applicant_name: string;
    type: string;
    submitted_at: string;
    status: string;
  }[];
  awaiting_approval?: {
    id: number;
    application_number: string;
    reference_number: string;
    applicant_name: string;
    type: string;
    vetted_by_name: string;
    vetted_at: string;
    status: string;
  }[];
  appointments?: {
    id: number;
    application_number: string;
    reference_number: string;
    applicant_name: string;
    appointment_date: string;
    appointment_time: string;
    status: string;
  }[];
  production_queue?: {
    id: number;
    card_number: string;
    application_number: string;
    applicant_name: string;
    date_approved: string;
    production_center: string;
  }[];
  dispatches?: {
    id: number;
    dispatch_reference: string;
    destination_name: string;
    card_number: string;
    application_number: string;
    waybill_number: string;
    dispatched_at: string;
    status: string;
  }[];
  incoming_consignments?: {
    id: number;
    dispatch_reference: string;
    waybill_number: string;
    card_number: string;
    application_number: string;
    dispatched_at: string;
    status: string;
  }[];
  available_cards?: {
    id: number;
    card_reference: string;
    application_reference: string;
    applicant_name: string;
    card_type: string;
    receiving_command: string;
    status: string;
  }[];
  commands?: {
    id: number;
    name: string;
    state?: string;
    applications_count: number;
    cards_issued: number;
  }[];
  recent_applications?: {
    id: number;
    application_number: string;
    applicant_name: string;
    type: string;
    status: string;
    created_at: string;
  }[];
  recent_events?: {
    id: number;
    timestamp: string;
    action: string;
    description: string;
    ip_address: string;
    user_name: string;
  }[];
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
  role_data?: RoleData;
  user_role?: string;
};

const monthLabel = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "short" });
const monthLong = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString("en-GB", { month: "long", year: "numeric" });

function StatCard({
  label,
  value,
  href,
  icon,
}: {
  label: string;
  value: string | number;
  href?: string;
  icon?: React.ReactNode;
}) {
  const content = (
    <div className="h-full min-h-[96px] sm:min-h-[105px] rounded-xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-xs transition hover:border-nis-primary/40 hover:shadow-sm flex flex-col justify-between">
      <div className="flex items-center justify-between gap-3">
        <span
          className="text-[13px] sm:text-sm font-medium tracking-wide text-slate-500 uppercase leading-snug whitespace-normal"
          title={label}
        >
          {label}
        </span>
        {icon && (
          <div className="shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-slate-50 border border-slate-100 text-slate-500">
            {icon}
          </div>
        )}
      </div>
      <div className="text-[22px] sm:text-2xl font-medium text-slate-900 tracking-tight tabular-nums mt-1.5">
        {value}
      </div>
    </div>
  );
  return href ? (
    <Link href={href} className="block h-full group focus:outline-none">
      {content}
    </Link>
  ) : (
    content
  );
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
        <PageTitle
          title={t("Dashboard")}
          subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        />
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

  const role = user.role;
  const isGeneralAdmin = role === "SuperAdmin" || role === "HeadquartersAdmin" || role === "Admin";
  const isVetting = role === "VettingOfficer" || role === "DataEntryOfficer" || role === "Inspector";
  const isApprover = role === "ApprovingOfficer";
  const isBiometrics = role === "BiometricsOfficer" || role === "EnrollmentOfficer";
  const isCardProduction = role === "CardProductionOfficer" || role === "ProductionOfficer";
  const isCardDistribution = role === "CardDistributionOfficer";
  const isCardReceiving = role === "CardReceivingOfficer";
  const isCardIssuance = role === "CardIssuanceOfficer" || role === "IssuingOfficer";
  const isZonalAdmin = role === "ZonalAdmin";
  const isCommandAdmin = role === "CommandAdmin";
  const isAuditor = role === "ReportingAuditOfficer" || role === "Auditor";

  // Dedicated role-specific view dispatch
  if (isVetting) return renderVettingOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isApprover) return renderApprovingOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isBiometrics) return renderBiometricsOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isCardProduction) return renderCardProductionOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isCardDistribution) return renderCardDistributionOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isCardReceiving) return renderCardReceivingOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isCardIssuance) return renderCardIssuanceOfficerDashboard(s, user, t, loading, fetchDashboardData);
  if (isZonalAdmin) return renderZonalAdminDashboard(s, user, t, loading, fetchDashboardData);
  if (isCommandAdmin) return renderCommandAdminDashboard(s, user, t, loading, fetchDashboardData);
  if (isAuditor) return renderAuditOfficerDashboard(s, user, t, loading, fetchDashboardData);

  // Default: General Admin Dashboard (Preserved for Super Administrator, HQ Administrator & Admin)
  return renderGeneralAdminDashboard(s, user, t, loading, fetchDashboardData, error);
}

// ==========================================
// 1. VETTING OFFICER DASHBOARD
// ==========================================
function renderVettingOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const vettedByMe = s.role_data?.vetted_by_me || [];
  const pendingQueue = s.role_data?.pending_queue || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Vetting Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/applications"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg>
              <span>{t("Open Vetting Queue")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      {/* Vetting Operational Statistics */}
      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Vetting Queue & Officer Performance")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Total Assigned for Vetting")}
            value={stats.assigned_for_vetting ?? 0}
            href="/staff/applications?assigned=me"
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>}
          />
          <StatCard
            label={t("Awaiting Vetting")}
            value={stats.awaiting_vetting ?? 0}
            href="/staff/applications"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Applications Vetted by Me")}
            value={stats.vetted_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>}
          />
          <StatCard
            label={t("Applications Queried by Me")}
            value={stats.queried_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>}
          />
        </div>
      </div>

      {/* Main Table: Applications Vetted by Me */}
      <Panel title={t("Applications Vetted by Me")}>
        {vettedByMe.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No applications have been vetted under your officer account yet.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Application Reference")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Application Type")}</th>
                  <th className="py-3">{t("Date Vetted")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {vettedByMe.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-emerald-800">
                      {item.application_number}
                    </td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-600">{item.type}</td>
                    <td className="py-3 text-slate-500">{item.vetted_at ? new Date(item.vetted_at).toLocaleString("en-GB") : "—"}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/applications/${item.id}`}
                        className="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        {t("View Details")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {/* Awaiting Vetting Queue Preview */}
      {pendingQueue.length > 0 && (
        <Panel title={t("Pending Applications Awaiting Vetting")}>
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Application Ref")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Submission Date")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {pendingQueue.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-slate-900">{item.application_number}</td>
                    <td className="py-3 text-slate-800">{item.applicant_name}</td>
                    <td className="py-3 text-slate-500">{item.submitted_at ? new Date(item.submitted_at).toLocaleDateString("en-GB") : "—"}</td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/applications/${item.id}`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Start Vetting")}
                      </Link>
                    </td>
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

// ==========================================
// 2. APPROVING OFFICER DASHBOARD
// ==========================================
function renderApprovingOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const awaitingApproval = s.role_data?.awaiting_approval || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Approving Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/approvals"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2z"/></svg>
              <span>{t("Open Approvals Queue")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 flex items-center justify-between">
        <div>
          <span className="font-bold uppercase tracking-wider">{t("Workflow Notice")}:</span> {t("Vetting records are available for read-only inspection. Approving officers review biometric completion and verified supporting documents before granting statutory approval.")}
        </div>
        <Link href="/staff/applications" className="font-semibold underline ml-4 shrink-0 hover:text-blue-950">
          {t("View Vetted Applications")}
        </Link>
      </div>

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Approval Queue & Jurisdiction Statistics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Applications Awaiting Approval")}
            value={stats.awaiting_approval ?? 0}
            href="/staff/approvals"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Applications Approved by Me")}
            value={stats.approved_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>}
          />
          <StatCard
            label={t("Applications Queried by Me")}
            value={stats.queried_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>}
          />
          <StatCard
            label={t("Vetted Applications in Jurisdiction")}
            value={stats.vetted_applications ?? 0}
            href="/staff/applications"
            icon={<svg className="w-4 h-4 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Applications Awaiting Final Approval")}>
        {awaitingApproval.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("There are currently no applications pending your statutory approval.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Application Ref")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Vetted By")}</th>
                  <th className="py-3">{t("Date Vetted")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {awaitingApproval.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-emerald-800">{item.application_number}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-700">{item.vetted_by_name}</td>
                    <td className="py-3 text-slate-500">{item.vetted_at ? new Date(item.vetted_at).toLocaleDateString("en-GB") : "—"}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/approvals`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Review & Decide")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 3. BIOMETRICS OFFICER DASHBOARD
// ==========================================
function renderBiometricsOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const appointments = s.role_data?.appointments || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Biometrics Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/queue"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
              <span>{t("Open Enrollment Desk")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Biometric Enrollment Metrics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Applications Awaiting Biometrics")}
            value={stats.awaiting_biometrics ?? 0}
            href="/staff/queue"
            icon={<svg className="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><path d="M12 6a4 4 0 1 0 4 4 4 4 0 0 0-4-4z"/></svg>}
          />
          <StatCard
            label={t("Today's Appointments")}
            value={stats.todays_appointments ?? 0}
            href="/staff/queue"
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>}
          />
          <StatCard
            label={t("Biometrics Completed by Me")}
            value={stats.biometrics_completed_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>}
          />
          <StatCard
            label={t("Pending Booking / Walk-in")}
            value={stats.pending_enrollment ?? 0}
            icon={<svg className="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Scheduled Enrollment Queue & Appointments")}>
        {appointments.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No biometric capture appointments scheduled for this enrollment station.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Application Ref")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Appointment Date")}</th>
                  <th className="py-3">{t("Time Slot")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {appointments.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-emerald-800">{item.application_number}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-700">{item.appointment_date}</td>
                    <td className="py-3 text-slate-600 font-medium">{item.appointment_time}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/queue`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Capture Biometrics")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 4. CARD PRODUCTION OFFICER DASHBOARD
// ==========================================
function renderCardProductionOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const queue = s.role_data?.production_queue || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Card Production Officer Dashboard")}
        subtitle={`NIS Service Headquarters Sauka Abuja · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/cards"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
              <span>{t("Card Print Console")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-900">
        <span className="font-bold uppercase tracking-wider">{t("Centralised Facility")}:</span> {t("Card personalisation and chip encoding operate strictly at the NIS Headquarters Production Facility Sauka Abuja. Produced cards are dispatched under security seal to designated State Commands.")}
      </div>

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Production Center Throughput")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Cards Awaiting Production")}
            value={stats.cards_awaiting_production ?? 0}
            href="/staff/cards"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Cards Produced by Me")}
            value={stats.cards_produced_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>}
          />
          <StatCard
            label={t("Total Cards Produced")}
            value={stats.total_cards_produced ?? 0}
            icon={<svg className="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="8" x2="17" y2="8"/><line x1="15" y1="12" x2="17" y2="12"/></svg>}
          />
          <StatCard
            label={t("Spoiled Cards Recorded")}
            value={stats.spoiled_cards_recorded ?? 0}
            icon={<svg className="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Card Production Queue (Awaiting Print & Chip Encoding)")}>
        {queue.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No residence cards currently waiting in the personalisation print queue.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Card Number")}</th>
                  <th className="py-3">{t("Application Ref")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Date Approved")}</th>
                  <th className="py-3">{t("Facility")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {queue.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-emerald-800">{item.card_number}</td>
                    <td className="py-3 font-mono text-slate-700">{item.application_number}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-600">{item.date_approved}</td>
                    <td className="py-3 text-xs text-slate-500">{item.production_center}</td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/cards`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Print Card")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 5. CARD DISTRIBUTION OFFICER DASHBOARD
// ==========================================
function renderCardDistributionOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const dispatches = s.role_data?.dispatches || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Card Distribution Officer Dashboard")}
        subtitle={`NIS Headquarters Sauka · Logistics & Dispatch · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/card-distribution"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2zM3 13h18"/></svg>
              <span>{t("Prepare Dispatch Batch")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Logistics & Dispatch Metrics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Cards Awaiting Dispatch")}
            value={stats.cards_awaiting_dispatch ?? 0}
            href="/staff/card-distribution?status=AWAITING_DISPATCH"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Cards Dispatched by Me")}
            value={stats.cards_dispatched_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>}
          />
          <StatCard
            label={t("Cards In Transit (Awaiting Receipt)")}
            value={stats.cards_awaiting_receipt ?? 0}
            href="/staff/card-distribution?status=DISPATCHED"
            icon={<svg className="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>}
          />
          <StatCard
            label={t("Completed Dispatches")}
            value={stats.completed_dispatches ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Consignment Dispatches to Zones & Commands")}>
        {dispatches.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No card dispatch records logged yet.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Batch Reference")}</th>
                  <th className="py-3">{t("Destination")}</th>
                  <th className="py-3">{t("Waybill No")}</th>
                  <th className="py-3">{t("Dispatch Date")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {dispatches.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-slate-900">{item.dispatch_reference}</td>
                    <td className="py-3 font-semibold text-slate-800">{item.destination_name}</td>
                    <td className="py-3 font-mono text-slate-600">{item.waybill_number}</td>
                    <td className="py-3 text-slate-500">{item.dispatched_at ? new Date(item.dispatched_at).toLocaleString("en-GB") : "—"}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/card-distribution`}
                        className="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        {t("View Consignment")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 6. CARD RECEIVING OFFICER DASHBOARD
// ==========================================
function renderCardReceivingOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const consignments = s.role_data?.incoming_consignments || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Card Receiving Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/card-distribution"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
              <span>{t("Confirm Incoming Batch")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Command Receipt Metrics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Cards Dispatched to My Location")}
            value={stats.cards_dispatched_to_my_location ?? 0}
            href="/staff/card-distribution?status=DISPATCHED"
            icon={<svg className="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>}
          />
          <StatCard
            label={t("Awaiting Receipt Confirmation")}
            value={stats.cards_awaiting_receipt ?? 0}
            href="/staff/card-distribution?status=DISPATCHED"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Cards Confirmed Received by Me")}
            value={stats.cards_received_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>}
          />
          <StatCard
            label={t("Cards Available for Issuance")}
            value={stats.cards_available_for_issuance ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Incoming Card Consignments Awaiting Physical Receipt")}>
        {consignments.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No in-transit consignments currently awaiting receipt confirmation at this location.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Batch Reference")}</th>
                  <th className="py-3">{t("Waybill / Consignment No")}</th>
                  <th className="py-3">{t("Card Reference")}</th>
                  <th className="py-3">{t("Dispatched At")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {consignments.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-slate-900">{item.dispatch_reference}</td>
                    <td className="py-3 font-mono text-slate-700">{item.waybill_number}</td>
                    <td className="py-3 font-mono text-emerald-800">{item.card_number}</td>
                    <td className="py-3 text-slate-500">{item.dispatched_at ? new Date(item.dispatched_at).toLocaleString("en-GB") : "—"}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/card-distribution`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Confirm Physical Receipt")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 7. CARD ISSUANCE OFFICER DASHBOARD
// ==========================================
function renderCardIssuanceOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const availableCards = s.role_data?.available_cards || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Card Issuance Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/card-issuance"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>{t("Issue Cards Console")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-900">
        <span className="font-bold uppercase tracking-wider">{t("Issuance Security Rule")}:</span> {t("Card Issuance is strictly separated from Card Production. Only cards that have completed HQ personalisation, security dispatch, and confirmed physical receipt at this Command may be issued to applicants upon biometric verification.")}
      </div>

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Command Issuance Statistics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <StatCard
            label={t("Cards Awaiting Issuance")}
            value={stats.cards_awaiting_issuance ?? 0}
            href="/staff/card-issuance"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Cards Issued by Me")}
            value={stats.cards_issued_by_me ?? 0}
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>}
          />
          <StatCard
            label={t("Total Cards Issued at My Assigned Command")}
            value={stats.total_cards_issued_at_location ?? 0}
            icon={<svg className="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Cards Available for Issuance within Jurisdiction")}>
        {availableCards.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No residence cards currently in Ready for Collection status at this location.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Card Reference")}</th>
                  <th className="py-3">{t("Application Reference")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Card Type")}</th>
                  <th className="py-3">{t("Receiving Command")}</th>
                  <th className="py-3">{t("Issuance Status")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {availableCards.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-bold text-emerald-800">{item.card_reference}</td>
                    <td className="py-3 font-mono text-slate-700">{item.application_reference}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-600">{item.card_type}</td>
                    <td className="py-3 text-slate-700">{item.receiving_command}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/card-issuance?search=${item.card_reference}`}
                        className="rounded-md bg-emerald-700 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-800 shadow-xs"
                      >
                        {t("Confirm Issuance")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 8. ZONAL ADMINISTRATOR DASHBOARD
// ==========================================
function renderZonalAdminDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const commands = s.role_data?.commands || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Zonal Administrator Dashboard")}
        subtitle={`${stats.zone_name || user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <button
            type="button"
            onClick={() => refresh(true)}
            disabled={loading}
            className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
            <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
          </button>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Zonal Operational Oversight")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <StatCard
            label={t("Total Applications in Zone")}
            value={stats.total_applications ?? 0}
            href="/staff/applications"
            icon={<svg className="w-4 h-4 text-emerald-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>}
          />
          <StatCard
            label={t("Awaiting Vetting")}
            value={stats.awaiting_vetting ?? 0}
            href="/staff/applications?status=PENDING_APPROVAL"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Awaiting Biometrics")}
            value={stats.awaiting_biometrics ?? 0}
            href="/staff/queue"
            icon={<svg className="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/></svg>}
          />
          <StatCard
            label={t("Cards Received in Zone")}
            value={stats.cards_received ?? 0}
            href="/staff/card-distribution"
            icon={<svg className="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="3" width="15" height="13"/></svg>}
          />
          <StatCard
            label={t("Cards Issued in Zone")}
            value={stats.cards_issued ?? 0}
            href="/staff/card-issuance"
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("State Commands Operational Breakdown within Zone")}>
        {commands.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No Command operational data recorded in this Zone.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("State Command")}</th>
                  <th className="py-3">{t("State")}</th>
                  <th className="py-3">{t("Applications Enrolled")}</th>
                  <th className="py-3">{t("Cards Issued")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {commands.map((cmd, idx) => (
                  <tr key={cmd.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-semibold text-slate-900">{cmd.name}</td>
                    <td className="py-3 text-slate-600">{cmd.state || "—"}</td>
                    <td className="py-3 font-mono font-medium text-slate-800">{cmd.applications_count}</td>
                    <td className="py-3 font-mono font-medium text-emerald-700">{cmd.cards_issued}</td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/applications?command_id=${cmd.id}`}
                        className="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        {t("Inspect Command")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 9. COMMAND ADMINISTRATOR DASHBOARD
// ==========================================
function renderCommandAdminDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const recentApps = s.role_data?.recent_applications || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Command Administrator Dashboard")}
        subtitle={`${stats.command_name || user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <button
            type="button"
            onClick={() => refresh(true)}
            disabled={loading}
            className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
            <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
          </button>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Command Processing & Issuance")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <StatCard
            label={t("Total Applications in Command")}
            value={stats.total_applications ?? 0}
            href="/staff/applications"
            icon={<svg className="w-4 h-4 text-emerald-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>}
          />
          <StatCard
            label={t("Awaiting Biometrics")}
            value={stats.awaiting_biometrics ?? 0}
            href="/staff/queue"
            icon={<svg className="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/></svg>}
          />
          <StatCard
            label={t("Cards Received at Command")}
            value={stats.cards_received ?? 0}
            href="/staff/card-distribution"
            icon={<svg className="w-4 h-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="3" width="15" height="13"/></svg>}
          />
          <StatCard
            label={t("Available for Issuance")}
            value={stats.cards_available_for_issuance ?? 0}
            href="/staff/card-issuance"
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>}
          />
          <StatCard
            label={t("Cards Issued to Applicants")}
            value={stats.cards_issued ?? 0}
            href="/staff/card-issuance"
            icon={<svg className="w-4 h-4 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Recent Applications Originating from Command")}>
        {recentApps.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No applications registered at this Command yet.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Application Ref")}</th>
                  <th className="py-3">{t("Applicant Name")}</th>
                  <th className="py-3">{t("Type")}</th>
                  <th className="py-3">{t("Status")}</th>
                  <th className="py-3">{t("Submission Date")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {recentApps.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 font-mono font-medium text-emerald-800">{item.application_number}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.applicant_name}</td>
                    <td className="py-3 text-slate-600">{item.type}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-600/20">
                        {item.status}
                      </span>
                    </td>
                    <td className="py-3 text-slate-500">{item.created_at}</td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/applications/${item.id}`}
                        className="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        {t("View Details")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 10. REPORTING / AUDIT OFFICER DASHBOARD
// ==========================================
function renderAuditOfficerDashboard(s: Summary, user: StaffUser, t: (k: string) => string, loading: boolean, refresh: (b: boolean) => void) {
  const stats = s.role_data?.stats || {};
  const recentEvents = s.role_data?.recent_events || [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Reporting / Audit Officer Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
        actions={
          <div className="flex items-center gap-2">
            <Link
              href="/staff/audit-logs"
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg bg-emerald-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-900"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/></svg>
              <span>{t("Full Audit Logs")}</span>
            </Link>
            <button
              type="button"
              onClick={() => refresh(true)}
              disabled={loading}
              className="inline-flex min-h-[38px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
            >
              <svg className={`h-3.5 w-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>
              <span>{loading ? t("Refreshing…") : t("Refresh Live Data")}</span>
            </button>
          </div>
        }
      />

      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Audit & Security Telemetry")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            label={t("Total System Audit Events")}
            value={stats.total_audit_events ?? 0}
            href="/staff/audit-logs"
            icon={<svg className="w-4 h-4 text-emerald-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/></svg>}
          />
          <StatCard
            label={t("Security & Access Events")}
            value={stats.security_events ?? 0}
            href="/staff/audit-logs"
            icon={<svg className="w-4 h-4 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>}
          />
          <StatCard
            label={t("Document Queries Raised")}
            value={stats.document_queries_count ?? 0}
            icon={<svg className="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>}
          />
          <StatCard
            label={t("Security & Geofence Logs")}
            value={stats.geofence_events ?? 0}
            icon={<svg className="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM12 8v4M12 16h.01"/></svg>}
          />
        </div>
      </div>

      <Panel title={t("Live Administrative & Security Audit Trail")}>
        {recentEvents.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">
            {t("No recent audit log events recorded.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">S/N</th>
                  <th className="py-3">{t("Timestamp")}</th>
                  <th className="py-3">{t("Officer / User")}</th>
                  <th className="py-3">{t("Action Type")}</th>
                  <th className="py-3">{t("IP / Station")}</th>
                  <th className="py-3">{t("Description")}</th>
                  <th className="pr-5 py-3 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {recentEvents.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3 text-slate-500 font-mono text-xs">{item.timestamp ? new Date(item.timestamp).toLocaleString("en-GB") : "—"}</td>
                    <td className="py-3 font-semibold text-slate-900">{item.user_name}</td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-mono font-medium text-slate-800">
                        {item.action}
                      </span>
                    </td>
                    <td className="py-3 font-mono text-xs text-slate-500">{item.ip_address}</td>
                    <td className="py-3 text-slate-700 max-w-xs truncate">{item.description}</td>
                    <td className="pr-5 py-3 text-right">
                      <Link
                        href={`/staff/audit-logs`}
                        className="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        {t("Inspect")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

// ==========================================
// 11. GENERAL ADMIN DASHBOARD (PRESERVED)
// ==========================================
function renderGeneralAdminDashboard(
  s: Summary,
  user: StaffUser,
  t: (k: string) => string,
  loading: boolean,
  fetchDashboardData: (b: boolean) => void,
  error: string | null
) {
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

  const procOverview: ProcessingOverview = a.processing_overview ?? s.processing_overview ?? {
    cards_produced: totals.cards_produced ?? s.total_cards_produced ?? 0,
    cards_issued: totals.cards_issued ?? 0,
    new_cards_issued: totals.new_cards_issued ?? s.new_cards_issued ?? 0,
    renewal_cards_issued: totals.renewal_cards_issued ?? s.renewal_cards_issued ?? 0,
    queried: 0,
    rejected: 0,
  };

  const statesData: StateCountItem[] = a.applications_by_state ?? s.applications_by_state ?? [];
  const top5Data: TopStateItem[] = a.top5_states ?? s.top5_states ?? [];

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Dashboard")}
        subtitle={`${user.command} · ${user.rank ? user.rank + " " : ""}${user.fullname}`}
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

      {/* Primary Application & Card Statistics: 2 rows x 3 columns = 6 horizontal rectangular cards */}
      <div>
        <h2 className="mb-2.5 text-xs font-medium uppercase tracking-[0.16em] text-nis-primary">
          {t("Application & Card Statistics")}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5">
          {/* Row 1, Card 1: Total Applications */}
          <StatCard
            label={t("Total Applications")}
            value={totals.applications ?? 0}
            href="/staff/applications"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6" />
                <path d="M16 13H8" />
                <path d="M16 17H8" />
                <path d="M10 9H8" />
              </svg>
            }
          />

          {/* Row 1, Card 2: Applications This Month */}
          <StatCard
            label={t("Applications This Month")}
            value={totals.applications_this_month ?? 0}
            href="/staff/applications"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
            }
          />

          {/* Row 1, Card 3: Total Cards Produced */}
          <StatCard
            label={t("Total Cards Produced")}
            value={totals.cards_produced ?? s.total_cards_produced ?? 0}
            href="/staff/cards"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <circle cx="9" cy="10" r="2" />
                <line x1="15" y1="8" x2="17" y2="8" />
                <line x1="15" y1="12" x2="17" y2="12" />
                <line x1="7" y1="16" x2="17" y2="16" />
              </svg>
            }
          />

          {/* Row 2, Card 1: Total Cards Issued */}
          <StatCard
            label={t("Total Cards Issued")}
            value={totals.cards_issued ?? 0}
            href="/staff/card-issuance"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
            }
          />

          {/* Row 2, Card 2: New Cards Issued */}
          <StatCard
            label={t("New Cards Issued")}
            value={totals.new_cards_issued ?? s.new_cards_issued ?? 0}
            href="/staff/card-issuance"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
              </svg>
            }
          />

          {/* Row 2, Card 3: Renewal Cards Issued */}
          <StatCard
            label={t("Renewal Cards Issued")}
            value={totals.renewal_cards_issued ?? s.renewal_cards_issued ?? 0}
            href="/staff/card-issuance"
            icon={
              <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
              </svg>
            }
          />
        </div>
      </div>

      {/* Feature A: Applications Submitted Per Month (Full Width) */}
      <ChartCard
        title={t("Applications Submitted Per Month")}
        subtitle={t("12-month historical intake across online and assisted enrollment channels")}
        footer={
          <Legend
            items={[
              { label: t("New Applications"), color: SERIES.primary },
              { label: t("Renewal Applications"), color: SERIES.navy },
            ]}
          />
        }
      >
        <div className="py-2">
          <VerticalTwoBarChart
            categories={months}
            series1={{
              label: t("New Applications"),
              data: (a.monthly ?? []).map((m) => m.new_applications ?? 0),
              color: SERIES.primary,
            }}
            series2={{
              label: t("Renewal Applications"),
              data: (a.monthly ?? []).map((m) => m.renewal_applications ?? 0),
              color: SERIES.navy,
            }}
          />
        </div>
      </ChartCard>

      {/* Row 2: Two charts side-by-side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
        {/* Feature B: Application Distribution by State */}
        <ChartCard
          title={t("Application Distribution by State")}
          subtitle={t("Total applicant domicile distribution across Nigerian states")}
        >
          <div className="py-2">
            <StateHorizontalBarChart
              data={statesData.map((d) => ({ label: d.state, value: d.total }))}
              height={320}
              barColor="#1B5E20"
            />
          </div>
        </ChartCard>

        {/* Feature C: Top 5 States by Application Volume */}
        <ChartCard
          title={t("Top 5 States by Application Volume")}
          subtitle={t("Commands with the highest volume of registered ECOWAS residents")}
        >
          <div className="py-2">
            <Top5StatesChart
              data={top5Data.map((d) => ({ rank: d.rank, state: d.state, total: d.total }))}
            />
          </div>
        </ChartCard>
      </div>

      {/* Row 3: Two charts side-by-side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
        {/* Feature D: Application Processing Overview */}
        <ChartCard
          title={t("Application Processing Overview")}
          subtitle={t("Summary of cards produced, issued, queried and rejected")}
        >
          <div className="py-2">
            <ProcessingOverviewChart data={procOverview} />
          </div>
        </ChartCard>

        {/* Feature F: Gender Breakdown */}
        <ChartCard
          title={t("Applicant Gender Breakdown")}
          subtitle={t("Total male vs female applicants across all commands")}
          footer={
            <Legend
              items={[
                { label: t("Male"), color: "#2E7D32" },
                { label: t("Female"), color: "#8E44AD" },
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
