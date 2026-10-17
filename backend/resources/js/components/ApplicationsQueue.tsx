"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, useEffect, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";
import { nisDate, applicationType } from "@/lib/format";
import type { Application, Paginated } from "@/lib/types";

export interface ApplicationsQueueViewProps {
  pageTitle: string;
  pageSubtitle: string;
  basePath: string; // e.g. "/staff/applications" or "/staff/approvals"
}

const STATUS_FILTERS: { key: string; label: string }[] = [
  { key: "PENDING_APPROVAL", label: "Awaiting Approval" },
  { key: "QUERIED", label: "Queried" },
  { key: "APPROVED_FOR_BIOMETRICS", label: "Biometrics" },
  { key: "BIOMETRICS_CAPTURED", label: "In Production" },
  { key: "READY_FOR_COLLECTION", label: "Ready for Collection" },
  { key: "ISSUED", label: "Collected" },
];

function ApplicationsQueueContent({ pageTitle, pageSubtitle, basePath }: ApplicationsQueueViewProps) {
  const router = useRouter();
  const searchParams = useSearchParams();

  const currentStatus = searchParams.get("status") ?? "";
  const currentSearch = searchParams.get("search") ?? "";
  const currentPage = Math.max(1, parseInt(searchParams.get("page") ?? "1", 10) || 1);
  const assigned = searchParams.get("assigned") ?? "";
  const overdue = searchParams.get("overdue") === "1";

  const [searchInput, setSearchInput] = useState(currentSearch);

  useEffect(() => {
    setSearchInput(currentSearch);
  }, [currentSearch]);

  // Construct API query with 20 items per page
  const apiParams = new URLSearchParams({
    page: String(currentPage),
    per_page: "20",
    ...(currentStatus ? { status: currentStatus } : {}),
    ...(currentSearch ? { search: currentSearch } : {}),
    ...(assigned ? { assigned } : {}),
    ...(overdue ? { overdue: "1" } : {}),
  });

  const { data: result } = useFetch<Paginated<Application>>("staff", `applications?${apiParams}`);

  function buildUrl(overrides: { status?: string; search?: string; page?: number; assigned?: string; overdue?: boolean }) {
    const nextStatus = overrides.status !== undefined ? overrides.status : currentStatus;
    const nextSearch = overrides.search !== undefined ? overrides.search : currentSearch;
    const nextPage = overrides.page !== undefined ? overrides.page : 1;
    const nextAssigned = overrides.assigned !== undefined ? overrides.assigned : assigned;
    const nextOverdue = overrides.overdue !== undefined ? overrides.overdue : overdue;

    const p = new URLSearchParams();
    if (nextPage > 1) p.set("page", String(nextPage));
    if (nextStatus) p.set("status", nextStatus);
    if (nextSearch) p.set("search", nextSearch);
    if (nextAssigned) p.set("assigned", nextAssigned);
    if (nextOverdue) p.set("overdue", "1");

    const qs = p.toString();
    return qs ? `${basePath}?${qs}` : basePath;
  }

  function handleSearchSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const trimmed = searchInput.trim();
    router.push(buildUrl({ search: trimmed, page: 1 }));
  }

  function handleClearSearch() {
    setSearchInput("");
    router.push(buildUrl({ search: "", page: 1 }));
  }

  function handleToggleStatus(filterKey: string) {
    if (currentStatus === filterKey) {
      // Toggle off -> show all
      router.push(buildUrl({ status: "", page: 1 }));
    } else {
      router.push(buildUrl({ status: filterKey, page: 1 }));
    }
  }

  // Pagination calculation
  const total = result?.meta?.total ?? 0;
  const perPage = result?.meta?.per_page ?? 20;
  const lastPage = Math.max(1, result?.meta?.last_page ?? (Math.ceil(total / perPage) || 1));
  const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const to = Math.min(total, currentPage * perPage);

  // Generate page numbers for pagination bar
  const getPageNumbers = () => {
    const pages: (number | "...")[] = [];
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i);
    } else {
      pages.push(1);
      if (currentPage > 3) pages.push("...");
      const start = Math.max(2, currentPage - 1);
      const end = Math.min(lastPage - 1, currentPage + 1);
      for (let i = start; i <= end; i++) pages.push(i);
      if (currentPage < lastPage - 2) pages.push("...");
      pages.push(lastPage);
    }
    return pages;
  };

  return (
    <div className="space-y-5">
      <PageTitle title={pageTitle} subtitle={pageSubtitle} />

      {/* Search Bar */}
      <form onSubmit={handleSearchSubmit} className="flex gap-2">
        <div className="relative flex-1">
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by application number, passport number or applicant name"
            className="w-full rounded-md border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-nis-primary/20"
          />
          {searchInput && (
            <button
              type="button"
              onClick={handleClearSearch}
              className="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400 hover:text-slate-600 focus:outline-none"
              title="Clear search"
            >
              ✕
            </button>
          )}
        </div>
        <Button type="submit">Search</Button>
      </form>

      {/* Secondary quick filters */}
      <div className="flex flex-wrap items-center gap-2 text-sm" role="group" aria-label="Quick filters">
        {([["me", "Assigned to me"], ["unassigned", "Unassigned"]] as const).map(([v, l]) => (
          <Link
            key={v}
            href={buildUrl({ assigned: assigned === v ? "" : v, page: 1 })}
            aria-pressed={assigned === v}
            className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
              assigned === v
                ? "border-nis-primary bg-nis-mint text-nis-primary-dark"
                : "border-slate-300 bg-white text-slate-700 hover:border-slate-400"
            }`}
          >
            {l}
          </Link>
        ))}
        <Link
          href={buildUrl({ overdue: !overdue, page: 1 })}
          aria-pressed={overdue}
          className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
            overdue
              ? "border-red-400 bg-red-50 text-red-800"
              : "border-slate-300 bg-white text-slate-700 hover:border-slate-400"
          }`}
        >
          Past the service-level target
        </Link>
      </div>

      {/* Application Status Filters */}
      <div className="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2.5 pt-1">
        {STATUS_FILTERS.map(({ key, label }) => {
          const isActive = currentStatus === key;
          return (
            <button
              key={key}
              type="button"
              onClick={() => handleToggleStatus(key)}
              className={`whitespace-nowrap rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                isActive
                  ? "bg-nis-primary text-white shadow-xs"
                  : "bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900"
              }`}
            >
              {label}
            </button>
          );
        })}
      </div>

      {/* Applications Table / Results */}
      {!result ? (
        <Spinner />
      ) : result.data.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-200 bg-white p-12 text-center">
          <p className="text-sm font-medium text-slate-600">No applications found.</p>
          {(currentSearch || currentStatus || assigned || overdue) && (
            <div className="mt-3">
              <Link
                href={basePath}
                className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                Clear all filters
              </Link>
            </div>
          )}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="w-12 px-3 py-3 text-center font-semibold">S/N</th>
                <th className="px-4 py-3 font-semibold">Application</th>
                <th className="px-4 py-3 font-semibold">Applicant</th>
                <th className="px-4 py-3 font-semibold">Command / Location</th>
                <th className="px-4 py-3 font-semibold">Nationality</th>
                <th className="px-4 py-3 font-semibold">Appointment</th>
                <th className="px-4 py-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {result.data.map((a, index) => {
                const serialNumber = (currentPage - 1) * perPage + index + 1;
                return (
                  <tr key={a.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="w-12 px-3 py-3 text-center text-xs font-medium text-slate-500">
                      {serialNumber}
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        href={`/staff/applications/${a.id}`}
                        className="font-semibold text-nis-green hover:underline"
                      >
                        {a.application_number}
                      </Link>
                      {(a.risk_flags?.length ?? 0) > 0 && (
                        <span
                          className="ml-2 rounded bg-nis-red px-1.5 text-[11px] font-semibold text-white"
                          title={a.risk_flags!.map((f) => f.message).join("\n")}
                        >
                          ⚠ {a.risk_flags!.length}
                        </span>
                      )}
                      <div className="text-xs text-slate-500">
                        {a.channel === "ASSISTED" ? "Assisted" : "Online"} · {applicationType(a)}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900">{a.surname}, {a.forenames}</div>
                      <div className="text-xs text-slate-500">{a.passport_number}</div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-800">
                        {a.domicile_state ? `${a.domicile_state} State` : "—"}
                      </div>
                      <div className="text-xs text-slate-500">
                        {a.domicile_lga ? `${a.domicile_lga} LGA` : a.domicile || ""}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-slate-700">{a.nationality}</td>
                    <td className="px-4 py-3">
                      <div className="text-slate-800">{nisDate(a.appointment_date)}</div>
                      <div className="text-xs text-slate-500">
                        {a.appointment_time} · {a.enrollment_center?.code}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={a.status} />
                      {a.sla && (
                        <div
                          className={`mt-1 text-xs ${a.sla.overdue ? "font-semibold text-red-700" : "text-slate-500"}`}
                          title={`Target: decision within ${a.sla.target} working days`}
                        >
                          {a.sla.working_days} working day{a.sla.working_days === 1 ? "" : "s"}
                          {a.sla.overdue ? " · overdue" : ""}
                        </div>
                      )}
                      {a.assigned_to && (
                        <div className="text-xs text-slate-500">→ {a.assigned_to.fullname}</div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination Bar */}
      {result && total > 0 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 text-sm border-t border-slate-200">
          <span className="text-slate-600">
            Showing <span className="font-medium text-slate-900">{from}</span>–
            <span className="font-medium text-slate-900">{to}</span> of{" "}
            <span className="font-medium text-slate-900">{total}</span> applications
          </span>

          <div className="flex items-center gap-1">
            {currentPage > 1 ? (
              <Link
                href={buildUrl({ page: currentPage - 1 })}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
              >
                Previous
              </Link>
            ) : (
              <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">
                Previous
              </span>
            )}

            <div className="hidden sm:flex items-center gap-1">
              {getPageNumbers().map((pNum, idx) =>
                pNum === "..." ? (
                  <span key={`dots-${idx}`} className="px-2 py-1 text-xs text-slate-400">
                    …
                  </span>
                ) : (
                  <Link
                    key={`page-${pNum}`}
                    href={buildUrl({ page: pNum })}
                    className={`min-w-[32px] text-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors ${
                      currentPage === pNum
                        ? "bg-nis-primary text-white font-semibold"
                        : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                    }`}
                  >
                    {pNum}
                  </Link>
                )
              )}
            </div>

            {currentPage < lastPage ? (
              <Link
                href={buildUrl({ page: currentPage + 1 })}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
              >
                Next
              </Link>
            ) : (
              <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">
                Next
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export function ApplicationsQueue(props: ApplicationsQueueViewProps) {
  return (
    <Suspense fallback={<Spinner />}>
      <ApplicationsQueueContent {...props} />
    </Suspense>
  );
}
