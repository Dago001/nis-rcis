"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";

type CommandItem = {
  sn: number;
  id: number;
  code: string;
  name: string;
  state: string;
  zone_id: number;
  zone_code: string;
  zone_name: string;
  is_active: boolean;
  total_applications: number;
  awaiting_vetting: number;
  awaiting_approval: number;
  cards_produced: number;
  cards_received: number;
  cards_issued: number;
};

type CommandsOverviewResponse = {
  commands: CommandItem[];
  totals: {
    total_commands: number;
    active_commands: number;
    total_applications: number;
    total_awaiting_vetting: number;
    total_awaiting_approval: number;
    total_cards_produced: number;
    total_cards_received: number;
    total_cards_issued: number;
  };
  zones: { id: number; code: string; name: string }[];
};

export default function CommandsOverviewPage() {
  const { user } = useStaff();
  const { t } = useI18n();

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<CommandsOverviewResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [zoneId, setZoneId] = useState<string>("");
  const [search, setSearch] = useState<string>("");

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (zoneId) params.append("zone_id", zoneId);
      if (search) params.append("search", search);

      const res = await api.get<CommandsOverviewResponse>(`/staff/commands-overview?${params.toString()}`);
      setData(res);
    } catch (err: any) {
      setError(err?.message || "Failed to load commands overview data");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [zoneId]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchData();
  };

  const totals = data?.totals;

  return (
    <div className="space-y-6 pb-12">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <PageTitle
            title={t("Commands Overview")}
            subtitle={t("Nationwide operational status, intake volumes, and card processing metrics across all NIS Commands")}
          />
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchData}
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
            <button onClick={fetchData} className="underline font-semibold ml-2">
              {t("Retry")}
            </button>
          </div>
        </Alert>
      )}

      {/* SUMMARY STATISTICS CARDS (4 CORE MANAGEMENT METRICS) */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {/* Total Commands */}
        <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs">
          <span className="text-xs font-medium text-slate-500 block">{t("Total Commands")}</span>
          <div className="flex items-baseline gap-2 mt-1">
            <span className="text-2xl font-bold tabular-nums text-slate-900">
              {totals ? totals.total_commands : "—"}
            </span>
            <span className="text-xs text-slate-400">
              ({totals ? `${totals.active_commands} ${t("Active")}` : ""})
            </span>
          </div>
          <span className="text-[11px] text-slate-500 mt-1 block">{t("36 States + FCT + Special Commands")}</span>
        </div>

        {/* Active Commands */}
        <div className="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-xs">
          <span className="text-xs font-medium text-emerald-800 block">{t("Active Biometric Commands")}</span>
          <span className="text-2xl font-bold tabular-nums text-emerald-900 mt-1 block">
            {totals ? totals.active_commands : "—"}
          </span>
          <span className="text-[11px] text-emerald-700 mt-1 block">{t("Fully operational enrollment centres")}</span>
        </div>

        {/* Total Applications Processed */}
        <div className="rounded-xl border border-blue-200/80 bg-blue-50/40 p-4 shadow-xs">
          <span className="text-xs font-medium text-blue-800 block">{t("Total Applications Processed")}</span>
          <span className="text-2xl font-bold tabular-nums text-blue-900 mt-1 block">
            {totals ? totals.total_applications.toLocaleString() : "—"}
          </span>
          <span className="text-[11px] text-blue-700 mt-1 block">{t("Cumulative intake across commands")}</span>
        </div>

        {/* Total Cards Issued */}
        <div className="rounded-xl border border-purple-200/80 bg-purple-50/40 p-4 shadow-xs">
          <span className="text-xs font-medium text-purple-800 block">{t("Total Cards Issued")}</span>
          <span className="text-2xl font-bold tabular-nums text-purple-900 mt-1 block">
            {totals ? totals.total_cards_issued.toLocaleString() : "—"}
          </span>
          <span className="text-[11px] text-purple-700 mt-1 block">{t("Completed card deliveries")}</span>
        </div>
      </div>

      {/* FILTER & SEARCH BAR */}
      <div className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <form onSubmit={handleSearchSubmit} className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div className="w-full sm:w-64">
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Filter by Zone")}</label>
            <select
              value={zoneId}
              onChange={(e) => setZoneId(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
            >
              <option value="">{t("All NIS Zones (Nationwide)")}</option>
              {data?.zones.map((z) => (
                <option key={z.id} value={z.id}>
                  {z.code} - {z.name}
                </option>
              ))}
            </select>
          </div>

          <div className="flex-1">
            <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Search Command or State")}</label>
            <div className="flex gap-2">
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t("e.g. Lagos, Kano, FCT, Oyo...")}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              />
              <button
                type="submit"
                className="rounded-lg bg-nis-primary px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition shrink-0"
              >
                {t("Search")}
              </button>
              {search && (
                <button
                  type="button"
                  onClick={() => {
                    setSearch("");
                    fetchData();
                  }}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition shrink-0"
                >
                  {t("Clear")}
                </button>
              )}
            </div>
          </div>
        </form>
      </div>

      {/* COMMANDS TABLE */}
      <div className="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="text-sm font-bold text-slate-900">{t("All NIS Commands Nationwide")}</h2>
            <p className="text-xs text-slate-500">
              {t("Displaying")} {data?.commands.length ?? 0} {t("Commands matching criteria")}
            </p>
          </div>
        </div>

        {loading ? (
          <div className="py-16 flex justify-center">
            <Spinner label={t("Loading commands data...")} />
          </div>
        ) : !data?.commands.length ? (
          <div className="py-16 text-center text-xs text-slate-500">
            {t("No commands found matching the search query.")}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
                <tr>
                  <th className="px-3.5 py-3 w-12 text-center">{t("S/N")}</th>
                  <th className="px-3.5 py-3">{t("Zone")}</th>
                  <th className="px-3.5 py-3">{t("Command Name")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Total Applications")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Awaiting Vetting")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Awaiting Approval")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Cards Produced")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Cards Received")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Cards Issued")}</th>
                  <th className="px-3.5 py-3 text-center">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {data.commands.map((cmd) => (
                  <tr key={cmd.id} className="hover:bg-slate-50/60 transition">
                    <td className="px-3.5 py-3 text-center font-mono text-slate-400 font-medium">
                      {cmd.sn}
                    </td>
                    <td className="px-3.5 py-3">
                      <span className="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                        {cmd.zone_code}
                      </span>
                    </td>
                    <td className="px-3.5 py-3 font-semibold text-slate-900">
                      <div>{cmd.name}</div>
                      <div className="text-[10px] text-slate-400 font-normal">{cmd.state}</div>
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono font-bold text-slate-900 tabular-nums">
                      {cmd.total_applications.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono text-amber-700 tabular-nums font-medium">
                      {cmd.awaiting_vetting.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono text-indigo-700 tabular-nums font-medium">
                      {cmd.awaiting_approval.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono text-emerald-700 tabular-nums font-medium">
                      {cmd.cards_produced.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono text-teal-700 tabular-nums font-medium">
                      {cmd.cards_received.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-right font-mono text-purple-700 tabular-nums font-bold">
                      {cmd.cards_issued.toLocaleString()}
                    </td>
                    <td className="px-3.5 py-3 text-center">
                      <Link
                        href={`/staff?command_id=${cmd.id}`}
                        className="inline-flex items-center rounded bg-slate-100 hover:bg-nis-primary hover:text-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition"
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
      </div>
    </div>
  );
}
