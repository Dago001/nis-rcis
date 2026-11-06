"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";

type OperationalSummary = {
  total_applications: number;
  awaiting_vetting: number;
  awaiting_biometrics: number;
  awaiting_approval: number;
  cards_awaiting_production: number;
  cards_awaiting_dispatch: number;
  cards_in_transit: number;
  cards_awaiting_receipt_confirmation: number;
  cards_awaiting_issuance: number;
  cards_issued: number;
};

type CardLifecycle = {
  produced: number;
  awaiting_dispatch: number;
  in_transit: number;
  received_at_command: number;
  awaiting_issuance: number;
  issued: number;
};

type DelayItem = {
  stage: string;
  pending_count: number;
  overdue_count: number;
  target_sla: string;
  avg_wait_days: number;
  status: "NORMAL" | "WATCH" | "CRITICAL";
  most_affected: string;
};

type AppRecord = {
  id: number;
  application_number: string;
  reference_number: string;
  applicant_name: string;
  nationality: string;
  type: string;
  current_stage: string;
  zone_name: string;
  command_name: string;
  status: string;
  created_at: string;
  days_in_stage: number;
};

type ZoneItem = {
  id: number;
  code: string;
  name: string;
  commands?: { id: number; zone_id: number; code: string; name: string; state: string }[];
};

type NationalOperationsResponse = {
  summary: OperationalSummary;
  card_lifecycle: CardLifecycle;
  backlog_delays: DelayItem[];
  records: {
    data: AppRecord[];
    current_page: number;
    last_page: number;
    total: number;
  };
  zones: ZoneItem[];
};

