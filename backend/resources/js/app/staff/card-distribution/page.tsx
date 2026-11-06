"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";

interface DistributionSummary {
  total_cards_produced: number;
  awaiting_dispatch: number;
  in_transit: number;
  received: number;
  issued: number;
}

interface CommandSummaryItem {
  sn: number;
  id: number;
  command_name: string;
  zone_name: string;
  zone_code: string;
  total_produced: number;
  awaiting_dispatch: number;
  in_transit: number;
  received: number;
  issued: number;
}

interface ZoneOption {
  id: number;
  code: string;
  name: string;
}

interface CommandOption {
  id: number;
  code: string;
  name: string;
  zone_id: number;
  state: string;
}

interface CardDistributionRecord {
  id: number;
  card_id: number;
  application_id: number;
  card_number: string;
  application_number: string;
  applicant_name: string;
  applicant_passport?: string;
  application_type?: string;
  destination_zone: string;
  destination_command: string;
  batch_reference: string;
  waybill_number?: string;
  courier_service?: string;
  status: "AWAITING_DISPATCH" | "DISPATCHED" | "RECEIVED" | "AVAILABLE_FOR_ISSUANCE" | "ISSUED";
  status_label: string;
  produced_at?: string;
  dispatched_at?: string;
  dispatched_by?: string;
  received_at?: string;
  received_by?: string;
  issued_at?: string;
  issued_by?: string;
  dispatch_notes?: string;
  receipt_notes?: string;
  geofence_verified_receipt?: boolean;
}

