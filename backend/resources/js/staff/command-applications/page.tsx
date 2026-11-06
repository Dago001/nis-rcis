"use client";

import Link from "next/link";
import { useSearchParams, useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { nisDate, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";
import { useI18n } from "@/components/I18nProvider";

interface CommandInfo {
  id: number;
  name: string;
  code: string;
  state: string;
}

interface CommandApplicationsResponse {
  status: string;
  command: CommandInfo | null;
  summary: {
    total: number;
    awaiting_vetting: number;
    awaiting_biometrics: number;
    awaiting_approval: number;
    issued: number;
  };
  enrollment_centers: { id: number; name: string; code: string; command_id: number }[];
  data: Application[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export default function CommandApplicationsPage() {
  const { user } = useStaff();
  const { t } = useI18n();
  const searchParams = useSearchParams();
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<CommandApplicationsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Filters State
  const [reference, setReference] = useState(searchParams.get("reference") || "");
  const [applicantName, setApplicantName] = useState(searchParams.get("applicant_name") || "");
  const [appType, setAppType] = useState(searchParams.get("application_type") || "");
  const [category, setCategory] = useState(searchParams.get("category") || "");
  const [status, setStatus] = useState(searchParams.get("status") || "");
  const [dateFrom, setDateFrom] = useState(searchParams.get("date_from") || "");
  const [dateTo, setDateTo] = useState(searchParams.get("date_to") || "");
  const [centerId, setCenterId] = useState(searchParams.get("enrollment_center_id") || "");
  const [page, setPage] = useState(parseInt(searchParams.get("page") || "1", 10));

  const commandIdParam = searchParams.get("command_id");

  const fetchData = async (targetPage = page) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      params.set("page", String(targetPage));
      if (commandIdParam) params.set("command_id", commandIdParam);
      if (reference.trim()) params.set("reference", reference.trim());
      if (applicantName.trim()) params.set("applicant_name", applicantName.trim());
      if (appType) params.set("application_type", appType);
      if (category) params.set("category", category);
      if (status) params.set("status", status);
      if (dateFrom) params.set("date_from", dateFrom);
      if (dateTo) params.set("date_to", dateTo);
      if (centerId) params.set("enrollment_center_id", centerId);

      const res = await api.get<CommandApplicationsResponse>(`/staff/command-applications?${params.toString()}`);
      setData(res);
      setPage(targetPage);
    } catch (err: any) {
      setError(err?.message || "Failed to load command applications");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData(1);
  }, [commandIdParam, appType, category, status, centerId]);

  const handleFilterSubmit = (e: FormEvent) => {
    e.preventDefault();
    fetchData(1);
  };

  const handleClearFilters = () => {
    setReference("");
    setApplicantName("");
    setAppType("");
    setCategory("");
    setStatus("");
    setDateFrom("");
    setDateTo("");
    setCenterId("");
    setTimeout(() => fetchData(1), 0);
  };

  const commandTitle = data?.command?.name || user.command || "Assigned Command";
  const summary = data?.summary;
  const meta = data?.meta;

  return (
    <div className="space-y-6 pb-12">
      {/* HEADER */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <PageTitle
            title={t("Command Applications")}
            subtitle={`${commandTitle} — ${t("Official Residence Card Applications & Jurisdictional Intake")}`}
          />
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => fetchData(page)}
            disabled={loading}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-50"
          >
            <svg className={`w-3.5 h-3.5 text-nis-primary ${loading ? "animate-spin" : ""}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
            </svg>
            <span>{loading ? t("Refreshing…") : t("Refresh Records")}</span>
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

      {/* SUMMARY COUNTERS */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block">{t("Total Applications")}</span>
          <span className="text-xl font-bold text-slate-900 mt-1 block tabular-nums">
            {summary ? summary.total.toLocaleString() : "—"}
          </span>
          <span className="text-[10px] text-slate-400 mt-0.5 block">{t("Assigned Command Volume")}</span>
        </div>
        <div className="rounded-xl border border-amber-200 bg-amber-50/40 p-3.5 shadow-2xs">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-amber-800 block">{t("Awaiting Vetting")}</span>
          <span className="text-xl font-bold text-amber-900 mt-1 block tabular-nums">
            {summary ? summary.awaiting_vetting.toLocaleString() : "—"}
          </span>
          <span className="text-[10px] text-amber-700 mt-0.5 block">{t("Document verification")}</span>
        </div>
        <div className="rounded-xl border border-blue-200 bg-blue-50/40 p-3.5 shadow-2xs">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-blue-800 block">{t("Awaiting Biometrics")}</span>
          <span className="text-xl font-bold text-blue-900 mt-1 block tabular-nums">
            {summary ? summary.awaiting_biometrics.toLocaleString() : "—"}
          </span>
          <span className="text-[10px] text-blue-700 mt-0.5 block">{t("Enrollment appointments")}</span>
        </div>
        <div className="rounded-xl border border-indigo-200 bg-indigo-50/40 p-3.5 shadow-2xs">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-indigo-800 block">{t("Awaiting Approval")}</span>
          <span className="text-xl font-bold text-indigo-900 mt-1 block tabular-nums">
            {summary ? summary.awaiting_approval.toLocaleString() : "—"}
          </span>
          <span className="text-[10px] text-indigo-700 mt-0.5 block">{t("Final officer adjudication")}</span>
        </div>
        <div className="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3.5 shadow-2xs col-span-2 sm:col-span-1">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-emerald-800 block">{t("Cards Issued")}</span>
          <span className="text-xl font-bold text-emerald-900 mt-1 block tabular-nums">
            {summary ? summary.issued.toLocaleString() : "—"}
          </span>
          <span className="text-[10px] text-emerald-700 mt-0.5 block">{t("Delivered to applicants")}</span>
        </div>
      </div>

      {/* FILTER BAR WITH ALL 7 SPECIFIED FILTERS */}
      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form onSubmit={handleFilterSubmit} className="space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {/* Filter 1: Application Reference */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Application Reference")}</label>
              <input
                type="text"
                value={reference}
                onChange={(e) => setReference(e.target.value)}
                placeholder={t("e.g. RC-2026-100001 or NIS-...")}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              />
            </div>

            {/* Filter 2: Applicant Name */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Applicant Name")}</label>
              <input
                type="text"
                value={applicantName}
                onChange={(e) => setApplicantName(e.target.value)}
                placeholder={t("Surname or forename...")}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              />
            </div>

            {/* Filter 3: Application Type */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Application Type")}</label>
              <select
                value={appType}
                onChange={(e) => setAppType(e.target.value)}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              >
                <option value="">{t("All Types")}</option>
                <option value="NEW">{t("New Application")}</option>
                <option value="RENEWAL">{t("Renewal Application")}</option>
                <option value="REPLACEMENT">{t("Replacement Card")}</option>
              </select>
            </div>

            {/* Filter 4: Application Category */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Application Category")}</label>
              <select
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              >
                <option value="">{t("All Categories")}</option>
                <option value="GENERAL">{t("General / Regular")}</option>
                <option value="STUDENT">{t("Student / Educational")}</option>
                <option value="BUSINESS">{t("Business / Corporate")}</option>
                <option value="EMPLOYEE">{t("Expatriate Employment")}</option>
                <option value="MISSIONARY">{t("Missionary / Volunteer")}</option>
                <option value="DEPENDANT">{t("Dependant / Family")}</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1 border-t border-slate-100">
            {/* Filter 5: Processing Status */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Processing Status")}</label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              >
                <option value="">{t("All Statuses")}</option>
                <option value="PENDING_APPROVAL">{t("Awaiting Vetting")}</option>
                <option value="VETTED">{t("Vetted (Awaiting Biometrics)")}</option>
                <option value="APPROVED_FOR_BIOMETRICS">{t("Approved for Biometrics")}</option>
                <option value="BIOMETRICS_CAPTURED">{t("Biometrics Captured (Awaiting Approval)")}</option>
                <option value="APPROVED">{t("Approved (Awaiting Production)")}</option>
                <option value="READY_FOR_COLLECTION">{t("Ready for Collection")}</option>
                <option value="ISSUED">{t("Card Issued")}</option>
                <option value="QUERIED">{t("Queried")}</option>
                <option value="REJECTED">{t("Rejected")}</option>
              </select>
            </div>

            {/* Filter 6: Date Range (From & To) */}
            <div className="grid grid-cols-2 gap-1.5">
              <div>
                <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("From Date")}</label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                  className="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
                />
              </div>
              <div>
                <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("To Date")}</label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                  className="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
                />
              </div>
            </div>

            {/* Filter 7: Enrollment Centre */}
            <div>
              <label className="block text-[11px] font-medium text-slate-600 mb-1">{t("Enrollment Centre")}</label>
              <select
                value={centerId}
                onChange={(e) => setCenterId(e.target.value)}
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-nis-primary focus:ring-1 focus:ring-nis-primary"
              >
                <option value="">{t("All Command Centres")}</option>
                {(data?.enrollment_centers || []).map((ec) => (
                  <option key={ec.id} value={ec.id}>
                    {ec.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Submit & Reset Buttons */}
            <div className="flex items-end gap-2">
              <button
                type="submit"
                className="flex-1 rounded-lg bg-nis-primary px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-nis-primary-dark transition text-center"
              >
                {t("Apply Filters")}
              </button>
              {(reference || applicantName || appType || category || status || dateFrom || dateTo || centerId) && (
                <button
                  type="button"
                  onClick={handleClearFilters}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                >
                  {t("Reset")}
                </button>
              )}
            </div>
          </div>
        </form>
      </div>

      {/* APPLICATIONS TABLE WITH SPECIFIED 9 COLUMNS */}
      <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="text-sm font-bold text-slate-900">{t("Command Application Records")}</h2>
            <p className="text-xs text-slate-500">
              {t("Showing")} {meta ? `${(meta.current_page - 1) * meta.per_page + 1} - ${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString()}` : "—"} {t("applications")}
            </p>
          </div>
        </div>

        {loading ? (
          <div className="py-20 flex justify-center">
            <Spinner label={t("Loading command applications...")} />
          </div>
        ) : !data?.data?.length ? (
          <div className="py-20 text-center text-xs text-slate-500">
            {t("No applications found matching the selected Command criteria.")}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-slate-200 bg-slate-50/70 text-slate-500 font-semibold">
                <tr>
                  <th className="px-3.5 py-3 w-12 text-center">{t("S/N")}</th>
                  <th className="px-3.5 py-3">{t("Application Reference")}</th>
                  <th className="px-3.5 py-3">{t("Applicant Name")}</th>
                  <th className="px-3.5 py-3">{t("Application Type")}</th>
                  <th className="px-3.5 py-3">{t("Application Category")}</th>
                  <th className="px-3.5 py-3">{t("Enrollment Centre")}</th>
                  <th className="px-3.5 py-3">{t("Current Processing Stage")}</th>
                  <th className="px-3.5 py-3">{t("Submission Date")}</th>
                  <th className="px-3.5 py-3 text-center">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {data.data.map((app, index) => {
                  const sn = meta ? (meta.current_page - 1) * meta.per_page + index + 1 : index + 1;
                  const isRenewal = (app.application_type || "").toUpperCase() === "RENEWAL";
                  return (
                    <tr key={app.id} className="hover:bg-slate-50/60 transition">
                      <td className="px-3.5 py-3 text-center font-mono text-slate-400 font-medium">
                        {sn}
                      </td>
                      <td className="px-3.5 py-3 font-mono font-semibold text-slate-900">
                        <Link href={`/staff/applications/${app.id}`} className="hover:text-nis-primary transition">
                          {app.application_number}
                        </Link>
                        {app.reference_number && (
                          <div className="text-[10px] text-slate-400 font-normal">{app.reference_number}</div>
                        )}
                      </td>
                      <td className="px-3.5 py-3 font-medium text-slate-900">
                        {app.particulars?.forenames ? `${app.particulars.forenames} ${app.particulars.surname || ""}` : (app as any).fullname || "—"}
                        <div className="text-[10px] text-slate-400">{app.particulars?.nationality || "—"}</div>
                      </td>
                      <td className="px-3.5 py-3">
                        <span className={`inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold ${
                          isRenewal ? "bg-blue-100 text-blue-800" : "bg-emerald-100 text-emerald-800"
                        }`}>
                          {applicationType(app.application_type)}
                        </span>
                      </td>
                      <td className="px-3.5 py-3 text-slate-700 font-medium">
                        {app.category || "General"}
                      </td>
                      <td className="px-3.5 py-3 text-slate-700">
                        <div>{app.enrollment_center?.name || commandTitle}</div>
                        {app.enrollment_center?.code && (
                          <div className="text-[10px] text-slate-400">{app.enrollment_center.code}</div>
                        )}
                      </td>
                      <td className="px-3.5 py-3">
                        <StatusBadge status={app.status} />
                      </td>
                      <td className="px-3.5 py-3 font-mono text-slate-600 tabular-nums">
                        {nisDate(app.submitted_at)}
                      </td>
                      <td className="px-3.5 py-3 text-center">
                        <Link
                          href={`/staff/applications/${app.id}`}
                          className="inline-flex items-center rounded bg-slate-100 hover:bg-nis-primary hover:text-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition"
                        >
                          {t("View")}
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* PAGINATION CONTROLS */}
        {meta && meta.last_page > 1 && (
          <div className="p-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
            <div>
              {t("Page")} <span className="font-semibold text-slate-800">{meta.current_page}</span> {t("of")}{" "}
              <span className="font-semibold text-slate-800">{meta.last_page}</span>
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => fetchData(meta.current_page - 1)}
                disabled={meta.current_page <= 1 || loading}
                className="rounded border border-slate-300 px-2.5 py-1 font-medium hover:bg-slate-50 disabled:opacity-40 transition"
              >
                {t("Previous")}
              </button>
              <button
                type="button"
                onClick={() => fetchData(meta.current_page + 1)}
                disabled={meta.current_page >= meta.last_page || loading}
                className="rounded border border-slate-300 px-2.5 py-1 font-medium hover:bg-slate-50 disabled:opacity-40 transition"
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