export default function NationalOperationsPage() {
  const { user } = useStaff();
  const { t } = useI18n();

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<NationalOperationsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Filters state
  const [zoneId, setZoneId] = useState<string>("");
  const [commandId, setCommandId] = useState<string>("");
  const [dateRange, setDateRange] = useState<string>("ALL");
  const [appType, setAppType] = useState<string>("ALL");
  const [stage, setStage] = useState<string>("ALL");
  const [search, setSearch] = useState<string>("");
  const [page, setPage] = useState<number>(1);

  const fetchData = async (pageNum = 1) => {
    setLoading(true);
    setError(null);

    let dateFrom = "";
    let dateTo = "";
    const now = new Date();
    if (dateRange === "TODAY") {
      dateFrom = now.toISOString().slice(0, 10);
      dateTo = dateFrom;
    } else if (dateRange === "THIS_WEEK") {
      const d = new Date();
      d.setDate(d.getDate() - 7);
      dateFrom = d.toISOString().slice(0, 10);
      dateTo = now.toISOString().slice(0, 10);
    } else if (dateRange === "THIS_MONTH") {
      const d = new Date(now.getFullYear(), now.getMonth(), 1);
      dateFrom = d.toISOString().slice(0, 10);
      dateTo = now.toISOString().slice(0, 10);
    } else if (dateRange === "THIS_QUARTER") {
      const d = new Date();
      d.setMonth(d.getMonth() - 3);
      dateFrom = d.toISOString().slice(0, 10);
      dateTo = now.toISOString().slice(0, 10);
    }

    try {
      const params = new URLSearchParams();
      if (zoneId) params.append("zone_id", zoneId);
      if (commandId) params.append("command_id", commandId);
      if (dateFrom) params.append("date_from", dateFrom);
      if (dateTo) params.append("date_to", dateTo);
      if (appType !== "ALL") params.append("type", appType);
      if (stage !== "ALL") params.append("stage", stage);
      if (search) params.append("search", search);
      params.append("page", String(pageNum));
      params.append("per_page", "15");

      const res = await api.get<NationalOperationsResponse>(`/staff/national-operations?${params.toString()}`);
      setData(res);
      setPage(pageNum);
    } catch (err: any) {
      setError(err?.message || "Failed to load national operations data");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData(1);
  }, [zoneId, commandId, dateRange, appType, stage]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchData(1);
  };

  const handleResetFilters = () => {
    setZoneId("");
    setCommandId("");
    setDateRange("ALL");
    setAppType("ALL");
    setStage("ALL");
    setSearch("");
  };

  const selectedZone = data?.zones.find((z) => String(z.id) === zoneId);
  const availableCommands = selectedZone?.commands ?? (data?.zones.flatMap((z) => z.commands || []) || []);

  const s = data?.summary;
  const l = data?.card_lifecycle;

  return (
    <div className="space-y-6 pb-12">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <PageTitle
            title={t("National Operations Overview")}
            subtitle={t("Comprehensive nationwide monitoring, tracking, and operational analytics across all 37 NIS Commands")}
          />
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => fetchData(page)}
            disabled={loading}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
            </svg>
            <span>{t("Refresh")}</span>
          </button>
        </div>
      </div>

      {error && (
        <Alert variant="error">
          <div className="flex items-center justify-between">
            <span>{error}</span>
            <button onClick={() => fetchData(page)} className="underline font-semibold ml-2">
              {t("Retry")}
            </button>
          </div>
        </Alert>
      )}

      {/* FILTER BAR */}
      <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
          <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-700">
            <svg className="w-4 h-4 text-nis-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <span>{t("Operational Scope & Filters")}</span>
          </div>
          {(zoneId || commandId || dateRange !== "ALL" || appType !== "ALL" || stage !== "ALL" || search) && (
            <button
              type="button"
              onClick={handleResetFilters}
              className="text-xs text-rose-600 hover:text-rose-700 font-semibold transition"
            >
              {t("Reset Filters")}
            </button>
          )}
        </div>

        <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          {/* Zone Selector */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("NIS Zone")}</label>
            <select
              value={zoneId}
              onChange={(e) => {
                setZoneId(e.target.value);
                setCommandId("");
              }}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="">{t("All NIS Zones")}</option>
              {data?.zones.map((z) => (
                <option key={z.id} value={z.id}>
                  {z.code} - {z.name}
                </option>
              ))}
            </select>
          </div>

          {/* Command Selector */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Command")}</label>
            <select
              value={commandId}
              onChange={(e) => setCommandId(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="">{t("All Commands")}</option>
              {availableCommands.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          {/* Date Range */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Date Range")}</label>
            <select
              value={dateRange}
              onChange={(e) => setDateRange(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="ALL">{t("All Time")}</option>
              <option value="TODAY">{t("Today")}</option>
              <option value="THIS_WEEK">{t("Past 7 Days")}</option>
              <option value="THIS_MONTH">{t("This Month")}</option>
              <option value="THIS_QUARTER">{t("Past 90 Days")}</option>
            </select>
          </div>

          {/* Application Type */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Application Type")}</label>
            <select
              value={appType}
              onChange={(e) => setAppType(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="ALL">{t("All Types")}</option>
              <option value="NEW">{t("New Application")}</option>
              <option value="RENEWAL">{t("Renewal")}</option>
            </select>
          </div>

          {/* Processing Stage */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Processing Stage")}</label>
            <select
              value={stage}
              onChange={(e) => setStage(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="ALL">{t("All Stages")}</option>
              <option value="awaiting_vetting">{t("1. Awaiting Vetting")}</option>
              <option value="awaiting_biometrics">{t("2. Awaiting Biometrics")}</option>
              <option value="awaiting_approval">{t("3. Awaiting Approval")}</option>
              <option value="awaiting_card_production">{t("4. Awaiting Card Production")}</option>
              <option value="issued">{t("8. Issued")}</option>
            </select>
          </div>

          {/* Keyword Search */}
          <div>
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Search Records")}</label>
            <div className="flex gap-1.5">
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t("App # / Name...")}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              />
              <button
                type="submit"
                className="rounded-lg bg-nis-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition shrink-0"
              >
                {t("Go")}
              </button>
            </div>
          </div>
        </form>
      </div>

      {/* 10 OPERATIONAL METRICS (COMPACT RESPONSIVE GRID) */}
      <div>
        <h2 className="mb-2 text-xs font-bold uppercase tracking-wider text-nis-primary">
          {t("Key Operational Indicators (10 Core Metrics)")}
        </h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
          {/* 1. Total Applications */}
          <div className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs">
            <span className="text-[11px] font-medium text-slate-500 block truncate">{t("1. Total Applications")}</span>
            <span className="text-xl font-bold tabular-nums text-slate-900 mt-1 block">
              {s ? s.total_applications.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-slate-400 mt-0.5 block">{t("Nationwide intake")}</span>
          </div>

          {/* 2. Awaiting Vetting */}
          <div className="rounded-xl border border-amber-200/70 bg-amber-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-amber-800 block truncate">{t("2. Awaiting Vetting")}</span>
            <span className="text-xl font-bold tabular-nums text-amber-900 mt-1 block">
              {s ? s.awaiting_vetting.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-amber-700 mt-0.5 block">{t("Document verification")}</span>
          </div>

          {/* 3. Awaiting Biometrics */}
          <div className="rounded-xl border border-blue-200/70 bg-blue-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-blue-800 block truncate">{t("3. Awaiting Biometrics")}</span>
            <span className="text-xl font-bold tabular-nums text-blue-900 mt-1 block">
              {s ? s.awaiting_biometrics.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-blue-700 mt-0.5 block">{t("Scheduled capture")}</span>
          </div>

          {/* 4. Awaiting Approval */}
          <div className="rounded-xl border border-indigo-200/70 bg-indigo-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-indigo-800 block truncate">{t("4. Awaiting Approval")}</span>
            <span className="text-xl font-bold tabular-nums text-indigo-900 mt-1 block">
              {s ? s.awaiting_approval.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-indigo-700 mt-0.5 block">{t("Officer adjudication")}</span>
          </div>

          {/* 5. Cards Awaiting Production */}
          <div className="rounded-xl border border-purple-200/70 bg-purple-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-purple-800 block truncate">{t("5. Awaiting Production")}</span>
            <span className="text-xl font-bold tabular-nums text-purple-900 mt-1 block">
              {s ? s.cards_awaiting_production.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-purple-700 mt-0.5 block">{t("HQ Sauka queue")}</span>
          </div>

          {/* 6. Cards Awaiting Dispatch */}
          <div className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs">
            <span className="text-[11px] font-medium text-slate-500 block truncate">{t("6. Awaiting Dispatch")}</span>
            <span className="text-xl font-bold tabular-nums text-slate-900 mt-1 block">
              {s ? s.cards_awaiting_dispatch.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-slate-400 mt-0.5 block">{t("Packaged at Sauka")}</span>
          </div>

          {/* 7. Cards In Transit */}
          <div className="rounded-xl border border-sky-200/70 bg-sky-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-sky-800 block truncate">{t("7. Cards In Transit")}</span>
            <span className="text-xl font-bold tabular-nums text-sky-900 mt-1 block">
              {s ? s.cards_in_transit.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-sky-700 mt-0.5 block">{t("En route to commands")}</span>
          </div>

          {/* 8. Cards Awaiting Receipt Confirmation */}
          <div className="rounded-xl border border-teal-200/70 bg-teal-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-teal-800 block truncate">{t("8. Awaiting Receipt")}</span>
            <span className="text-xl font-bold tabular-nums text-teal-900 mt-1 block">
              {s ? s.cards_awaiting_receipt_confirmation.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-teal-700 mt-0.5 block">{t("Pending verification")}</span>
          </div>

          {/* 9. Cards Awaiting Issuance */}
          <div className="rounded-xl border border-amber-200/70 bg-amber-50/30 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-amber-800 block truncate">{t("9. Awaiting Issuance")}</span>
            <span className="text-xl font-bold tabular-nums text-amber-900 mt-1 block">
              {s ? s.cards_awaiting_issuance.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-amber-700 mt-0.5 block">{t("Ready for collection")}</span>
          </div>

          {/* 10. Cards Issued */}
          <div className="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-3 shadow-xs">
            <span className="text-[11px] font-medium text-emerald-800 block truncate">{t("10. Cards Issued")}</span>
            <span className="text-xl font-bold tabular-nums text-emerald-900 mt-1 block">
              {s ? s.cards_issued.toLocaleString() : "—"}
            </span>
            <span className="text-[10px] text-emerald-700 mt-0.5 block">{t("Verified handover")}</span>
          </div>
        </div>
      </div>

      {/* CARD DISTRIBUTION MONITORING (LIFECYCLE FLOW) */}
      <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className="text-sm font-bold text-slate-900">{t("Card Distribution Lifecycle Monitoring")}</h2>
            <p className="text-xs text-slate-500">{t("Real-time nationwide card logistics from Sauka Production to Command Issuance")}</p>
          </div>
          <Link
            href="/staff/card-distribution"
            className="text-xs font-semibold text-nis-primary hover:underline flex items-center gap-1"
          >
            <span>{t("View Detailed Manifests")}</span>
            <span>&rarr;</span>
          </Link>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
          {/* Step 1: Produced */}
          <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3 relative">
            <div className="text-[11px] font-bold text-slate-500 uppercase">{t("Step 1")}</div>
            <div className="text-xs font-semibold text-slate-800 mt-0.5">{t("Produced at HQ")}</div>
            <div className="text-lg font-bold text-slate-900 tabular-nums mt-1">
              {l ? l.produced.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-slate-500 mt-1">{t("NIS HQ Sauka")}</div>
          </div>

          {/* Step 2: Awaiting Dispatch */}
          <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <div className="text-[11px] font-bold text-slate-500 uppercase">{t("Step 2")}</div>
            <div className="text-xs font-semibold text-slate-800 mt-0.5">{t("Awaiting Dispatch")}</div>
            <div className="text-lg font-bold text-slate-900 tabular-nums mt-1">
              {l ? l.awaiting_dispatch.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-slate-500 mt-1">{t("Central Logistics")}</div>
          </div>

          {/* Step 3: In Transit */}
          <div className="rounded-lg border border-sky-200 bg-sky-50/40 p-3">
            <div className="text-[11px] font-bold text-sky-700 uppercase">{t("Step 3")}</div>
            <div className="text-xs font-semibold text-sky-900 mt-0.5">{t("In Transit")}</div>
            <div className="text-lg font-bold text-sky-900 tabular-nums mt-1">
              {l ? l.in_transit.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-sky-600 mt-1">{t("Courier Dispatch")}</div>
          </div>

          {/* Step 4: Received at Command */}
          <div className="rounded-lg border border-teal-200 bg-teal-50/40 p-3">
            <div className="text-[11px] font-bold text-teal-700 uppercase">{t("Step 4")}</div>
            <div className="text-xs font-semibold text-teal-900 mt-0.5">{t("Received at Command")}</div>
            <div className="text-lg font-bold text-teal-900 tabular-nums mt-1">
              {l ? l.received_at_command.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-teal-600 mt-1">{t("Custody Confirmed")}</div>
          </div>

          {/* Step 5: Awaiting Issuance */}
          <div className="rounded-lg border border-amber-200 bg-amber-50/40 p-3">
            <div className="text-[11px] font-bold text-amber-700 uppercase">{t("Step 5")}</div>
            <div className="text-xs font-semibold text-amber-900 mt-0.5">{t("Awaiting Collection")}</div>
            <div className="text-lg font-bold text-amber-900 tabular-nums mt-1">
              {l ? l.awaiting_issuance.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-amber-600 mt-1">{t("Ready for Holder")}</div>
          </div>

          {/* Step 6: Issued */}
          <div className="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
            <div className="text-[11px] font-bold text-emerald-700 uppercase">{t("Step 6")}</div>
            <div className="text-xs font-semibold text-emerald-900 mt-0.5">{t("Handed Over")}</div>
            <div className="text-lg font-bold text-emerald-900 tabular-nums mt-1">
              {l ? l.issued.toLocaleString() : "—"}
            </div>
            <div className="text-[10px] text-emerald-600 mt-1">{t("Final Issuance")}</div>
          </div>
        </div>
      </div>

      {/* PROCESSING DELAYS AND BACKLOG MONITORING */}
      <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <div className="mb-3">
          <h2 className="text-sm font-bold text-slate-900">{t("Processing Delays and Backlog Monitoring")}</h2>
          <p className="text-xs text-slate-500">{t("Operational bottleneck surveillance against statutory SLA thresholds")}</p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
              <tr>
                <th className="px-3 py-2.5">{t("Processing Stage")}</th>
                <th className="px-3 py-2.5 text-right">{t("Pending Volume")}</th>
                <th className="px-3 py-2.5 text-right">{t("Exceeding SLA")}</th>
                <th className="px-3 py-2.5">{t("Target SLA")}</th>
                <th className="px-3 py-2.5 text-right">{t("Avg Wait Time")}</th>
                <th className="px-3 py-2.5">{t("Operational Status")}</th>
                <th className="px-3 py-2.5">{t("Most Affected Command/Zone")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {data?.backlog_delays.map((d, idx) => (
                <tr key={idx} className="hover:bg-slate-50/60 transition">
                  <td className="px-3 py-2.5 font-semibold text-slate-800">{d.stage}</td>
                  <td className="px-3 py-2.5 text-right font-mono font-medium text-slate-900 tabular-nums">
                    {d.pending_count.toLocaleString()}
                  </td>
                  <td className="px-3 py-2.5 text-right font-mono font-bold tabular-nums">
                    <span className={d.overdue_count > 0 ? "text-rose-600" : "text-slate-400"}>
                      {d.overdue_count.toLocaleString()}
                    </span>
                  </td>
                  <td className="px-3 py-2.5 text-slate-600">{d.target_sla}</td>
                  <td className="px-3 py-2.5 text-right font-medium text-slate-700">
                    {d.avg_wait_days} {t("days")}
                  </td>
                  <td className="px-3 py-2.5">
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold ${
                        d.status === "CRITICAL"
                          ? "bg-rose-100 text-rose-800"
                          : d.status === "WATCH"
                          ? "bg-amber-100 text-amber-800"
                          : "bg-emerald-100 text-emerald-800"
                      }`}
                    >
                      {d.status}
                    </span>
                  </td>
                  <td className="px-3 py-2.5 text-slate-600 truncate max-w-[200px]">{d.most_affected}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* FILTERED APPLICATIONS LIST */}
      <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between mb-3">
          <div>
            <h2 className="text-sm font-bold text-slate-900">{t("Live Operational Application Records")}</h2>
            <p className="text-xs text-slate-500">
              {t("Displaying applications matching current filter criteria")} ({data?.records.total ?? 0} {t("records found")})
            </p>
          </div>
        </div>

        {loading ? (
          <div className="py-12 flex justify-center">
            <Spinner label={t("Loading application records...")} />
          </div>
        ) : !data?.records.data.length ? (
          <div className="py-12 text-center text-xs text-slate-500">
            {t("No applications found matching the selected filters.")}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
                <tr>
                  <th className="px-3 py-2.5">{t("Application Number")}</th>
                  <th className="px-3 py-2.5">{t("Applicant Name")}</th>
                  <th className="px-3 py-2.5">{t("Nationality")}</th>
                  <th className="px-3 py-2.5">{t("Type")}</th>
                  <th className="px-3 py-2.5">{t("Current Stage")}</th>
                  <th className="px-3 py-2.5">{t("Command / Zone")}</th>
                  <th className="px-3 py-2.5 text-right">{t("Days in Stage")}</th>
                  <th className="px-3 py-2.5">{t("Submitted")}</th>
                  <th className="px-3 py-2.5 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {data.records.data.map((rec) => (
                  <tr key={rec.id} className="hover:bg-slate-50/60 transition">
                    <td className="px-3 py-2.5 font-mono font-medium text-slate-900">
                      <Link href={`/staff/applications/${rec.id}`} className="hover:text-nis-primary hover:underline">
                        {rec.application_number}
                      </Link>
                    </td>
                    <td className="px-3 py-2.5 font-medium text-slate-800">{rec.applicant_name}</td>
                    <td className="px-3 py-2.5 text-slate-600">{rec.nationality}</td>
                    <td className="px-3 py-2.5">
                      <span className={`inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold ${rec.type === "RENEWAL" ? "bg-indigo-100 text-indigo-800" : "bg-emerald-100 text-emerald-800"}`}>
                        {rec.type}
                      </span>
                    </td>
                    <td className="px-3 py-2.5">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-700">
                        {rec.current_stage}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-slate-600">
                      <div className="font-medium text-slate-800">{rec.command_name}</div>
                      <div className="text-[10px] text-slate-400">{rec.zone_name}</div>
                    </td>
                    <td className="px-3 py-2.5 text-right font-medium text-slate-700">
                      {rec.days_in_stage} {t("d")}
                    </td>
                    <td className="px-3 py-2.5 text-slate-400">{rec.created_at}</td>
                    <td className="px-3 py-2.5 text-right">
                      <Link
                        href={`/staff/applications/${rec.id}`}
                        className="rounded bg-slate-100 hover:bg-nis-primary hover:text-white px-2 py-1 text-[11px] font-semibold text-slate-700 transition"
                      >
                        {t("View")}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {data && data.records.last_page > 1 && (
          <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-600">
            <span>
              {t("Page")} {data.records.current_page} {t("of")} {data.records.last_page} ({data.records.total} {t("total records")})
            </span>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => fetchData(page - 1)}
                disabled={page <= 1 || loading}
                className="rounded-lg border border-slate-300 px-2.5 py-1 font-medium hover:bg-slate-50 disabled:opacity-40"
              >
                {t("Previous")}
              </button>
              <button
                type="button"
                onClick={() => fetchData(page + 1)}
                disabled={page >= data.records.last_page || loading}
                className="rounded-lg border border-slate-300 px-2.5 py-1 font-medium hover:bg-slate-50 disabled:opacity-40"
              >
                {t("Next")}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