interface ApiResponse {
  data: CardDistributionRecord[];
  summary: DistributionSummary;
  commands_summary: CommandSummaryItem[];
  zones: ZoneOption[];
  commands: CommandOption[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export default function CardDistributionPage() {
  const me = useStaff();

  // Filters State
  const [selectedZone, setSelectedZone] = useState<string>("");
  const [selectedCommand, setSelectedCommand] = useState<string>("");
  const [selectedStatus, setSelectedStatus] = useState<string>("");
  const [selectedType, setSelectedType] = useState<string>("");
  const [dateFrom, setDateFrom] = useState<string>("");
  const [dateTo, setDateTo] = useState<string>("");
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Server Data State
  const [distributions, setDistributions] = useState<CardDistributionRecord[]>([]);
  const [summary, setSummary] = useState<DistributionSummary | null>(null);
  const [commandsSummary, setCommandsSummary] = useState<CommandSummaryItem[]>([]);
  const [zones, setZones] = useState<ZoneOption[]>([]);
  const [allCommands, setAllCommands] = useState<CommandOption[]>([]);
  const [pagination, setPagination] = useState<ApiResponse["pagination"] | null>(null);

  // Selection & UI State
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Modals State
  const [showDispatchModal, setShowDispatchModal] = useState(false);
  const [showReceiptModal, setShowReceiptModal] = useState(false);
  const [dossierRecord, setDossierRecord] = useState<CardDistributionRecord | null>(null);

  // Batch Dispatch Form
  const [dispatchRef, setDispatchRef] = useState("");
  const [courierName, setCourierName] = useState("NIPOST Express Pouch");
  const [trackingNumber, setTrackingNumber] = useState("");
  const [dispatchNotes, setDispatchNotes] = useState("");

  // Physical Receipt Form & Geofence
  const [gpsLoading, setGpsLoading] = useState(false);
  const [coords, setCoords] = useState<{ latitude: number; longitude: number } | null>(null);
  const [overrideReason, setOverrideReason] = useState("");
  const [showOverride, setShowOverride] = useState(false);
  const [receiptNotes, setReceiptNotes] = useState("");

  // Role permissions
  const role = me?.role || "";
  const isSuperAdmin = role === "SuperAdmin";
  const isHqAdmin = role === "HeadquartersAdmin" || role === "Admin";
  const isHqStaff = isSuperAdmin || isHqAdmin || role === "CardDistributionOfficer" || role === "ProductionOfficer";
  const isCommandStaff = role === "CommandAdmin" || role === "CardReceivingOfficer" || role === "CardIssuanceOfficer";

  // Cascading commands list based on selected Zone
  const filteredCommandOptions = useMemo(() => {
    if (!selectedZone) return allCommands;
    const zId = Number(selectedZone);
    return allCommands.filter((c) => c.zone_id === zId);
  }, [allCommands, selectedZone]);

  // Load Distribution Data from API
  const loadData = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      params.set("page", String(page));
      params.set("per_page", "25");
      if (selectedZone) params.set("zone_id", selectedZone);
      if (selectedCommand) params.set("command_id", selectedCommand);
      if (selectedStatus) params.set("status", selectedStatus);
      if (selectedType) params.set("type", selectedType);
      if (dateFrom) params.set("date_from", dateFrom);
      if (dateTo) params.set("date_to", dateTo);
      if (searchQuery.trim()) params.set("search", searchQuery.trim());

      const res = await api<ApiResponse>("staff", `card-distributions?${params.toString()}`);
      setDistributions(res.data || []);
      setSummary(res.summary || null);
      setCommandsSummary(res.commands_summary || []);
      setZones(res.zones || []);
      setAllCommands(res.commands || []);
      setPagination(res.pagination || null);
      setSelectedIds([]);
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
      } else {
        setError("Unable to load card distribution register.");
      }
    } finally {
      setLoading(false);
    }
  }, [selectedZone, selectedCommand, selectedStatus, selectedType, dateFrom, dateTo, searchQuery]);

  useEffect(() => {
    void loadData(currentPage);
  }, [loadData, currentPage]);

  const handleResetFilters = () => {
    setSelectedZone("");
    setSelectedCommand("");
    setSelectedStatus("");
    setSelectedType("");
    setDateFrom("");
    setDateTo("");
    setSearchQuery("");
    setCurrentPage(1);
  };

  const handleZoneChange = (zoneId: string) => {
    setSelectedZone(zoneId);
    setSelectedCommand(""); // Cascade reset command
    setCurrentPage(1);
  };

  // Selection handlers
  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked && distributions.length > 0) {
      setSelectedIds(distributions.map((d) => d.id));
    } else {
      setSelectedIds([]);
    }
  };

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    );
  };

  // GPS Geofence Capture
  const captureGps = () => {
    if (!navigator.geolocation) {
      setError("Geolocation is not supported by your browser. Please use supervisor override if authorized.");
      return;
    }
    setGpsLoading(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setCoords({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
        });
        setGpsLoading(false);
        setNotice(`GPS Coordinates acquired (${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}). Ready for geofenced verification.`);
      },
      (err) => {
        setGpsLoading(false);
        setError(`Unable to retrieve GPS coordinates: ${err.message}. If operating in an authorized offline station, provide supervisor override reason.`);
        setShowOverride(true);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  };

  // Submit HQ Dispatch Batch
  const handleDispatchSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedIds.length === 0) return;
    setActionLoading(true);
    setError(null);
    setNotice(null);
    try {
      const res = await api<{ message: string; batch_reference: string }>("staff", "card-distributions/dispatch", {
        method: "POST",
        json: {
          distribution_ids: selectedIds,
          dispatch_reference: dispatchRef.trim() || undefined,
          courier_service: courierName.trim() || "NIS Escort / NIPOST",
          waybill_number: trackingNumber.trim() || undefined,
          notes: dispatchNotes.trim() || undefined,
        },
      });
      setNotice(res.message || `Consignment batch ${res.batch_reference} successfully dispatched!`);
      setShowDispatchModal(false);
      setDispatchRef("");
      setTrackingNumber("");
      setDispatchNotes("");
      void loadData(currentPage);
    } catch (e) {
      if (e instanceof ApiError) setError(e.message);
      else setError("Failed to submit dispatch consignment.");
    } finally {
      setActionLoading(false);
    }
  };

  // Confirm Physical Receipt at Command
  const handleReceiptSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedIds.length === 0) return;
    if (!coords && !overrideReason.trim()) {
      setError("GPS verification or an authorized supervisor override reason is required.");
      return;
    }
    setActionLoading(true);
    setError(null);
    setNotice(null);
    try {
      const res = await api<{ message: string; verified_count: number }>("staff", "card-distributions/receive", {
        method: "POST",
        json: {
          distribution_ids: selectedIds,
          latitude: coords?.latitude,
          longitude: coords?.longitude,
          override_reason: overrideReason.trim() || undefined,
          notes: receiptNotes.trim() || undefined,
        },
      });
      setNotice(res.message || `Physical receipt confirmed for ${res.verified_count} residence cards.`);
      setShowReceiptModal(false);
      setCoords(null);
      setOverrideReason("");
      setShowOverride(false);
      setReceiptNotes("");
      void loadData(currentPage);
    } catch (e) {
      if (e instanceof ApiError) setError(e.message);
      else setError("Failed to verify physical card receipt.");
    } finally {
      setActionLoading(false);
    }
  };

  // Identify active command drilldown
  const activeCommandObj = useMemo(() => {
    if (!selectedCommand) return null;
    return allCommands.find((c) => String(c.id) === selectedCommand) || null;
  }, [allCommands, selectedCommand]);

  // Selected distribution items
  const selectedRecords = useMemo(() => {
    return distributions.filter((d) => selectedIds.includes(d.id));
  }, [distributions, selectedIds]);

  const canDispatchSelected = selectedRecords.length > 0 && selectedRecords.every((r) => r.status === "AWAITING_DISPATCH");
  const canReceiveSelected = selectedRecords.length > 0 && selectedRecords.every((r) => r.status === "DISPATCHED");

  return (
    <div className="space-y-6">
      <PageTitle
        title="Card Distribution Management"
        subtitle="Manage secure transfer of produced cards from Headquarters to State Commands."
        actions={
          <div className="flex items-center gap-2">
            {isHqStaff && canDispatchSelected && (
              <Button
                onClick={() => setShowDispatchModal(true)}
                className="!bg-amber-600 hover:!bg-amber-700 text-white shadow-xs"
              >
                <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                Dispatch Selected ({selectedIds.length})
              </Button>
            )}

            {(isCommandStaff || isSuperAdmin) && canReceiveSelected && (
              <Button
                onClick={() => setShowReceiptModal(true)}
                className="!bg-emerald-700 hover:!bg-emerald-800 text-white shadow-xs"
              >
                <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Confirm Receipt ({selectedIds.length})
              </Button>
            )}

            <Button
              variant="ghost"
              onClick={() => void loadData(currentPage)}
              disabled={loading}
              className="border border-slate-300 bg-white shadow-xs"
            >
              <svg className={`w-3.5 h-3.5 text-nis-primary mr-1.5 ${loading ? "animate-spin" : ""}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Refresh
            </Button>
          </div>
        }
      />

      {/* Global Alerts */}
      {error && (
        <Alert tone="danger" onClose={() => setError(null)}>
          {error}
        </Alert>
      )}
      {notice && (
        <Alert tone="success" onClose={() => setNotice(null)}>
          {notice}
        </Alert>
      )}

      {/* ========================================================= */}
      {/* SECTION 1: DISTRIBUTION SUMMARY CARDS                     */}
      {/* ========================================================= */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Total Cards Produced */}
        <div
          onClick={() => { setSelectedStatus(""); setCurrentPage(1); }}
          className="cursor-pointer rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-nis-primary/50 hover:shadow-sm"
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Total Cards Produced</span>
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </span>
          </div>
          <div className="mt-3 text-3xl font-bold tracking-tight text-slate-900">
            {summary?.total_cards_produced?.toLocaleString() ?? 0}
          </div>
          <p className="mt-1 text-xs text-slate-500">Cumulative production within selected scope</p>
        </div>

        {/* Awaiting Dispatch */}
        <div
          onClick={() => { setSelectedStatus("AWAITING_DISPATCH"); setCurrentPage(1); }}
          className={`cursor-pointer rounded-2xl border p-5 shadow-xs transition hover:shadow-sm ${
            selectedStatus === "AWAITING_DISPATCH"
              ? "border-amber-500 bg-amber-50/50 ring-2 ring-amber-500/20"
              : "border-slate-200 bg-white hover:border-amber-400"
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-amber-800">Awaiting Dispatch</span>
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
          </div>
          <div className="mt-3 text-3xl font-bold tracking-tight text-amber-900">
            {summary?.awaiting_dispatch?.toLocaleString() ?? 0}
          </div>
          <p className="mt-1 text-xs text-amber-700">Printed at HQ · Staged for courier dispatch</p>
        </div>

        {/* Dispatched / In Transit */}
        <div
          onClick={() => { setSelectedStatus("DISPATCHED"); setCurrentPage(1); }}
          className={`cursor-pointer rounded-2xl border p-5 shadow-xs transition hover:shadow-sm ${
            selectedStatus === "DISPATCHED"
              ? "border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20"
              : "border-slate-200 bg-white hover:border-blue-400"
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-blue-800">Dispatched / In Transit</span>
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
              </svg>
            </span>
          </div>
          <div className="mt-3 text-3xl font-bold tracking-tight text-blue-900">
            {summary?.in_transit?.toLocaleString() ?? 0}
          </div>
          <p className="mt-1 text-xs text-blue-700">En route via official courier / pouch</p>
        </div>

        {/* Received by Commands */}
        <div
          onClick={() => { setSelectedStatus("RECEIVED"); setCurrentPage(1); }}
          className={`cursor-pointer rounded-2xl border p-5 shadow-xs transition hover:shadow-sm ${
            selectedStatus === "RECEIVED"
              ? "border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20"
              : "border-slate-200 bg-white hover:border-emerald-400"
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-emerald-800">Received by Commands</span>
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </span>
          </div>
          <div className="mt-3 text-3xl font-bold tracking-tight text-emerald-950">
            {summary?.received?.toLocaleString() ?? 0}
          </div>
          <p className="mt-1 text-xs text-emerald-800">
            Physically verified at Command ({summary?.issued ?? 0} issued)
          </p>
        </div>
      </div>

      {/* ========================================================= */}
      {/* SECTION 2: DISTRIBUTION FILTERS                           */}
      {/* ========================================================= */}
      <Panel>
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div className="flex items-center gap-2">
            <svg className="w-4 h-4 text-nis-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-700">Distribution Filters & Search</h2>
          </div>
          <button
            type="button"
            onClick={handleResetFilters}
            className="text-xs font-semibold text-nis-primary hover:text-nis-primary-dark hover:underline"
          >
            Clear / Reset Filters
          </button>
        </div>

        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
          {/* Zone Selector */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">NIS Zone</label>
            <Select
              value={selectedZone}
              onChange={(e) => handleZoneChange(e.target.value)}
              className="!py-1.5 !text-xs"
            >
              <option value="">All NIS Zones (Nationwide)</option>
              {zones.map((z) => (
                <option key={z.id} value={z.id}>
                  {z.name} ({z.code})
                </option>
              ))}
            </Select>
          </div>

          {/* Command Cascading Selector */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">State Command</label>
            <Select
              value={selectedCommand}
              onChange={(e) => { setSelectedCommand(e.target.value); setCurrentPage(1); }}
              className="!py-1.5 !text-xs"
            >
              <option value="">All State Commands</option>
              {filteredCommandOptions.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name} ({c.state})
                </option>
              ))}
            </Select>
          </div>

          {/* Status Selector */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Distribution Status</label>
            <Select
              value={selectedStatus}
              onChange={(e) => { setSelectedStatus(e.target.value); setCurrentPage(1); }}
              className="!py-1.5 !text-xs"
            >
              <option value="">All Lifecycle Stages</option>
              <option value="AWAITING_DISPATCH">Awaiting Dispatch (HQ)</option>
              <option value="DISPATCHED">Dispatched / In Transit</option>
              <option value="RECEIVED">Received at Command</option>
              <option value="ISSUED">Issued to Applicant</option>
            </Select>
          </div>

          {/* Application Type */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Application Type</label>
            <Select
              value={selectedType}
              onChange={(e) => { setSelectedType(e.target.value); setCurrentPage(1); }}
              className="!py-1.5 !text-xs"
            >
              <option value="">All Types</option>
              <option value="NEW">New Applications</option>
              <option value="RENEWAL">Renewal Applications</option>
            </Select>
          </div>

          {/* Date Range: From */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Date From</label>
            <Input
              type="date"
              value={dateFrom}
              onChange={(e) => { setDateFrom(e.target.value); setCurrentPage(1); }}
              className="!py-1.5 !text-xs"
            />
          </div>

          {/* Date Range: To */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Date To</label>
            <Input
              type="date"
              value={dateTo}
              onChange={(e) => { setDateTo(e.target.value); setCurrentPage(1); }}
              className="!py-1.5 !text-xs"
            />
          </div>
        </div>

        {/* Search Row */}
        <div className="mt-3 flex gap-2">
          <div className="relative flex-1">
            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              type="text"
              placeholder="Search by Card Reference, Application Reference, Applicant Name, Waybill No, or Dispatch Reference..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter") void loadData(1); }}
              className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 placeholder-slate-400 focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary"
            />
          </div>
          <Button
            onClick={() => void loadData(1)}
            className="!py-2 !px-4 text-xs font-semibold"
          >
            Search
          </Button>
        </div>
      </Panel>

      {/* ========================================================= */}
      {/* SECTION 5: COMMAND DRILLDOWN BANNER (WHEN FILTERED)       */}
      {/* ========================================================= */}
      {activeCommandObj && (
        <div className="rounded-2xl border border-emerald-300 bg-gradient-to-r from-emerald-50 via-teal-50 to-white p-5 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center rounded-md bg-emerald-800 px-2 py-0.5 text-xs font-bold text-white">
                  Active Command Scope
                </span>
                <span className="text-xs font-medium text-emerald-800">
                  {zones.find((z) => z.id === activeCommandObj.zone_id)?.name || "NIS Zone"}
                </span>
              </div>
              <h2 className="mt-1 text-lg font-bold text-slate-900">
                {activeCommandObj.name} ({activeCommandObj.code})
              </h2>
              <p className="text-xs text-slate-600">
                Location: {activeCommandObj.state} State · Filter applied across distribution registry and operational stages.
              </p>
            </div>
            <Button
              variant="ghost"
              onClick={() => { setSelectedCommand(""); setCurrentPage(1); }}
              className="border border-emerald-300 bg-white !text-xs font-semibold text-emerald-900 shadow-2xs hover:bg-emerald-50"
            >
              ✕ Clear Command Scope
            </Button>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* SECTION 3: COMMAND DISTRIBUTION SUMMARY TABLE             */}
      {/* ========================================================= */}
      <Panel>
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h2 className="text-sm font-bold text-slate-900">Command Distribution Summary</h2>
            <p className="text-xs text-slate-500">
              Aggregated distribution inventory across State Commands nationwide
            </p>
          </div>
          <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
            {commandsSummary.length} Commands Tracked
          </span>
        </div>

        <div className="-mx-5 mt-3 overflow-x-auto">
          <table className="w-full text-left text-xs sm:text-sm">
            <thead className="border-y border-slate-200 bg-slate-50/75 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
              <tr>
                <th className="w-12 pl-5 py-3">S/N</th>
                <th className="px-3 py-3">Zone</th>
                <th className="px-3 py-3">Command Name</th>
                <th className="px-3 py-3 text-center">Total Produced</th>
                <th className="px-3 py-3 text-center">Awaiting Dispatch</th>
                <th className="px-3 py-3 text-center">In Transit</th>
                <th className="px-3 py-3 text-center">Received</th>
                <th className="px-3 py-3 text-center">Issued</th>
                <th className="pr-5 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {commandsSummary.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-8 text-center text-xs text-slate-500">
                    No command summary records match your filter criteria.
                  </td>
                </tr>
              ) : (
                commandsSummary.map((c) => {
                  const isCurrentScope = String(c.id) === selectedCommand;
                  return (
                    <tr
                      key={c.id}
                      className={`align-middle transition hover:bg-slate-50/70 ${
                        isCurrentScope ? "bg-emerald-50/40 font-medium" : ""
                      }`}
                    >
                      <td className="pl-5 py-3 font-mono text-slate-400">{c.sn}</td>
                      <td className="px-3 py-3 font-semibold text-slate-600">{c.zone_name}</td>
                      <td className="px-3 py-3">
                        <div className="font-semibold text-slate-900">{c.command_name}</div>
                      </td>
                      <td className="px-3 py-3 text-center font-bold tabular-nums text-slate-900">
                        {c.total_produced.toLocaleString()}
                      </td>
                      <td className="px-3 py-3 text-center tabular-nums font-semibold text-amber-800">
                        {c.awaiting_dispatch}
                      </td>
                      <td className="px-3 py-3 text-center tabular-nums font-semibold text-blue-800">
                        {c.in_transit}
                      </td>
                      <td className="px-3 py-3 text-center tabular-nums font-semibold text-emerald-800">
                        {c.received}
                      </td>
                      <td className="px-3 py-3 text-center tabular-nums text-slate-600">
                        {c.issued}
                      </td>
                      <td className="pr-5 py-3 text-right">
                        <Button
                          variant="ghost"
                          onClick={() => { setSelectedCommand(String(c.id)); setCurrentPage(1); }}
                          className={`!px-2.5 !py-1 !text-xs border shadow-2xs ${
                            isCurrentScope
                              ? "!bg-emerald-800 !text-white border-emerald-800"
                              : "border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
                          }`}
                        >
                          {isCurrentScope ? "Active Filter" : "View Details"}
                        </Button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </Panel>

      {/* ========================================================= */}
      {/* SECTION 4: DETAILED CARD DISTRIBUTION REGISTER            */}
      {/* ========================================================= */}
      <Panel>
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-sm font-bold text-slate-900">Detailed Card Distribution Register</h2>
              <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                {pagination?.total ?? distributions.length} Cards Found
              </span>
            </div>
            <p className="text-xs text-slate-500">
              Individual consignment dossiers, traceable dispatch metadata, and physical receipt records
            </p>
          </div>

          {selectedIds.length > 0 && (
            <div className="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 border border-emerald-200">
              <span className="text-xs font-semibold text-emerald-900">
                {selectedIds.length} card(s) selected
              </span>
              <button
                type="button"
                onClick={() => setSelectedIds([])}
                className="text-[11px] text-emerald-700 underline font-medium"
              >
                Clear
              </button>
            </div>
          )}
        </div>

        {loading ? (
          <div className="py-12 text-center">
            <Spinner label="Loading distribution register..." />
          </div>
        ) : distributions.length === 0 ? (
          <div className="py-12 text-center">
            <svg className="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 className="mt-2 text-sm font-semibold text-slate-900">No Distribution Records Found</h3>
            <p className="mt-1 text-xs text-slate-500">
              No produced residence cards match your search parameters or current command scope.
            </p>
            <div className="mt-4">
              <Button variant="ghost" onClick={handleResetFilters} className="border border-slate-300 text-xs">
                Reset All Filters
              </Button>
            </div>
          </div>
        ) : (
          <div className="-mx-5 mt-3 overflow-x-auto">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50/75 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="w-10 pl-5 py-3">
                    <input
                      type="checkbox"
                      checked={selectedIds.length > 0 && selectedIds.length === distributions.length}
                      onChange={handleSelectAll}
                      className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                    />
                  </th>
                  <th className="w-10 py-3">S/N</th>
                  <th className="px-3 py-3">Card Reference</th>
                  <th className="px-3 py-3">Application Reference</th>
                  <th className="px-3 py-3">Applicant Name</th>
                  <th className="px-3 py-3 text-center">Type</th>
                  <th className="px-3 py-3">Destination Command</th>
                  <th className="px-3 py-3">Production Date</th>
                  <th className="px-3 py-3">Dispatch Ref & Date</th>
                  <th className="px-3 py-3 text-center">Distribution Status</th>
                  <th className="pr-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {distributions.map((d, idx) => {
                  const isSelected = selectedIds.includes(d.id);
                  const snNumber = pagination ? (pagination.current_page - 1) * pagination.per_page + idx + 1 : idx + 1;

                  return (
                    <tr
                      key={d.id}
                      className={`align-middle transition hover:bg-slate-50/70 ${
                        isSelected ? "bg-emerald-50/30" : ""
                      }`}
                    >
                      <td className="pl-5 py-3.5">
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => toggleSelect(d.id)}
                          className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                        />
                      </td>
                      <td className="py-3.5 font-mono text-xs text-slate-400">{snNumber}</td>
                      <td className="px-3 py-3.5">
                        <span className="font-mono font-bold text-slate-900 text-xs">
                          {d.card_number}
                        </span>
                      </td>
                      <td className="px-3 py-3.5 font-mono text-xs text-slate-700">
                        {d.application_number}
                      </td>
                      <td className="px-3 py-3.5">
                        <div className="font-semibold text-slate-900">{d.applicant_name}</div>
                        {d.applicant_passport && (
                          <div className="text-[11px] font-mono text-slate-400">{d.applicant_passport}</div>
                        )}
                      </td>
                      <td className="px-3 py-3.5 text-center">
                        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold ${
                          d.application_type === "RENEWAL"
                            ? "bg-blue-50 text-blue-800 border border-blue-200"
                            : "bg-emerald-50 text-emerald-800 border border-emerald-200"
                        }`}>
                          {d.application_type || "NEW"}
                        </span>
                      </td>
                      <td className="px-3 py-3.5">
                        <div className="font-semibold text-slate-800">{d.destination_command}</div>
                        <div className="text-[11px] text-slate-500">{d.destination_zone}</div>
                      </td>
                      <td className="px-3 py-3.5 text-xs text-slate-600">
                        {d.produced_at ? dateTime(d.produced_at) : "—"}
                      </td>
                      <td className="px-3 py-3.5 text-xs">
                        {d.batch_reference ? (
                          <div>
                            <div className="font-mono font-semibold text-slate-900">{d.batch_reference}</div>
                            {d.dispatched_at && (
                              <div className="text-[11px] text-slate-500">{dateTime(d.dispatched_at)}</div>
                            )}
                          </div>
                        ) : (
                          <span className="text-slate-400 font-mono text-xs">Awaiting dispatch</span>
                        )}
                      </td>
                      <td className="px-3 py-3.5 text-center">
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                          d.status === "AWAITING_DISPATCH"
                            ? "bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20"
                            : d.status === "DISPATCHED"
                            ? "bg-blue-50 text-blue-800 ring-1 ring-inset ring-blue-600/20"
                            : d.status === "RECEIVED" || d.status === "AVAILABLE_FOR_ISSUANCE"
                            ? "bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-600/20"
                            : d.status === "ISSUED"
                            ? "bg-purple-50 text-purple-800 ring-1 ring-inset ring-purple-600/20"
                            : "bg-slate-100 text-slate-700"
                        }`}>
                          {d.status_label || d.status.replace(/_/g, " ")}
                        </span>
                      </td>
                      <td className="pr-5 py-3.5 text-right">
                        <Button
                          variant="ghost"
                          onClick={() => setDossierRecord(d)}
                          className="!px-2.5 !py-1 !text-xs border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs"
                        >
                          View Dossier
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Controls */}
        {pagination && pagination.last_page > 1 && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-600">
            <div>
              Showing page <span className="font-semibold text-slate-900">{pagination.current_page}</span> of{" "}
              <span className="font-semibold text-slate-900">{pagination.last_page}</span> (
              {pagination.total} total items)
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                disabled={pagination.current_page <= 1}
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                className="!py-1 !px-2.5 !text-xs border border-slate-300"
              >
                Previous
              </Button>
              <Button
                variant="ghost"
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() => setCurrentPage((p) => Math.min(pagination.last_page, p + 1))}
                className="!py-1 !px-2.5 !text-xs border border-slate-300"
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </Panel>

      {/* ========================================================= */}
      {/* MODAL 1: BATCH DISPATCH PACKAGING (HQ)                    */}
      {/* ========================================================= */}
      {showDispatchModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-base font-bold text-slate-900">
                  Consignment Dispatch Packaging ({selectedIds.length} cards)
                </h3>
                <p className="text-xs text-slate-500">
                  Record official transfer and courier waybill details from NIS Service Headquarters
                </p>
              </div>
              <button
                type="button"
                onClick={() => setShowDispatchModal(false)}
                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleDispatchSubmit} className="mt-4 space-y-4">
              <Field label="Consignment / Waybill Reference">
                <Input
                  placeholder="e.g. NIS-HQ-WAYBILL-2026-0042 (leave blank to auto-generate)"
                  value={dispatchRef}
                  onChange={(e) => setDispatchRef(e.target.value)}
                />
              </Field>

              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Official Courier / Service">
                  <Input
                    placeholder="e.g. NIPOST Express Pouch / NIS Escort"
                    value={courierName}
                    onChange={(e) => setCourierName(e.target.value)}
                    required
                  />
                </Field>
                <Field label="Tracking Number (Optional)">
                  <Input
                    placeholder="e.g. TRK-88291039"
                    value={trackingNumber}
                    onChange={(e) => setTrackingNumber(e.target.value)}
                  />
                </Field>
              </div>

              <Field label="Dispatch / Security Notes (Optional)">
                <Input
                  placeholder="e.g. Sealed security bag #A-44910 with security tamper evident tags."
                  value={dispatchNotes}
                  onChange={(e) => setDispatchNotes(e.target.value)}
                />
              </Field>

              <div className="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <Button variant="ghost" type="button" onClick={() => setShowDispatchModal(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={actionLoading} className="!bg-amber-600 hover:!bg-amber-700 text-white">
                  {actionLoading ? "Packaging Dispatch..." : `Confirm Dispatch of ${selectedIds.length} Cards`}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* MODAL 2: CONFIRM PHYSICAL RECEIPT (COMMAND GEOFENCE)      */}
      {/* ========================================================= */}
      {showReceiptModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-base font-bold text-slate-900">
                  Confirm Physical Card Receipt at Command ({selectedIds.length} cards)
                </h3>
                <p className="text-xs text-slate-500">
                  Verify physical consignment arrival inside authorized Command perimeters
                </p>
              </div>
              <button
                type="button"
                onClick={() => setShowReceiptModal(false)}
                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleReceiptSubmit} className="mt-4 space-y-4">
              {/* GPS Geofence Box */}
              <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-3.5 text-xs">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-blue-950">Command Geofence Verification:</span>
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={captureGps}
                    disabled={gpsLoading}
                    className="border border-blue-300 bg-white !px-2.5 !py-1 !text-xs font-semibold text-blue-900 shadow-2xs hover:bg-blue-50"
                  >
                    {gpsLoading ? "Acquiring GPS..." : coords ? "↻ Re-acquire GPS" : "📍 Acquire Office GPS"}
                  </Button>
                </div>

                <div className="mt-2 text-slate-700">
                  {coords ? (
                    <div className="flex items-center gap-2 font-mono font-semibold text-emerald-800 bg-emerald-100/70 p-2 rounded-lg border border-emerald-300">
                      <span>✓ GPS Fixed:</span>
                      <span>Lat: {coords.latitude.toFixed(5)}, Lng: {coords.longitude.toFixed(5)}</span>
                    </div>
                  ) : (
                    <p className="text-slate-500 italic">
                      Click &ldquo;Acquire Office GPS&rdquo; to record authenticated coordinates.
                    </p>
                  )}
                </div>

                <div className="mt-2 flex items-center justify-end">
                  <button
                    type="button"
                    onClick={() => setShowOverride(!showOverride)}
                    className="text-[11px] font-semibold text-blue-800 hover:underline"
                  >
                    {showOverride ? "Hide Supervisor Override" : "Station Offline / Supervisor Override"}
                  </button>
                </div>
              </div>

              {showOverride && (
                <Field label="Authorized Supervisor Override Reason" error={undefined}>
                  <Input
                    placeholder="e.g. Shielded armory vault, GPS signal blocked by concrete bunker..."
                    value={overrideReason}
                    onChange={(e) => setOverrideReason(e.target.value)}
                  />
                </Field>
              )}

              <Field label="Consignment Seal & Manifest Inspection Notes">
                <Input
                  placeholder="e.g. Security seal inspected intact; cards counted and verified against manifest."
                  value={receiptNotes}
                  onChange={(e) => setReceiptNotes(e.target.value)}
                />
              </Field>

              <div className="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <Button variant="ghost" type="button" onClick={() => setShowReceiptModal(false)}>
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={actionLoading || gpsLoading}
                  className="!bg-emerald-800 hover:!bg-emerald-900 text-white"
                >
                  {actionLoading ? "Verifying Receipt..." : `Confirm Receipt of ${selectedIds.length} Cards`}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* MODAL 3: INDIVIDUAL CARD DISTRIBUTION DOSSIER             */}
      {/* ========================================================= */}
      {dossierRecord && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-nis-primary">
                  NIS Residence Card Logistics Dossier
                </span>
                <h3 className="text-lg font-bold text-slate-900 font-mono">
                  {dossierRecord.card_number}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setDossierRecord(null)}
                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                ✕
              </button>
            </div>

            <div className="mt-4 space-y-4 text-xs">
              {/* Applicant & Destination Grid */}
              <div className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <div>
                  <span className="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Applicant Name:</span>
                  <div className="font-bold text-slate-900 text-sm mt-0.5">{dossierRecord.applicant_name}</div>
                  <div className="font-mono text-slate-600">{dossierRecord.applicant_passport}</div>
                  <div className="mt-1">
                    <span className="inline-flex items-center rounded bg-emerald-100 px-1.5 py-0.5 font-bold text-emerald-800">
                      {dossierRecord.application_type || "NEW"} APPLICATION
                    </span>
                  </div>
                </div>

                <div>
                  <span className="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Destination Command:</span>
                  <div className="font-bold text-slate-900 text-sm mt-0.5">{dossierRecord.destination_command}</div>
                  <div className="text-slate-600 font-medium">{dossierRecord.destination_zone}</div>
                  <div className="mt-1 text-slate-500">
                    Application Ref: <span className="font-mono font-semibold text-slate-800">{dossierRecord.application_number}</span>
                  </div>
                </div>
              </div>

              {/* Lifecycle Audit Trail */}
              <div className="space-y-3">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700">Traceable Lifecycle Milestones</h4>

                {/* 1. Production */}
                <div className="flex items-start gap-3 rounded-lg border border-slate-200 p-3 bg-white">
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-700 font-bold">1</span>
                  <div className="flex-1">
                    <div className="font-bold text-slate-900">Card Production & Personalization</div>
                    <div className="text-slate-500">Service Headquarters Sauka Abuja · Central Secure Printing Facility</div>
                    <div className="mt-1 text-slate-700">
                      Production Date: <span className="font-medium">{dossierRecord.produced_at ? dateTime(dossierRecord.produced_at) : "Confirmed Printed"}</span>
                    </div>
                  </div>
                </div>

                {/* 2. Dispatch */}
                <div className="flex items-start gap-3 rounded-lg border border-slate-200 p-3 bg-white">
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 font-bold">2</span>
                  <div className="flex-1">
                    <div className="font-bold text-slate-900">Consignment Dispatch & Transfer</div>
                    {dossierRecord.dispatched_at ? (
                      <div className="mt-1 space-y-0.5 text-slate-700">
                        <div>Consignment Reference: <span className="font-mono font-bold text-slate-900">{dossierRecord.batch_reference}</span></div>
                        <div>Courier Service: <span className="font-medium">{dossierRecord.courier_service || "NIS Escort"}</span></div>
                        {dossierRecord.waybill_number && <div>Tracking Number: <span className="font-mono">{dossierRecord.waybill_number}</span></div>}
                        <div>Dispatched: {dateTime(dossierRecord.dispatched_at)} by {dossierRecord.dispatched_by}</div>
                        {dossierRecord.dispatch_notes && <div className="italic text-slate-500 mt-0.5">&quot;{dossierRecord.dispatch_notes}&quot;</div>}
                      </div>
                    ) : (
                      <div className="text-amber-800 italic mt-0.5">Awaiting consignment packaging at Headquarters.</div>
                    )}
                  </div>
                </div>

                {/* 3. Receipt */}
                <div className="flex items-start gap-3 rounded-lg border border-slate-200 p-3 bg-white">
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-800 font-bold">3</span>
                  <div className="flex-1">
                    <div className="font-bold text-slate-900">Command Physical Receipt & Geofence Verification</div>
                    {dossierRecord.received_at ? (
                      <div className="mt-1 space-y-0.5 text-slate-700">
                        <div className="flex items-center gap-1.5">
                          <span className="font-semibold text-emerald-800">✓ Received at Command:</span>
                          <span>{dateTime(dossierRecord.received_at)} by {dossierRecord.received_by}</span>
                        </div>
                        {dossierRecord.geofence_verified_receipt && (
                          <div className="font-semibold text-emerald-700">✓ Geofence perimeter confirmed via GPS coordinates.</div>
                        )}
                        {dossierRecord.receipt_notes && <div className="italic text-slate-500 mt-0.5">&quot;{dossierRecord.receipt_notes}&quot;</div>}
                      </div>
                    ) : (
                      <div className="text-slate-500 italic mt-0.5">Pending physical arrival and geofenced receipt confirmation at destination Command.</div>
                    )}
                  </div>
                </div>

                {/* 4. Issuance */}
                <div className="flex items-start gap-3 rounded-lg border border-slate-200 p-3 bg-white">
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 font-bold">4</span>
                  <div className="flex-1">
                    <div className="font-bold text-slate-900">Card Issuance to Applicant</div>
                    {dossierRecord.issued_at ? (
                      <div className="mt-1 text-slate-700">
                        <span className="font-semibold text-emerald-800">✓ Issued:</span> {dateTime(dossierRecord.issued_at)} by {dossierRecord.issued_by}
                      </div>
                    ) : (
                      <div className="text-slate-500 italic mt-0.5">Available for collection / issuance once received at Command.</div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-6 flex justify-end border-t border-slate-100 pt-3">
              <Button onClick={() => setDossierRecord(null)} className="text-xs">
                Close Dossier
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
