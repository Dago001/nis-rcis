"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, useEffect, useRef, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";
import { nisDate, applicationType } from "@/lib/format";
import type { Application, Paginated } from "@/lib/types";

export interface ApplicationsQueueViewProps {
  pageTitle: string;
  pageSubtitle: string;
  basePath: string; // e.g. "/staff/applications" or "/staff/approvals"
  defaultPerPage?: number;
}

const VETTING_STATUS_FILTERS: { key: string; label: string }[] = [
  { key: "", label: "All" },
  { key: "PENDING_APPROVAL", label: "Awaiting Vetting" },
  { key: "QUERIED", label: "Queried" },
  { key: "VETTED", label: "Vetted" },
  { key: "REJECTED", label: "Rejected" },
];

const APPROVERS_STATUS_FILTERS: { key: string; label: string }[] = [
  { key: "", label: "All" },
  { key: "VETTED", label: "Awaiting Approval" },
  { key: "APPROVED_FOR_BIOMETRICS", label: "Approved" },
  { key: "QUERIED", label: "Queried" },
  { key: "REJECTED", label: "Rejected" },
];

function ApplicationsQueueContent({ pageTitle, pageSubtitle, basePath, defaultPerPage = 10 }: ApplicationsQueueViewProps) {
  const router = useRouter();
  const searchParams = useSearchParams();

  const isApprovers = basePath === "/staff/approvals";
  const queueType = isApprovers ? "approvals" : "vetting";
  const statusFilters = isApprovers ? APPROVERS_STATUS_FILTERS : VETTING_STATUS_FILTERS;

  const currentStatus = searchParams.get("status") ?? "";
  const currentSearch = searchParams.get("search") ?? "";
  const currentPage = Math.max(1, parseInt(searchParams.get("page") ?? "1", 10) || 1);
  const assigned = searchParams.get("assigned") ?? "";
  const overdue = searchParams.get("overdue") === "1";

  const [searchInput, setSearchInput] = useState(currentSearch);
  const [debouncedSearch, setDebouncedSearch] = useState(currentSearch);
  const [suggestions, setSuggestions] = useState<Application[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);

  const searchContainerRef = useRef<HTMLDivElement>(null);
  const latestRequestRef = useRef(0);

  // Sync external search param changes
  useEffect(() => {
    setSearchInput(currentSearch);
    setDebouncedSearch(currentSearch);
  }, [currentSearch]);

  // Click outside to close dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (searchContainerRef.current && !searchContainerRef.current.contains(event.target as Node)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // Live search debouncing (~250ms) and suggestions fetching
  useEffect(() => {
    const query = searchInput.trim();
    const timer = setTimeout(() => {
      setDebouncedSearch(query);

      if (query.length >= 1) {
        setIsSearching(true);
        setShowDropdown(true);
        const reqId = ++latestRequestRef.current;
        const endpoint = `applications?search=${encodeURIComponent(query)}&queue=${queueType}&per_page=6`;
        api<Paginated<Application>>("staff", endpoint)
          .then((res) => {
            if (reqId === latestRequestRef.current) {
              setSuggestions(res.data ?? []);
            }
          })
          .catch(() => {
            if (reqId === latestRequestRef.current) {
              setSuggestions([]);
            }
          })
          .finally(() => {
            if (reqId === latestRequestRef.current) {
              setIsSearching(false);
            }
          });
      } else {
        latestRequestRef.current++;
        setSuggestions([]);
        setShowDropdown(false);
        setIsSearching(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchInput, queueType]);

  // Construct API query with specified items per page (default 10 on Vetting)
  const apiParams = new URLSearchParams({
    page: String(currentPage),
    per_page: String(defaultPerPage),
    queue: queueType,
    ...(currentStatus ? { status: currentStatus } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
    ...(assigned ? { assigned } : {}),
    ...(overdue ? { overdue: "1" } : {}),
  });

  const { data: result } = useFetch<Paginated<Application>>("staff", `applications?${apiParams}`);

  function buildUrl(overrides: { status?: string; search?: string; page?: number; assigned?: string; overdue?: boolean }) {
    const nextStatus = overrides.status !== undefined ? overrides.status : currentStatus;
    const nextSearch = overrides.search !== undefined ? overrides.search : debouncedSearch;
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
    latestRequestRef.current++;
    setDebouncedSearch(trimmed);
    setShowDropdown(false);
    router.push(buildUrl({ search: trimmed, page: 1 }));
  }

  function handleClearSearch() {
    latestRequestRef.current++;
    setSearchInput("");
    setDebouncedSearch("");
    setSuggestions([]);
    setShowDropdown(false);
    setIsSearching(false);
    router.push(buildUrl({ search: "", page: 1 }));
  }

  function handleSelectSuggestion(app: Application) {
    setShowDropdown(false);
    router.push(`${basePath}/${app.id}`);
  }

  // Pagination calculation
  const total = result?.meta?.total ?? 0;
  const perPage = result?.meta?.per_page ?? defaultPerPage;
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

      {/* Unified Search Bar & Dropdown Filters Row */}
      <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
        {/* Search Bar with Live Suggestions (reduced width, flexible) */}
        <div ref={searchContainerRef} className="relative flex-1 min-w-[260px]">
          <form onSubmit={handleSearchSubmit} className="relative">
            <div className="relative flex items-center">
              <span className="pointer-events-none absolute left-3.5 text-slate-400">
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </span>
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onFocus={() => {
                  if (searchInput.trim().length >= 1) {
                    setShowDropdown(true);
                  }
                }}
                onKeyDown={(e) => {
                  if (e.key === "Escape") {
                    setShowDropdown(false);
                  }
                }}
                placeholder="Search by application number, passport number or applicant name"
                className="w-full rounded-md border border-slate-300 bg-white !pl-10 !pr-10 py-2.5 text-sm text-slate-900 transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
              />
              {isSearching ? (
                <span className="absolute right-3 h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-nis-primary" />
              ) : searchInput ? (
                <button
                  type="button"
                  onClick={handleClearSearch}
                  className="absolute right-3 flex items-center text-xs text-slate-400 hover:text-slate-600 focus:outline-none"
                  title="Clear search"
                >
                  ✕
                </button>
              ) : null}
            </div>
          </form>

          {/* Live Suggestions Dropdown */}
          {showDropdown && searchInput.trim().length >= 1 && (
            <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
              {isSearching && suggestions.length === 0 ? (
                <div className="flex items-center justify-center gap-2 px-4 py-6 text-sm text-slate-500">
                  <Spinner />
                  <span>Searching applications...</span>
                </div>
              ) : suggestions.length === 0 ? (
                <div className="px-4 py-6 text-center text-sm font-medium text-slate-500">
                  No matching applications found
                </div>
              ) : (
                <div className="divide-y divide-slate-100">
                  {suggestions.map((s) => (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() => handleSelectSuggestion(s)}
                      className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                    >
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-semibold text-slate-900 truncate">
                          {s.surname}, {s.forenames}
                        </div>
                        <div className="mt-0.5 text-xs text-slate-500 flex flex-wrap items-center gap-1.5">
                          <span className="font-medium text-nis-primary">{s.application_number}</span>
                          <span>|</span>
                          <span>{s.passport_number}</span>
                          <span>|</span>
                          <span>{s.status_label || s.status.replaceAll("_", " ")}</span>
                        </div>
                      </div>
                      <div className="shrink-0">
                        <StatusBadge status={s.status} label={s.status_label} />
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>

        {/* First Dropdown: Status Filter */}
        <div className="w-full md:w-56 shrink-0">
          <select
            value={currentStatus}
            onChange={(e) => router.push(buildUrl({ status: e.target.value, page: 1 }))}
            aria-label="Status filter"
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-xs transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
          >
            <option value="">Status: All</option>
            {isApprovers ? (
              <>
                <option value="VETTED">Awaiting Approval</option>
                <option value="APPROVED_FOR_BIOMETRICS">Approved</option>
                <option value="QUERIED">Queried</option>
                <option value="REJECTED">Rejected</option>
              </>
            ) : (
              <>
                <option value="PENDING_APPROVAL">Awaiting Vetting</option>
                <option value="QUERIED">Queried</option>
                <option value="VETTED">Vetted</option>
                <option value="REJECTED">Rejected</option>
              </>
            )}
          </select>
        </div>

        {/* Second Dropdown: Assignment / Workload Filter */}
        <div className="w-full md:w-64 shrink-0">
          <select
            value={overdue ? "overdue" : assigned || ""}
            onChange={(e) => {
              const val = e.target.value;
              if (val === "overdue") {
                router.push(buildUrl({ assigned: "", overdue: true, page: 1 }));
              } else if (val === "me" || val === "unassigned") {
                router.push(buildUrl({ assigned: val, overdue: false, page: 1 }));
              } else {
                router.push(buildUrl({ assigned: "", overdue: false, page: 1 }));
              }
            }}
            aria-label="Assignment / Workload filter"
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-xs transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
          >
            <option value="">Assignment / Workload: All</option>
            <option value="me">Assigned to Me</option>
            <option value="unassigned">Unassigned</option>
            <option value="overdue">Past Service Level Target</option>
          </select>
        </div>
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
                        href={`${basePath}/${a.id}`}
                        className="font-semibold text-nis-green hover:underline"
                      >
                        {a.application_number}
                      </Link>
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
