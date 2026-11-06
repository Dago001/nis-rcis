"use client";

import { useCallback, useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import type { CardDistribution, CardDistributionStatus } from "@/lib/types";

interface DistributionStats {
  awaiting_dispatch: number;
  dispatched: number;
  received: number;
  available_for_issuance: number;
  issued: number;
}

export default function CardDistributionPage() {
  const me = useStaff();
  const [tab, setTab] = useState<"AWAITING_DISPATCH" | "DISPATCHED" | "RECEIVED" | "ALL">("AWAITING_DISPATCH");
  const [distributions, setDistributions] = useState<CardDistribution[] | null>(null);
  const [stats, setStats] = useState<DistributionStats | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Dispatch batch inputs
  const [dispatchRef, setDispatchRef] = useState("");
  const [courierName, setCourierName] = useState("");
  const [trackingNumber, setTrackingNumber] = useState("");

  // Receipt confirmation inputs & GPS
  const [gpsLoading, setGpsLoading] = useState(false);
  const [coords, setCoords] = useState<{ latitude: number; longitude: number } | null>(null);
  const [overrideReason, setOverrideReason] = useState("");
  const [showOverride, setShowOverride] = useState(false);
  const [receiptNotes, setReceiptNotes] = useState("");

  const loadData = useCallback(async () => {
    try {
      const res = await api<{
        data: CardDistribution[];
        stats: DistributionStats;
      }>("staff", `card-distributions?status=${tab === "ALL" ? "" : tab}`);
      setDistributions(res.data);
      setStats(res.stats);
      setSelectedIds([]);
    } catch (e) {
      if (e instanceof ApiError) setError(e.message);
    }
  }, [tab]);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  // Request browser GPS coordinates for geofence verification
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
        setNotice(`GPS Coordinates successfully acquired (${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}). Ready to verify Command perimeter.`);
      },
      (err) => {
        setGpsLoading(false);
        setError(`Unable to retrieve GPS coordinates: ${err.message}. If operating in an authorized offline station, provide supervisor override reason.`);
        setShowOverride(true);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  };

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked && distributions) {
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

  // Submit HQ Dispatch Batch
  const handleDispatch = async () => {
    if (selectedIds.length === 0) return;
    setError(null);
    setNotice(null);
    setLoading(true);

    try {
      const res = await api<{ message: string; count: number }>("staff", "card-distributions/dispatch", {
        method: "POST",
        json: {
          distribution_ids: selectedIds,
          dispatch_reference: dispatchRef || undefined,
          courier_service: courierName || undefined,
          tracking_number: trackingNumber || undefined,
        },
      });
      setNotice(res.message);
      setDispatchRef("");
      setCourierName("");
      setTrackingNumber("");
      await loadData();
    } catch (e) {
      if (e instanceof ApiError) setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  // Submit Command Physical Receipt Batch with Geofence verification
  const handleConfirmReceipt = async () => {
    if (selectedIds.length === 0) return;
    setError(null);
    setNotice(null);
    setLoading(true);

    try {
      const res = await api<{ message: string; count: number; geofence?: any }>("staff", "card-distributions/receive", {
        method: "POST",
        json: {
          distribution_ids: selectedIds,
          latitude: coords?.latitude,
          longitude: coords?.longitude,
          override_reason: overrideReason || undefined,
          notes: receiptNotes || undefined,
        },
      });
      setNotice(res.message);
      setReceiptNotes("");
      setOverrideReason("");
      setCoords(null);
      await loadData();
    } catch (e) {
      if (e instanceof ApiError) setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  const isHeadquarters = me.is_headquarters || me.role === "SuperAdmin" || me.role === "HeadquartersAdmin" || me.role === "CardDistributionOfficer";

  return (
    <div className="space-y-6">
      <PageTitle
        title="Card Distribution & Logistics"
        subtitle="Manage secure transfer of produced cards from Headquarters to State Commands."
      />

      {notice && <Alert tone="success">{notice}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}

      {/* Overview Stats */}
      {stats && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-amber-700">Awaiting Dispatch (HQ)</div>
            <div className="mt-1 text-2xl font-bold text-amber-900">{stats.awaiting_dispatch}</div>
            <p className="mt-1 text-[11px] text-amber-800">Cards printed at HQ ready for consignment packaging</p>
          </div>
          <div className="rounded-xl border border-blue-200 bg-blue-50/60 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-blue-700">In Transit</div>
            <div className="mt-1 text-2xl font-bold text-blue-900">{stats.dispatched}</div>
            <p className="mt-1 text-[11px] text-blue-800">En route from Sauka to destination Commands</p>
          </div>
          <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-emerald-700">Received at Command</div>
            <div className="mt-1 text-2xl font-bold text-emerald-900">{stats.received + stats.available_for_issuance}</div>
            <p className="mt-1 text-[11px] text-emerald-800">Physically confirmed & available for issuance</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-700">Issued to Holders</div>
            <div className="mt-1 text-2xl font-bold text-slate-900">{stats.issued}</div>
            <p className="mt-1 text-[11px] text-slate-800">Handed over to applicants at local issuance desks</p>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-slate-200 space-x-4 text-sm font-medium">
        <button
          className={`pb-3 border-b-2 transition ${tab === "AWAITING_DISPATCH" ? "border-emerald-700 text-emerald-800 font-bold" : "border-transparent text-slate-500 hover:text-slate-700"}`}
          onClick={() => setTab("AWAITING_DISPATCH")}
        >
          Awaiting Dispatch ({stats?.awaiting_dispatch ?? 0})
        </button>
        <button
          className={`pb-3 border-b-2 transition ${tab === "DISPATCHED" ? "border-emerald-700 text-emerald-800 font-bold" : "border-transparent text-slate-500 hover:text-slate-700"}`}
          onClick={() => setTab("DISPATCHED")}
        >
          In Transit / Dispatched ({stats?.dispatched ?? 0})
        </button>
        <button
          className={`pb-3 border-b-2 transition ${tab === "RECEIVED" ? "border-emerald-700 text-emerald-800 font-bold" : "border-transparent text-slate-500 hover:text-slate-700"}`}
          onClick={() => setTab("RECEIVED")}
        >
          Received at Command ({stats?.received ?? 0})
        </button>
        <button
          className={`pb-3 border-b-2 transition ${tab === "ALL" ? "border-emerald-700 text-emerald-800 font-bold" : "border-transparent text-slate-500 hover:text-slate-700"}`}
          onClick={() => setTab("ALL")}
        >
          All Logistics Records
        </button>
      </div>

      {/* Action Banners */}
      {selectedIds.length > 0 && tab === "AWAITING_DISPATCH" && isHeadquarters && (
        <div className="rounded-xl border border-emerald-300 bg-emerald-50/70 p-4">
          <h3 className="font-semibold text-emerald-900 text-sm">
            Consignment Dispatch Packaging ({selectedIds.length} card{selectedIds.length > 1 ? "s" : ""} selected)
          </h3>
          <p className="text-xs text-emerald-800 mt-0.5">
            Record secure courier / diplomatic pouch transfer details from NIS Service Headquarters to destination State Commands.
          </p>
          <div className="mt-3 grid gap-3 sm:grid-cols-3">
            <Field label="Consignment / Waybill Ref">
              <Input
                placeholder="e.g. NIS-HQ-WAYBILL-2026-0042"
                value={dispatchRef}
                onChange={(e) => setDispatchRef(e.target.value)}
              />
            </Field>
            <Field label="Official Courier / Escort">
              <Input
                placeholder="e.g. NIPOST Courier / NIS Escort"
                value={courierName}
                onChange={(e) => setCourierName(e.target.value)}
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
          <div className="mt-3 flex justify-end">
            <Button onClick={handleDispatch} disabled={loading}>
              {loading ? "Dispatching..." : `Mark ${selectedIds.length} Card(s) as Dispatched`}
            </Button>
          </div>
        </div>
      )}

      {selectedIds.length > 0 && tab === "DISPATCHED" && (
        <div className="rounded-xl border border-blue-300 bg-blue-50/70 p-4">
          <h3 className="font-semibold text-blue-900 text-sm">
            Confirm Physical Card Receipt at Command ({selectedIds.length} card{selectedIds.length > 1 ? "s" : ""} selected)
          </h3>
          <p className="text-xs text-blue-800 mt-0.5">
            Verifies that the cards have physically arrived at your designated NIS Command and are intact before advancing them to Collection / Issuance status.
          </p>

          <div className="mt-3 space-y-3">
            {/* GPS Geofence Verification Status */}
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-blue-200 bg-white p-3 text-xs">
              <span className="font-semibold text-slate-800">Physical Geofence Verification:</span>
              {coords ? (
                <span className="font-mono text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  ✓ Lat: {coords.latitude.toFixed(4)}, Lng: {coords.longitude.toFixed(4)}
                </span>
              ) : (
                <span className="text-slate-500">Office location coordinates required</span>
              )}
              <Button
                variant="ghost"
                className="!px-2 !py-1 !text-xs border border-slate-300"
                onClick={captureGps}
                disabled={gpsLoading}
              >
                {gpsLoading ? "Acquiring GPS..." : coords ? "Re-acquire GPS" : "📍 Acquire Office GPS"}
              </Button>
              <button
                type="button"
                onClick={() => setShowOverride(!showOverride)}
                className="text-blue-700 underline text-xs ml-auto"
              >
                {showOverride ? "Hide Override" : "Station Offline / Override"}
              </button>
            </div>

            {showOverride && (
              <Field label="Authorized Supervisor Override Reason" error={undefined}>
                <Input
                  placeholder="Required if GPS hardware is unavailable or receiving inside shielded vault..."
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                />
              </Field>
            )}

            <Field label="Receipt Inspection Notes">
              <Input
                placeholder="e.g. Consignment seal inspected intact; 5 card booklets verified against manifest."
                value={receiptNotes}
                onChange={(e) => setReceiptNotes(e.target.value)}
              />
            </Field>

            <div className="flex justify-end gap-2">
              <Button onClick={handleConfirmReceipt} disabled={loading || gpsLoading}>
                {loading ? "Verifying..." : `Confirm Physical Receipt of ${selectedIds.length} Card(s)`}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Main Table */}
      <Panel>
        <div className="flex items-center justify-between">
          <div className="text-xs font-semibold text-slate-500">
            {distributions ? `${distributions.length} card logistics record(s)` : "Loading..."}
          </div>
          <Button variant="ghost" className="!px-2 !py-1 !text-xs" onClick={() => loadData()}>
            ↻ Refresh
          </Button>
        </div>

        {!distributions ? (
          <div className="py-8 text-center"><Spinner /></div>
        ) : distributions.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">No card distribution records found for this stage.</div>
        ) : (
          <div className="-mx-5 mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="w-10 pl-5 py-3">
                    <input
                      type="checkbox"
                      checked={selectedIds.length > 0 && selectedIds.length === distributions.length}
                      onChange={handleSelectAll}
                      className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                    />
                  </th>
                  <th className="py-3">Card & Applicant</th>
                  <th className="py-3">Destination Command</th>
                  <th className="py-3">Batch Reference</th>
                  <th className="py-3">Status</th>
                  <th className="py-3">Dispatched</th>
                  <th className="pr-5 py-3">Received at Command</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {distributions.map((d) => (
                  <tr key={d.id} className="align-middle hover:bg-slate-50/60">
                    <td className="pl-5 py-3.5">
                      <input
                        type="checkbox"
                        checked={selectedIds.includes(d.id)}
                        onChange={() => toggleSelect(d.id)}
                        className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                      />
                    </td>
                    <td className="py-3.5">
                      <div className="font-semibold text-slate-900 font-mono text-xs">{d.card_number}</div>
                      <div className="text-xs text-slate-600">{d.applicant_name}</div>
                      <div className="text-[11px] text-slate-400 font-mono">{d.applicant_passport}</div>
                    </td>
                    <td className="py-3.5">
                      <div className="font-medium text-slate-800">{d.destination_command}</div>
                      <div className="text-xs text-slate-500">{d.destination_zone}</div>
                    </td>
                    <td className="py-3.5 font-mono text-xs text-slate-600">
                      {d.batch_reference}
                    </td>
                    <td className="py-3.5">
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        d.status === "AWAITING_DISPATCH"
                          ? "bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20"
                          : d.status === "DISPATCHED"
                          ? "bg-blue-50 text-blue-800 ring-1 ring-inset ring-blue-600/20"
                          : d.status === "RECEIVED" || d.status === "AVAILABLE_FOR_ISSUANCE"
                          ? "bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-600/20"
                          : "bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-600/20"
                      }`}>
                        {d.status.replace(/_/g, " ")}
                      </span>
                    </td>
                    <td className="py-3.5 text-xs text-slate-600">
                      {d.dispatched_at ? (
                        <div>
                          <div>{dateTime(d.dispatched_at)}</div>
                          <div className="text-slate-400 text-[11px]">{d.dispatched_by}</div>
                        </div>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="pr-5 py-3.5 text-xs text-slate-600">
                      {d.received_at ? (
                        <div>
                          <div className="text-emerald-700 font-medium">{dateTime(d.received_at)}</div>
                          <div className="text-slate-400 text-[11px]">{d.received_by}</div>
                        </div>
                      ) : (
                        <span className="text-slate-400">Pending arrival</span>
                      )}
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
