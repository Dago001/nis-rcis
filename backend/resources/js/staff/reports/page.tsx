"use client";

import { useState, useRef, useEffect, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Field, Input, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";
import { withBase } from "@/lib/base-path";
import { ECOWAS_NATIONALITIES } from "@/lib/nationalities";
import { dateTime, nisDate } from "@/lib/format";

type ReportTab =
  | "issuance"
  | "applications"
  | "vetting"
  | "approvals"
  | "biometrics"
  | "production"
  | "lost_cards"
  | "renewals"
  | "cod"
  | "dependants";

const REPORT_TABS: { key: ReportTab; label: string; icon: string }[] = [
  { key: "issuance", label: "Card Issuance", icon: "M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" },
  { key: "applications", label: "Applications", icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" },
  { key: "vetting", label: "Vetting", icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" },
  { key: "approvals", label: "Approvals", icon: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" },
  { key: "biometrics", label: "Biometrics", icon: "M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004.07 9m5.93 11a14.053 14.053 0 005.171-3" },
  { key: "production", label: "Card Production", icon: "M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" },
  { key: "lost_cards", label: "Lost Card Reports", icon: "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" },
  { key: "renewals", label: "Renewals", icon: "M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" },
  { key: "cod", label: "Change of Data", icon: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" },
  { key: "dependants", label: "Dependants", icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" },
];

type ReportSummary = {
  from: string;
  to: string;
  total_issued: number;
  new_issued: number;
  renewed_issued: number;
  replacement_issued: number;
  nationalities: number;
  by_nationality: { nationality: string; total: number }[];
  by_month: { month: string; total: number }[];
};

type IssuedCardRecord = {
  id: number;
  card_number: string;
  application_number: string;
  reference_number: string;
  applicant_name: string;
  forenames: string;
  surname: string;
  passport_number: string;
  nationality: string;
  application_category: string;
  card_category: string;
  issuance_status: string;
  issuance_status_label: string;
  issued_on: string;
  expires_on: string;
  issuing_officer: string;
  issuance_location: string;
};

type PaginatedResponse<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
  };
};

type LostCardRecord = {
  id: number;
  report_reference: string;
  card_number: string;
  applicant_name: string;
  nationality: string;
  report_type: string;
  incident_date: string;
  incident_location: string;
  police_report_number: string;
  details: string;
  status: string;
  assigned_to?: string | null;
  admin_notes?: string | null;
  created_at: string;
};

type Filters = {
  from: string;
  to: string;
  status?: string;
  nationality?: string;
  card_category?: string;
  category?: string;
  search?: string;
};

type SavedFilter = { id: number; name: string; filters: Partial<Filters> };

const clean = (f: Record<string, string | number | undefined | null>) =>
  Object.fromEntries(
    Object.entries(f).filter(([, v]) => v !== undefined && v !== null && String(v).trim() !== "")
  ) as Record<string, string>;

export default function ReportsPage() {
  const user = useStaff();
  const { t } = useI18n();

  const [activeTab, setActiveTab] = useState<ReportTab>("issuance");
  const [filters, setFilters] = useState<Filters>(() => ({
    from: `${new Date().getFullYear()}-01-01`,
    to: new Date().toISOString().slice(0, 10),
    status: "",
    nationality: "",
    card_category: "",
    category: "",
    search: "",
  }));

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [formKey, setFormKey] = useState(0);
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7));
  const [message, setMessage] = useState<string | null>(null);

  // Lost Card Review Modal State
  const [selectedLostCard, setSelectedLostCard] = useState<LostCardRecord | null>(null);
  const [lostCardStatus, setLostCardStatus] = useState<string>("UNDER_REVIEW");
  const [lostCardNotes, setLostCardNotes] = useState<string>("");
  const [assignToMe, setAssignToMe] = useState<boolean>(true);
  const [submittingLostStatus, setSubmittingLostStatus] = useState<boolean>(false);

  useEffect(() => {
    setSearchInput(filters.search || "");
  }, [filters.search]);

  // When tab changes, reset page to 1
  function handleTabChange(tab: ReportTab) {
    setActiveTab(tab);
    setPage(1);
  }

  const queryParams = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    category: filters.category,
    search: filters.search,
    page,
    per_page: 10,
  })).toString();

  const summaryParams = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    search: filters.search,
  })).toString();

  // Queries for each tab
  const { data: summary } = useFetch<ReportSummary>("staff", `reports/summary?${summaryParams}`);
  const { data: issuanceData } = useFetch<PaginatedResponse<IssuedCardRecord>>(
    "staff",
    activeTab === "issuance" ? `reports?${queryParams}` : null
  );
  const { data: applicationsData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "applications" ? `reports/applications?${queryParams}` : null
  );
  const { data: vettingData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "vetting" ? `reports/vetting?${queryParams}` : null
  );
  const { data: approvalsData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "approvals" ? `reports/approvals?${queryParams}` : null
  );
  const { data: biometricsData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "biometrics" ? `reports/biometrics?${queryParams}` : null
  );
  const { data: productionData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "production" ? `reports/production?${queryParams}` : null
  );
  const { data: lostCardsData, reload: reloadLostCards } = useFetch<PaginatedResponse<LostCardRecord>>(
    "staff",
    activeTab === "lost_cards" ? `reports/lost-cards?${queryParams}` : null
  );
  const { data: renewalsData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "renewals" ? `reports/renewals?${queryParams}` : null
  );
  const { data: codData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "cod" ? `reports/cod?${queryParams}` : null
  );
  const { data: dependantsData } = useFetch<PaginatedResponse<any>>(
    "staff",
    activeTab === "dependants" ? `reports/dependants?${queryParams}` : null
  );

  const saved = useFetch<{ data: SavedFilter[] }>("staff", "reports/filters");
  const canExport = hasRole(user, "SuperAdmin", "Admin", "Supervisor", "Auditor");
  const canManage = hasRole(user, "SuperAdmin", "Admin", "Supervisor", "Auditor");

  function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setPage(1);
    setFilters({
      from: String(f.get("from") || `${new Date().getFullYear()}-01-01`),
      to: String(f.get("to") || new Date().toISOString().slice(0, 10)),
      status: String(f.get("status") ?? ""),
      nationality: String(f.get("nationality") ?? "").trim(),
      card_category: String(f.get("card_category") ?? ""),
      category: String(f.get("category") ?? ""),
      search: searchInput.trim(),
    });
  }

  function handleReset() {
    const defaultFrom = `${new Date().getFullYear()}-01-01`;
    const defaultTo = new Date().toISOString().slice(0, 10);
    setSearchInput("");
    setPage(1);
    setFilters({
      from: defaultFrom,
      to: defaultTo,
      status: "",
      nationality: "",
      card_category: "",
      category: "",
      search: "",
    });
    setFormKey((k) => k + 1);
  }

  const exportQuery = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    search: filters.search,
  })).toString();

  async function updateLostStatusSubmit(e: FormEvent) {
    e.preventDefault();
    if (!selectedLostCard) return;
    setSubmittingLostStatus(true);
    try {
      const res = await api<{ message: string }>("staff", `reports/lost-cards/${selectedLostCard.id}/status`, {
        method: "POST",
        json: {
          status: lostCardStatus,
          admin_notes: lostCardNotes.trim() || null,
          assign_to_me: assignToMe,
        },
      });
      setMessage(res.message);
      setSelectedLostCard(null);
      reloadLostCards();
    } catch (err: any) {
      alert(err.message || "Failed to update report status");
    } finally {
      setSubmittingLostStatus(false);
    }
  }

  // Current active dataset & meta
  const currentDataset = activeTab === "issuance" ? issuanceData
    : activeTab === "applications" ? applicationsData
    : activeTab === "vetting" ? vettingData
    : activeTab === "approvals" ? approvalsData
    : activeTab === "biometrics" ? biometricsData
    : activeTab === "production" ? productionData
    : activeTab === "lost_cards" ? lostCardsData
    : activeTab === "renewals" ? renewalsData
    : activeTab === "cod" ? codData
    : dependantsData;

  const meta = currentDataset?.meta ?? { current_page: 1, last_page: 1, per_page: 10, total: 0 };
  const items = currentDataset?.data ?? [];
  const maxMonth = Math.max(1, ...(summary?.by_month.map((m) => m.total) ?? [1]));

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Reports")}
        subtitle={t("Comprehensive administrative reports, audit records, issuance registry, and lost card management.")}
        actions={
          canExport && (
            <div className="flex flex-wrap items-center gap-2">
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=csv`)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                download
              >
                <svg className="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                {t("Export CSV")}
              </a>
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=xlsx`)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                download
              >
                <svg className="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {t("Export Excel")}
              </a>
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=pdf`)}
                className="inline-flex items-center gap-1.5 rounded-lg bg-nis-primary px-3.5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-nis-primary-dark transition-colors"
                target="_blank"
                rel="noreferrer"
              >
                <svg className="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                {t("Export PDF")}
              </a>
            </div>
          )
        }
      />

      {message && <Alert tone="success">{message}</Alert>}

      {/* Primary Tab Navigation */}
      <div className="border-b border-slate-200">
        <nav className="flex space-x-2 overflow-x-auto pb-2 scrollbar-none" aria-label="Report Categories">
          {REPORT_TABS.map((tab) => {
            const isActive = activeTab === tab.key;
            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => handleTabChange(tab.key)}
                className={`inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-all ${
                  isActive
                    ? "bg-nis-primary text-white shadow-xs"
                    : "bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900"
                }`}
              >
                <svg className={`h-4 w-4 ${isActive ? "text-white" : "text-slate-400"}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={tab.icon} />
                </svg>
                <span>{t(tab.label)}</span>
              </button>
            );
          })}
        </nav>
      </div>

      {/* KPI Cards (Active for Issuance) */}
      {activeTab === "issuance" && summary && (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t("Total Cards Issued")}</div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-nis-primary tabular-nums">{summary.total_issued.toLocaleString()}</div>
            <div className="mt-0.5 text-[11px] text-slate-500">{t("Officially confirmed & issued")}</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t("New Cards Issued")}</div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{summary.new_issued.toLocaleString()}</div>
            <div className="mt-0.5 text-[11px] text-slate-500">{t("Initial residence cards")}</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t("Renewed Cards Issued")}</div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{summary.renewed_issued.toLocaleString()}</div>
            <div className="mt-0.5 text-[11px] text-slate-500">{t("Extensions & renewals")}</div>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t("Replacement Cards Issued")}</div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{summary.replacement_issued.toLocaleString()}</div>
            <div className="mt-0.5 text-[11px] text-slate-500">{t("Lost or damaged replacements")}</div>
          </div>
        </div>
      )}

      {/* Filter and Search Panel */}
      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
        <form key={formKey} onSubmit={apply} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <div>
              <Field label={t("From")}>
                <Input type="date" name="from" defaultValue={filters.from} />
              </Field>
            </div>
            <div>
              <Field label={t("To")}>
                <Input type="date" name="to" defaultValue={filters.to} />
              </Field>
            </div>
            <div>
              <Field label={t("Search")}>
                <Input
                  type="search"
                  placeholder={t("Number, reference, name…")}
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                />
              </Field>
            </div>
            <div className="flex items-center gap-2">
              <Button type="submit" className="!w-auto flex-1">
                {t("Apply")}
              </Button>
              <Button type="button" variant="secondary" onClick={handleReset} className="!w-auto">
                {t("Reset")}
              </Button>
            </div>
          </div>
        </form>
      </div>

      {/* Main Report Table Container */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
        <div className="border-b border-slate-200 px-5 py-4 flex flex-wrap items-center justify-between gap-2">
          <h3 className="font-semibold text-slate-900">
            {t(REPORT_TABS.find((x) => x.key === activeTab)?.label ?? "Report")}
          </h3>
          <span className="text-xs text-slate-500">
            {t("Total records")}: <strong className="text-slate-800">{meta.total.toLocaleString()}</strong>
          </span>
        </div>

        {!currentDataset ? (
          <div className="p-8 text-center"><Spinner /></div>
        ) : items.length === 0 ? (
          <div className="p-12 text-center text-sm text-slate-500">
            {t("No records found matching the active criteria.")}
          </div>
        ) : (
          <div className="overflow-x-auto">
            {/* 1. CARD ISSUANCE TAB */}
            {activeTab === "issuance" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Card Number</th>
                    <th className="px-4 py-3.5">Applicant / Passport</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Type</th>
                    <th className="px-4 py-3.5">Issued On</th>
                    <th className="px-4 py-3.5">Issuing Officer</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {(items as IssuedCardRecord[]).map((c, idx) => (
                    <tr key={c.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">
                        {c.card_number}
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900">{c.applicant_name}</div>
                        <div className="text-xs text-slate-500 font-mono">{c.passport_number}</div>
                      </td>
                      <td className="px-4 py-3">{t(c.nationality)}</td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-800">
                          {c.card_category}
                        </span>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">{nisDate(c.issued_on)}</td>
                      <td className="px-4 py-3 text-xs">{c.issuing_officer}</td>
                      <td className="px-4 py-3"><StatusBadge status={c.issuance_status} label={c.issuance_status_label} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 2. APPLICATIONS TAB */}
            {activeTab === "applications" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Category</th>
                    <th className="px-4 py-3.5">Center</th>
                    <th className="px-4 py-3.5">Date</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((a: any, idx) => (
                    <tr key={a.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{a.application_number}</td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900">{a.applicant_name}</div>
                        <div className="text-xs text-slate-500 font-mono">{a.passport_number}</div>
                      </td>
                      <td className="px-4 py-3">{t(a.nationality)}</td>
                      <td className="px-4 py-3">{a.category}</td>
                      <td className="px-4 py-3 text-xs">{a.center}</td>
                      <td className="px-4 py-3 text-xs whitespace-nowrap">{dateTime(a.created_at)}</td>
                      <td className="px-4 py-3"><StatusBadge status={a.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 3. VETTING TAB */}
            {activeTab === "vetting" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Vetting Officer</th>
                    <th className="px-4 py-3.5">Vetted At</th>
                    <th className="px-4 py-3.5">Result</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((v: any, idx) => (
                    <tr key={v.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{v.application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{v.applicant_name}</td>
                      <td className="px-4 py-3">{t(v.nationality)}</td>
                      <td className="px-4 py-3 text-xs font-medium text-slate-800">{v.vetted_by}</td>
                      <td className="px-4 py-3 text-xs whitespace-nowrap">{dateTime(v.vetted_at)}</td>
                      <td className="px-4 py-3"><StatusBadge status={v.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 4. APPROVALS TAB */}
            {activeTab === "approvals" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Approving Officer</th>
                    <th className="px-4 py-3.5">Decided At</th>
                    <th className="px-4 py-3.5">Decision</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((ap: any, idx) => (
                    <tr key={ap.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{ap.application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{ap.applicant_name}</td>
                      <td className="px-4 py-3">{t(ap.nationality)}</td>
                      <td className="px-4 py-3 text-xs font-medium text-slate-800">{ap.approved_by}</td>
                      <td className="px-4 py-3 text-xs whitespace-nowrap">{dateTime(ap.approved_at)}</td>
                      <td className="px-4 py-3"><StatusBadge status={ap.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 5. BIOMETRICS TAB */}
            {activeTab === "biometrics" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Enrollment Center</th>
                    <th className="px-4 py-3.5">Captured At</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((b: any, idx) => (
                    <tr key={b.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{b.application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{b.applicant_name}</td>
                      <td className="px-4 py-3">{t(b.nationality)}</td>
                      <td className="px-4 py-3 text-xs">{b.center}</td>
                      <td className="px-4 py-3 text-xs whitespace-nowrap">{dateTime(b.biometrics_completed_at)}</td>
                      <td className="px-4 py-3"><StatusBadge status={b.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 6. CARD PRODUCTION TAB */}
            {activeTab === "production" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Card Number</th>
                    <th className="px-4 py-3.5">Holder</th>
                    <th className="px-4 py-3.5">Batch</th>
                    <th className="px-4 py-3.5">Printed By</th>
                    <th className="px-4 py-3.5">Print Date</th>
                    <th className="px-4 py-3.5">Outcome</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((p: any, idx) => (
                    <tr key={p.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{p.card_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{p.holder_name}</td>
                      <td className="px-4 py-3 text-xs font-mono">{p.batch_number}</td>
                      <td className="px-4 py-3 text-xs">{p.printed_by}</td>
                      <td className="px-4 py-3 text-xs whitespace-nowrap">{dateTime(p.created_at)}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
                          p.outcome === "PRINTED" ? "bg-emerald-50 text-emerald-700 border border-emerald-200" : "bg-red-50 text-red-700 border border-red-200"
                        }`}>
                          {p.outcome}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 7. LOST CARD REPORTS TAB */}
            {activeTab === "lost_cards" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Report Ref</th>
                    <th className="px-4 py-3.5">Card Number</th>
                    <th className="px-4 py-3.5">Applicant / Nationality</th>
                    <th className="px-4 py-3.5">Type</th>
                    <th className="px-4 py-3.5">Incident Date & Location</th>
                    <th className="px-4 py-3.5">Status</th>
                    <th className="px-4 py-3.5 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {(items as LostCardRecord[]).map((r, idx) => (
                    <tr key={r.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-bold text-red-800 font-mono text-xs">{r.report_reference}</td>
                      <td className="px-4 py-3 font-mono text-slate-900">{r.card_number}</td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900">{r.applicant_name}</div>
                        <div className="text-xs text-slate-500">{t(r.nationality)}</div>
                      </td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">
                          {r.report_type}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs">
                        <div>{nisDate(r.incident_date)}</div>
                        <div className="text-slate-500">{r.incident_location}</div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
                          r.status === "RESOLVED" || r.status === "CLOSED" ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                          : r.status === "UNDER_REVIEW" ? "bg-blue-50 text-blue-700 border border-blue-200"
                          : "bg-red-50 text-red-700 border border-red-200"
                        }`}>
                          {r.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <button
                          type="button"
                          onClick={() => {
                            setSelectedLostCard(r);
                            setLostCardStatus(r.status === "SUBMITTED" ? "UNDER_REVIEW" : r.status);
                            setLostCardNotes(r.admin_notes || "");
                          }}
                          className="inline-flex items-center gap-1 text-xs font-semibold text-nis-primary hover:text-nis-primary-dark hover:underline focus:outline-none"
                        >
                          <svg className="h-3.5 w-3.5 text-nis-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          <span>Review</span>
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 8. RENEWALS TAB */}
            {activeTab === "renewals" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Nationality</th>
                    <th className="px-4 py-3.5">Category</th>
                    <th className="px-4 py-3.5">Fee Paid</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((rn: any, idx) => (
                    <tr key={rn.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{rn.application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{rn.applicant_name}</td>
                      <td className="px-4 py-3">{t(rn.nationality)}</td>
                      <td className="px-4 py-3">{rn.category}</td>
                      <td className="px-4 py-3 font-semibold">₦{rn.fee_naira?.toLocaleString() ?? "—"}</td>
                      <td className="px-4 py-3"><StatusBadge status={rn.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 9. CHANGE OF DATA TAB */}
            {activeTab === "cod" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Application Number</th>
                    <th className="px-4 py-3.5">Applicant</th>
                    <th className="px-4 py-3.5">Fields to Change</th>
                    <th className="px-4 py-3.5">COD Admin Fee</th>
                    <th className="px-4 py-3.5">Reason</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((cd: any, idx) => (
                    <tr key={cd.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{cd.application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{cd.applicant_name}</td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                          {Array.isArray(cd.cod_fields) ? cd.cod_fields.join(", ") : (cd.cod_fields || "Name / DOB")}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-bold text-slate-900">₦{(cd.cod_fee_naira || 15000).toLocaleString()}</td>
                      <td className="px-4 py-3 text-xs text-slate-600 max-w-xs truncate">{cd.cod_reason || "Correction of recorded data"}</td>
                      <td className="px-4 py-3"><StatusBadge status={cd.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* 10. DEPENDANTS TAB */}
            {activeTab === "dependants" && (
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                  <tr>
                    <th className="px-4 py-3.5 w-12 text-center">S/N</th>
                    <th className="px-4 py-3.5">Dependant App Number</th>
                    <th className="px-4 py-3.5">Dependant Name</th>
                    <th className="px-4 py-3.5">Relationship</th>
                    <th className="px-4 py-3.5">Principal App Number</th>
                    <th className="px-4 py-3.5">Principal Name</th>
                    <th className="px-4 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {items.map((dp: any, idx) => (
                    <tr key={dp.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 text-center text-xs font-mono text-slate-500">
                        {(page - 1) * 10 + idx + 1}
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 font-mono">{dp.dependant_application_number}</td>
                      <td className="px-4 py-3 font-medium text-slate-900">{dp.dependant_name}</td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                          {dp.relationship}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{dp.principal_application_number}</td>
                      <td className="px-4 py-3 text-xs text-slate-600">{dp.principal_name}</td>
                      <td className="px-4 py-3"><StatusBadge status={dp.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {/* Continuous Pagination */}
        {meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <span className="text-xs text-slate-500">
              {t("Page")} <strong className="text-slate-800">{meta.current_page}</strong> {t("of")}{" "}
              <strong className="text-slate-800">{meta.last_page}</strong> ({meta.total.toLocaleString()} {t("records")})
            </span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={meta.current_page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {t("Previous")}
              </button>
              <button
                type="button"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {t("Next")}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Monthly Management Report Section */}
      {canManage && (
        <Panel title={t("Monthly management report")}>
          <div className="flex flex-wrap items-end gap-3">
            <Field label={t("Month")}>
              <Input
                type="month"
                value={month}
                max={new Date().toISOString().slice(0, 7)}
                onChange={(e) => setMonth(e.target.value)}
              />
            </Field>
            <a
              href={withBase(`/api/bff/staff/reports/management?month=${month}&format=pdf`)}
              className="rounded-md bg-nis-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-nis-primary-dark transition-colors shadow-xs"
              target="_blank"
              rel="noreferrer"
            >
              {t("Download PDF")}
            </a>
            <a
              href={withBase(`/api/bff/staff/reports/management?month=${month}&format=xlsx`)}
              className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:border-nis-primary hover:text-nis-primary transition-colors shadow-xs"
              download
            >
              {t("Download Excel")}
            </a>
            <p className="basis-full text-xs text-slate-500">
              Applications, service-level performance, cards, fees, card production and centre queues for the selected month.
            </p>
          </div>
        </Panel>
      )}

      {/* Lost Card Report Review Modal */}
      {selectedLostCard && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
          <div className="w-full max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="text-lg font-bold text-slate-900">Review Lost Card Report</h3>
                <p className="text-xs font-mono text-red-800 font-semibold">{selectedLostCard.report_reference}</p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedLostCard(null)}
                className="text-slate-400 hover:text-slate-600 focus:outline-none"
              >
                ✕
              </button>
            </div>

            <dl className="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-3.5 rounded-lg border border-slate-200">
              <div><dt className="text-slate-500">Card Number:</dt><dd className="font-mono font-bold text-slate-800">{selectedLostCard.card_number}</dd></div>
              <div><dt className="text-slate-500">Holder Name:</dt><dd className="font-semibold text-slate-800">{selectedLostCard.applicant_name}</dd></div>
              <div><dt className="text-slate-500">Report Type:</dt><dd className="font-medium text-amber-800">{selectedLostCard.report_type}</dd></div>
              <div><dt className="text-slate-500">Incident Date:</dt><dd>{nisDate(selectedLostCard.incident_date)}</dd></div>
              <div><dt className="text-slate-500">Incident Location:</dt><dd>{selectedLostCard.incident_location}</dd></div>
              <div><dt className="text-slate-500">Police Report No:</dt><dd className="font-mono">{selectedLostCard.police_report_number}</dd></div>
              <div className="col-span-2"><dt className="text-slate-500">Details / Statement:</dt><dd className="mt-0.5 text-slate-700 bg-white p-2 rounded border border-slate-200">{selectedLostCard.details}</dd></div>
            </dl>

            <form onSubmit={updateLostStatusSubmit} className="space-y-4 pt-2">
              <Field label="Update Report Status" required>
                <Select value={lostCardStatus} onChange={(e) => setLostCardStatus(e.target.value)}>
                  <option value="SUBMITTED">SUBMITTED</option>
                  <option value="UNDER_REVIEW">UNDER_REVIEW</option>
                  <option value="ADDITIONAL_INFO_REQUIRED">ADDITIONAL_INFO_REQUIRED</option>
                  <option value="RESOLVED">RESOLVED</option>
                  <option value="CLOSED">CLOSED</option>
                </Select>
              </Field>

              <Field label="Administrative Notes" hint="Internal notes on verification with police extract or card recovery.">
                <Textarea value={lostCardNotes} onChange={(e) => setLostCardNotes(e.target.value)} rows={3} placeholder="Add officer notes..." />
              </Field>

              <label className="flex items-center gap-2 text-xs font-medium text-slate-700">
                <input type="checkbox" checked={assignToMe} onChange={(e) => setAssignToMe(e.target.checked)} className="rounded border-slate-300 text-nis-primary" />
                <span>Assign this review to my account ({user?.fullname})</span>
              </label>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button variant="secondary" type="button" onClick={() => setSelectedLostCard(null)} disabled={submittingLostStatus}>
                  Cancel
                </Button>
                <Button type="submit" disabled={submittingLostStatus}>
                  {submittingLostStatus ? "Saving…" : "Save Status"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
