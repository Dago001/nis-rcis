"use client";

import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";

interface CommandAdminInfo {
  id: number;
  fullname: string;
  rank: string | null;
  service_number: string;
  username: string;
  email: string;
  is_active: boolean;
}

interface CommandStaffMember {
  id: number;
  fullname: string;
  rank: string | null;
  service_number: string;
  username: string;
  email: string;
  role: string;
  role_label: string;
  is_active: boolean;
  last_login_at: string | null;
}

interface CommandItem {
  sn: number;
  id: number;
  code: string;
  name: string;
  state: string;
  zone_id: number;
  zone_code: string;
  zone_name: string;
  is_active: boolean;
  administrator: CommandAdminInfo | null;
  total_staff: number;
  total_applications: number;
  awaiting_vetting: number;
  awaiting_approval: number;
  cards_produced: number;
  cards_received: number;
  cards_issued: number;
}

interface CommandsOverviewResponse {
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
}

export default function CommandAdministrationPage() {
  const { user } = useStaff();
  const { t } = useI18n();

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<CommandsOverviewResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Filters
  const [zoneId, setZoneId] = useState<string>("");
  const [search, setSearch] = useState<string>("");

  // Management Drawer State
  const [selectedCommand, setSelectedCommand] = useState<CommandItem | null>(null);
  const [commandStaffList, setCommandStaffList] = useState<CommandStaffMember[]>([]);
  const [staffLoading, setStaffLoading] = useState(false);

  // Assign Admin Modal State
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [assignMode, setAssignMode] = useState<"existing" | "new">("existing");
  const [selectedExistingUserId, setSelectedExistingUserId] = useState<string>("");
  const [assignForm, setAssignForm] = useState({
    username: "",
    fullname: "",
    rank: "CIS",
    service_number: "",
    email: "",
  });
  const [assignLoading, setAssignLoading] = useState(false);
  const [assignError, setAssignError] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (zoneId) params.append("zone_id", zoneId);
      if (search) params.append("search", search);

      const res = await api.get<CommandsOverviewResponse>(`/staff/commands-overview?${params.toString()}`);
      setData(res);
      // Update selected command if open
      if (selectedCommand) {
        const updated = res.commands.find((c) => c.id === selectedCommand.id);
        if (updated) setSelectedCommand(updated);
      }
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

  const handleOpenManage = async (cmd: CommandItem) => {
    setSelectedCommand(cmd);
    setStaffLoading(true);
    setShowAssignModal(false);
    try {
      const res = await api.get<{ staff: CommandStaffMember[] }>(`/staff/commands/${cmd.id}/staff`);
      setCommandStaffList(res.staff || []);
    } catch {
      setCommandStaffList([]);
    } finally {
      setStaffLoading(false);
    }
  };

  const handleAssignAdminSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!selectedCommand) return;
    setAssignLoading(true);
    setAssignError(null);
    try {
      const payload: Record<string, any> = {};
      if (assignMode === "existing") {
        if (!selectedExistingUserId) {
          setAssignError("Please select an officer from the list.");
          setAssignLoading(false);
          return;
        }
        payload.existing_user_id = parseInt(selectedExistingUserId, 10);
      } else {
        payload.username = assignForm.username;
        payload.fullname = assignForm.fullname;
        payload.rank = assignForm.rank;
        payload.service_number = assignForm.service_number;
        payload.email = assignForm.email;
      }

      const res = await api.post<{ message: string; administrator: CommandAdminInfo }>(
        `/staff/commands/${selectedCommand.id}/assign-admin`,
        payload
      );

      setNotice(res.message);
      setShowAssignModal(false);
      await fetchData();
      // Reload staff
      const staffRes = await api.get<{ staff: CommandStaffMember[] }>(`/staff/commands/${selectedCommand.id}/staff`);
      setCommandStaffList(staffRes.staff || []);
    } catch (err: any) {
      setAssignError(err?.message || "Failed to assign Command Administrator.");
    } finally {
      setAssignLoading(false);
    }
  };

  const totals = data?.totals;

  return (
    <div className="space-y-6 pb-12">
      {/* HEADER */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <PageTitle
            title={t("Command Administration")}
            subtitle={t("Nationwide operational oversight, Command Administrator assignments, staff management, and intake analytics across all NIS Commands")}
          />
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchData}
            disabled={loading}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`w-3.5 h-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
            </svg>
            <span>{loading ? t("Refreshing…") : t("Refresh Commands")}</span>
          </button>
        </div>
      </div>

      {notice && <Alert tone="success">{notice}</Alert>}
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

      {/* COMMANDS TABLE MATCHING SECTION 11 COLUMNS */}
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
          <div className="py-20 flex justify-center">
            <Spinner label={t("Loading commands data...")} />
          </div>
        ) : !data?.commands.length ? (
          <div className="py-20 text-center text-xs text-slate-500">
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
                  <th className="px-3.5 py-3">{t("Command Administrator")}</th>
                  <th className="px-3.5 py-3 text-center">{t("Total Staff")}</th>
                  <th className="px-3.5 py-3 text-right">{t("Total Applications")}</th>
                  <th className="px-3.5 py-3 text-center">{t("Status")}</th>
                  <th className="px-3.5 py-3 text-center">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {data.commands.map((cmd) => {
                  const admin = cmd.administrator;
                  return (
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
                      <td className="px-3.5 py-3">
                        {admin ? (
                          <div className="flex items-center gap-1.5">
                            <span className="inline-flex h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
                            <div>
                              <div className="font-semibold text-slate-900">
                                {admin.rank ? `${admin.rank} ` : ""}{admin.fullname}
                              </div>
                              <div className="text-[10px] text-slate-400 font-mono">{admin.service_number}</div>
                            </div>
                          </div>
                        ) : (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                            {t("Unassigned")}
                          </span>
                        )}
                      </td>
                      <td className="px-3.5 py-3 text-center">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-800 tabular-nums">
                          {cmd.total_staff}
                        </span>
                      </td>
                      <td className="px-3.5 py-3 text-right font-mono font-bold text-slate-900 tabular-nums">
                        {cmd.total_applications.toLocaleString()}
                      </td>
                      <td className="px-3.5 py-3 text-center">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                          cmd.is_active ? "bg-emerald-100 text-emerald-800" : "bg-red-100 text-red-800"
                        }`}>
                          {cmd.is_active ? t("Active") : t("Inactive")}
                        </span>
                      </td>
                      <td className="px-3.5 py-3 text-center">
                        <button
                          type="button"
                          onClick={() => handleOpenManage(cmd)}
                          className="inline-flex items-center rounded-lg bg-nis-primary hover:bg-nis-primary-dark text-white px-2.5 py-1 text-[11px] font-semibold shadow-2xs transition"
                        >
                          {t("View / Manage")}
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* VIEW / MANAGE COMMAND SLIDE-OVER DRAWER */}
      {selectedCommand && (
        <div className="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end">
          <div className="w-full max-w-2xl bg-white h-full shadow-2xl flex flex-col overflow-y-auto">
            {/* Drawer Header */}
            <div className="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-800">
                  {selectedCommand.zone_name} • {selectedCommand.code}
                </span>
                <h2 className="text-lg font-bold text-slate-900">{selectedCommand.name}</h2>
                <p className="text-xs text-slate-500">{selectedCommand.state} State Command</p>
              </div>
              <button
                type="button"
                onClick={() => setSelectedCommand(null)}
                className="h-8 w-8 flex items-center justify-center rounded-lg border border-slate-300 text-slate-500 hover:bg-slate-100 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <div className="p-6 space-y-6 flex-1">
              {/* ACTION SHORTCUTS */}
              <div className="flex flex-wrap gap-2.5">
                <Link
                  href={`/staff?command_id=${selectedCommand.id}`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-nis-primary text-white px-3.5 py-2 text-xs font-semibold shadow-xs hover:bg-nis-primary-dark transition"
                >
                  <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z" />
                  </svg>
                  <span>{t("Open Command Dashboard")}</span>
                </Link>
                <Link
                  href={`/staff/command-applications?command_id=${selectedCommand.id}`}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-800 px-3.5 py-2 text-xs font-semibold shadow-2xs hover:bg-slate-50 transition"
                >
                  <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <path d="M14 2v6h6" />
                  </svg>
                  <span>{t("View Command Applications")}</span>
                </Link>
                <Link
                  href={`/staff/users?command_id=${selectedCommand.id}`}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-slate-800 px-3.5 py-2 text-xs font-semibold shadow-2xs hover:bg-slate-50 transition"
                >
                  <svg className="w-4 h-4 text-nis-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="8.5" cy="7" r="4" />
                    <line x1="20" y1="8" x2="20" y2="14" />
                    <line x1="23" y1="11" x2="17" y2="11" />
                  </svg>
                  <span>{t("Add Officer to Command")}</span>
                </Link>
              </div>

              {/* COMMAND ADMINISTRATOR CARD */}
              <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                    {t("Command Administrator")}
                  </h3>
                  <button
                    type="button"
                    onClick={() => setShowAssignModal(!showAssignModal)}
                    className="text-xs font-semibold text-nis-primary hover:underline"
                  >
                    {selectedCommand.administrator ? t("Change Administrator") : t("+ Assign Administrator")}
                  </button>
                </div>

                {selectedCommand.administrator ? (
                  <div className="flex items-start gap-3 bg-white p-3.5 rounded-lg border border-slate-200">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 font-bold text-sm">
                      {selectedCommand.administrator.rank || "CA"}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="font-bold text-slate-900 text-sm">
                        {selectedCommand.administrator.rank ? `${selectedCommand.administrator.rank} ` : ""}
                        {selectedCommand.administrator.fullname}
                      </div>
                      <div className="text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                        <span>NIS No: <strong className="font-mono text-slate-700">{selectedCommand.administrator.service_number}</strong></span>
                        <span>Username: <strong className="font-mono text-slate-700">{selectedCommand.administrator.username}</strong></span>
                        <span>Email: <strong className="text-slate-700">{selectedCommand.administrator.email}</strong></span>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="bg-amber-50 border border-amber-200 p-3 rounded-lg text-xs text-amber-900 flex items-center justify-between">
                    <span>{t("No Command Administrator currently assigned to this Command.")}</span>
                    <button
                      type="button"
                      onClick={() => setShowAssignModal(true)}
                      className="rounded bg-amber-600 text-white px-2.5 py-1 font-semibold hover:bg-amber-700 transition"
                    >
                      {t("Assign Now")}
                    </button>
                  </div>
                )}

                {/* INLINE ASSIGN / CHANGE ADMIN FORM */}
                {showAssignModal && (
                  <div className="mt-4 pt-4 border-t border-slate-200 bg-white p-4 rounded-lg shadow-sm">
                    <h4 className="text-xs font-bold text-slate-900 mb-2">
                      {t("Assign Command Administrator for {name}", { name: selectedCommand.name })}
                    </h4>

                    {assignError && <Alert variant="error" className="mb-3">{assignError}</Alert>}

                    <div className="flex gap-4 mb-3 text-xs">
                      <label className="flex items-center gap-1.5 cursor-pointer">
                        <input
                          type="radio"
                          name="assign_mode"
                          checked={assignMode === "existing"}
                          onChange={() => setAssignMode("existing")}
                        />
                        <span className="font-medium text-slate-800">{t("Select Existing Officer")}</span>
                      </label>
                      <label className="flex items-center gap-1.5 cursor-pointer">
                        <input
                          type="radio"
                          name="assign_mode"
                          checked={assignMode === "new"}
                          onChange={() => setAssignMode("new")}
                        />
                        <span className="font-medium text-slate-800">{t("Create New Command Administrator")}</span>
                      </label>
                    </div>

                    <form onSubmit={handleAssignAdminSubmit} className="space-y-3">
                      {assignMode === "existing" ? (
                        <div>
                          <label className="block text-[11px] font-medium text-slate-600 mb-1">
                            {t("Select Officer Deployed at this Command")}
                          </label>
                          <select
                            value={selectedExistingUserId}
                            onChange={(e) => setSelectedExistingUserId(e.target.value)}
                            required
                            className="w-full rounded-lg border border-slate-300 p-2 text-xs text-slate-800"
                          >
                            <option value="">{t("Choose an officer to appoint...")}</option>
                            {commandStaffList.map((s) => (
                              <option key={s.id} value={s.id}>
                                {s.rank ? `${s.rank} ` : ""}{s.fullname} ({s.service_number}) — {s.role_label}
                              </option>
                            ))}
                          </select>
                        </div>
                      ) : (
                        <div className="space-y-2">
                          <div className="grid grid-cols-2 gap-2">
                            <div>
                              <label className="block text-[10px] font-medium text-slate-600 mb-0.5">{t("NIS Rank")}</label>
                              <select
                                value={assignForm.rank}
                                onChange={(e) => setAssignForm({ ...assignForm, rank: e.target.value })}
                                className="w-full rounded border border-slate-300 p-1.5 text-xs"
                              >
                                <option value="CIS">CIS — Comptroller of Immigration</option>
                                <option value="DCI">DCI — Deputy Comptroller</option>
                                <option value="ACI">ACI — Assistant Comptroller</option>
                                <option value="CSI">CSI — Chief Superintendent</option>
                              </select>
                            </div>
                            <div>
                              <label className="block text-[10px] font-medium text-slate-600 mb-0.5">{t("Full Name")}</label>
                              <input
                                type="text"
                                required
                                value={assignForm.fullname}
                                onChange={(e) => setAssignForm({ ...assignForm, fullname: e.target.value })}
                                placeholder="e.g. Babatunde Alabi"
                                className="w-full rounded border border-slate-300 p-1.5 text-xs"
                              />
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-2">
                            <div>
                              <label className="block text-[10px] font-medium text-slate-600 mb-0.5">{t("NIS Number")}</label>
                              <input
                                type="text"
                                required
                                value={assignForm.service_number}
                                onChange={(e) => setAssignForm({ ...assignForm, service_number: e.target.value })}
                                placeholder="NIS-10006"
                                className="w-full rounded border border-slate-300 p-1.5 text-xs"
                              />
                            </div>
                            <div>
                              <label className="block text-[10px] font-medium text-slate-600 mb-0.5">{t("Username")}</label>
                              <input
                                type="text"
                                required
                                value={assignForm.username}
                                onChange={(e) => setAssignForm({ ...assignForm, username: e.target.value })}
                                placeholder="cmdadmin.lagos"
                                className="w-full rounded border border-slate-300 p-1.5 text-xs"
                              />
                            </div>
                          </div>
                          <div>
                            <label className="block text-[10px] font-medium text-slate-600 mb-0.5">{t("Official Email")}</label>
                            <input
                              type="email"
                              required
                              value={assignForm.email}
                              onChange={(e) => setAssignForm({ ...assignForm, email: e.target.value })}
                              placeholder="admin@immigration.gov.ng"
                              className="w-full rounded border border-slate-300 p-1.5 text-xs"
                            />
                          </div>
                        </div>
                      )}

                      <div className="flex justify-end gap-2 pt-2">
                        <button
                          type="button"
                          onClick={() => setShowAssignModal(false)}
                          className="px-3 py-1.5 rounded text-xs border border-slate-300 text-slate-600 hover:bg-slate-50"
                        >
                          {t("Cancel")}
                        </button>
                        <button
                          type="submit"
                          disabled={assignLoading}
                          className="px-4 py-1.5 rounded text-xs bg-nis-primary text-white font-semibold hover:bg-nis-primary-dark transition disabled:opacity-50"
                        >
                          {assignLoading ? t("Appointing…") : t("Confirm Appointment")}
                        </button>
                      </div>
                    </form>
                  </div>
                )}
              </div>

              {/* COMMAND OPERATIONAL STATISTICS */}
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                  {t("Operational Intake & Card Statistics")}
                </h3>
                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                  <div className="border border-slate-200 rounded-lg p-2.5 bg-white">
                    <span className="text-slate-400 block text-[10px] uppercase">{t("Total Apps")}</span>
                    <strong className="text-sm font-bold text-slate-900 tabular-nums">
                      {selectedCommand.total_applications.toLocaleString()}
                    </strong>
                  </div>
                  <div className="border border-amber-200 rounded-lg p-2.5 bg-amber-50/40">
                    <span className="text-amber-800 block text-[10px] uppercase">{t("Awaiting Vetting")}</span>
                    <strong className="text-sm font-bold text-amber-900 tabular-nums">
                      {selectedCommand.awaiting_vetting.toLocaleString()}
                    </strong>
                  </div>
                  <div className="border border-indigo-200 rounded-lg p-2.5 bg-indigo-50/40">
                    <span className="text-indigo-800 block text-[10px] uppercase">{t("Awaiting Approval")}</span>
                    <strong className="text-sm font-bold text-indigo-900 tabular-nums">
                      {selectedCommand.awaiting_approval.toLocaleString()}
                    </strong>
                  </div>
                  <div className="border border-emerald-200 rounded-lg p-2.5 bg-emerald-50/40">
                    <span className="text-emerald-800 block text-[10px] uppercase">{t("Cards Produced")}</span>
                    <strong className="text-sm font-bold text-emerald-900 tabular-nums">
                      {selectedCommand.cards_produced.toLocaleString()}
                    </strong>
                  </div>
                  <div className="border border-teal-200 rounded-lg p-2.5 bg-teal-50/40">
                    <span className="text-teal-800 block text-[10px] uppercase">{t("Cards Received")}</span>
                    <strong className="text-sm font-bold text-teal-900 tabular-nums">
                      {selectedCommand.cards_received.toLocaleString()}
                    </strong>
                  </div>
                  <div className="border border-purple-200 rounded-lg p-2.5 bg-purple-50/40">
                    <span className="text-purple-800 block text-[10px] uppercase">{t("Cards Issued")}</span>
                    <strong className="text-sm font-bold text-purple-900 tabular-nums">
                      {selectedCommand.cards_issued.toLocaleString()}
                    </strong>
                  </div>
                </div>
              </div>

              {/* COMMAND STAFF LIST */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                    {t("Command Deployed Staff ({count})", { count: commandStaffList.length })}
                  </h3>
                  <Link
                    href={`/staff/users?command_id=${selectedCommand.id}`}
                    className="text-xs font-semibold text-nis-primary hover:underline"
                  >
                    {t("+ Add Officer")}
                  </Link>
                </div>

                {staffLoading ? (
                  <div className="py-8 flex justify-center"><Spinner label={t("Loading staff...")} /></div>
                ) : !commandStaffList.length ? (
                  <div className="p-4 text-center border border-dashed border-slate-200 rounded-lg text-xs text-slate-400">
                    {t("No operational officers registered under this Command yet.")}
                  </div>
                ) : (
                  <div className="border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200">
                        <tr>
                          <th className="px-3 py-2">{t("Officer")}</th>
                          <th className="px-3 py-2">{t("NIS Number")}</th>
                          <th className="px-3 py-2">{t("Role")}</th>
                          <th className="px-3 py-2 text-center">{t("Status")}</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 bg-white">
                        {commandStaffList.map((s) => (
                          <tr key={s.id} className="hover:bg-slate-50/60">
                            <td className="px-3 py-2 font-medium text-slate-900">
                              <div>{s.rank ? `${s.rank} ` : ""}{s.fullname}</div>
                              <div className="text-[10px] text-slate-400 font-mono">{s.username}</div>
                            </td>
                            <td className="px-3 py-2 font-mono text-slate-600">{s.service_number}</td>
                            <td className="px-3 py-2 text-slate-700">{s.role_label}</td>
                            <td className="px-3 py-2 text-center">
                              <span className={`inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold ${
                                s.is_active ? "bg-emerald-100 text-emerald-800" : "bg-red-100 text-red-800"
                              }`}>
                                {s.is_active ? t("Active") : t("Inactive")}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
