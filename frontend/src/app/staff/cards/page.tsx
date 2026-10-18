"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, useEffect, useRef, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";
import { nisDate } from "@/lib/format";
import type { Card, Paginated } from "@/lib/types";

const FILTER_OPTIONS = [
  { key: "", label: "All Cards" },
  { key: "awaiting_production", label: "Awaiting Production" },
  { key: "new_cards", label: "New Cards" },
  { key: "renewed_cards", label: "Renewed Cards" },
  { key: "damaged_cards", label: "Damaged Cards" },
];

function CardProductionContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const currentFilter = searchParams.get("filter") ?? "";
  const currentSearch = searchParams.get("search") ?? "";
  const currentPage = Math.max(1, parseInt(searchParams.get("page") ?? "1", 10) || 1);
  const perPage = 10;

  const [searchInput, setSearchInput] = useState(currentSearch);
  const [debouncedSearch, setDebouncedSearch] = useState(currentSearch);
  const [suggestions, setSuggestions] = useState<Card[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);

  const searchContainerRef = useRef<HTMLDivElement>(null);
  const latestRequestRef = useRef(0);

  // Sync external URL search parameter changes
  useEffect(() => {
    setSearchInput(currentSearch);
    setDebouncedSearch(currentSearch);
  }, [currentSearch]);

  // Click outside to close suggestion dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (searchContainerRef.current && !searchContainerRef.current.contains(event.target as Node)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // Debounced live search suggestions
  useEffect(() => {
    const query = searchInput.trim();
    const timer = setTimeout(() => {
      setDebouncedSearch(query);

      if (query.length >= 1) {
        setIsSearching(true);
        setShowDropdown(true);
        const reqId = ++latestRequestRef.current;
        const endpoint = `cards?search=${encodeURIComponent(query)}&per_page=6`;
        api<Paginated<Card>>("staff", endpoint)
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
        setSuggestions([]);
        setShowDropdown(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchInput]);

  // Build URL helper
  const buildUrl = (overrides: Record<string, string | number | boolean | null>) => {
    const p = new URLSearchParams();
    const filter = overrides.filter !== undefined ? overrides.filter : currentFilter;
    const search = overrides.search !== undefined ? overrides.search : debouncedSearch;
    const page = overrides.page !== undefined ? overrides.page : currentPage;

    if (filter) p.set("filter", String(filter));
    if (search) p.set("search", String(search));
    p.set("page", String(page));
    p.set("per_page", String(perPage));

    return `/staff/cards?${p.toString()}`;
  };

  const handleSearchSubmit = (e: FormEvent) => {
    e.preventDefault();
    setShowDropdown(false);
    router.push(buildUrl({ search: searchInput.trim(), page: 1 }));
  };

  const handleClearSearch = () => {
    setSearchInput("");
    setDebouncedSearch("");
    setShowDropdown(false);
    setSuggestions([]);
    router.push(buildUrl({ search: "", page: 1 }));
  };

  const handleFilterChange = (filterKey: string) => {
    router.push(buildUrl({ filter: filterKey, page: 1 }));
  };

  const handleSelectSuggestion = (c: Card) => {
    setShowDropdown(false);
    router.push(`/staff/cards/${c.id}`);
  };

  // Fetch paginated cards
  const queryUrl = `cards?filter=${encodeURIComponent(currentFilter)}&search=${encodeURIComponent(debouncedSearch)}&page=${currentPage}&per_page=${perPage}`;
  const { data: result } = useFetch<Paginated<Card>>("staff", queryUrl);

  const total = result?.total ?? 0;
  const from = total > 0 ? (currentPage - 1) * perPage + 1 : 0;
  const to = Math.min(currentPage * perPage, total);
  const totalPages = Math.max(1, Math.ceil(total / perPage));

  const getPageNumbers = () => {
    const pages: (number | "...")[] = [];
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (currentPage > 3) pages.push("...");
      const start = Math.max(2, currentPage - 1);
      const end = Math.min(totalPages - 1, currentPage + 1);
      for (let i = start; i <= end; i++) pages.push(i);
      if (currentPage < totalPages - 2) pages.push("...");
      pages.push(totalPages);
    }
    return pages;
  };

  return (
    <div className="space-y-5">
      <PageTitle
        title="Card Production"
        subtitle="Prepare, inspect and print official ECOWAS Residence Cards for verified applicants."
      />

      {/* Live Search Bar */}
      <form onSubmit={handleSearchSubmit} className="flex gap-2">
        <div ref={searchContainerRef} className="relative flex-1">
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            onFocus={() => {
              if (searchInput.trim().length >= 1) setShowDropdown(true);
            }}
            placeholder="Search by card number, passport number or applicant name"
            className="w-full rounded-md border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
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

          {/* Suggestions Dropdown */}
          {showDropdown && searchInput.trim().length >= 1 && (
            <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
              {isSearching && suggestions.length === 0 ? (
                <div className="flex items-center justify-center gap-2 px-4 py-6 text-sm text-slate-500">
                  <Spinner />
                  <span>Searching cards...</span>
                </div>
              ) : suggestions.length === 0 ? (
                <div className="px-4 py-6 text-center text-sm font-medium text-slate-500">
                  No matching card records found
                </div>
              ) : (
                <div className="divide-y divide-slate-100">
                  {suggestions.map((c) => {
                    const isPrinted = (c.printed_count ?? 0) > 0;
                    return (
                      <button
                        key={c.id}
                        type="button"
                        onClick={() => handleSelectSuggestion(c)}
                        className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                      >
                        <div className="min-w-0 flex-1">
                          <div className="text-sm font-semibold text-slate-900 truncate">
                            {c.surname}, {c.forenames}
                          </div>
                          <div className="mt-0.5 text-xs text-slate-500 flex flex-wrap items-center gap-1.5">
                            <span className="font-medium text-nis-primary">{c.card_number}</span>
                            <span>|</span>
                            <span>Passport: {c.passport_number}</span>
                            <span>|</span>
                            <span>{c.nationality}</span>
                          </div>
                        </div>
                        <div className="shrink-0">
                          <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                              isPrinted
                                ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
                                : "bg-amber-50 text-amber-800 border border-amber-200"
                            }`}
                          >
                            {isPrinted ? "Printed" : "Awaiting Production"}
                          </span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>
        <Button type="submit">Search</Button>
      </form>

      {/* Production & Category Filter Buttons */}
      <div className="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2.5 pt-1">
        {FILTER_OPTIONS.map(({ key, label }) => {
          const isActive = currentFilter === key;
          return (
            <button
              key={key || "all"}
              type="button"
              onClick={() => handleFilterChange(key)}
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

      {/* Table Results */}
      {!result ? (
        <Spinner />
      ) : result.data.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-200 bg-white p-12 text-center">
          {currentFilter === "damaged_cards" ? (
            <div className="space-y-1">
              <p className="text-sm font-medium text-slate-800">
                No damaged card replacement records found.
              </p>
              <p className="text-xs text-slate-500">
                Damaged card replacement requests are not currently registered in the database.
              </p>
            </div>
          ) : (
            <p className="text-sm font-medium text-slate-600">No card records found.</p>
          )}
          {(debouncedSearch || currentFilter) && (
            <div className="mt-3">
              <Link
                href="/staff/cards"
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
                <th className="px-4 py-3 font-semibold">Card Number</th>
                <th className="px-4 py-3 font-semibold">Holder Details</th>
                <th className="px-4 py-3 font-semibold">Destination (State / LGA)</th>
                <th className="px-4 py-3 font-semibold">Nationality</th>
                <th className="px-4 py-3 font-semibold">Category</th>
                <th className="px-4 py-3 font-semibold">Production Status</th>
                <th className="px-4 py-3 font-semibold">Issuance Status</th>
                <th className="px-4 py-3 text-right font-semibold">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {result.data.map((c, index) => {
                const serialNumber = (currentPage - 1) * perPage + index + 1;
                const isPrinted = (c.printed_count ?? 0) > 0;
                const isIssued = c.status === "ISSUED" || c.issuance_status === "ISSUED";
                const category = c.card_category || c.application?.category || "General";

                return (
                  <tr key={c.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="w-12 px-3 py-3 text-center text-xs font-medium text-slate-500">
                      {serialNumber}
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        href={`/staff/cards/${c.id}`}
                        className="font-semibold text-nis-green hover:underline"
                      >
                        {c.card_number}
                      </Link>
                      <div className="text-xs text-slate-500">
                        Booklet: {c.booklet_number || "—"}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900">
                        {c.surname}, {c.forenames}
                      </div>
                      <div className="text-xs text-slate-500">
                        Passport: {c.passport_number}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-800">
                        {c.domicile_state ? `${c.domicile_state} State` : "—"}
                      </div>
                      <div className="text-xs text-slate-500">
                        {c.domicile_lga ? `${c.domicile_lga} LGA` : c.domicile || ""}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-slate-700">{c.nationality}</td>
                    <td className="px-4 py-3">
                      <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
                        {category}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                          isPrinted
                            ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
                            : "bg-amber-50 text-amber-800 border border-amber-200"
                        }`}
                      >
                        {isPrinted ? "Printed" : "Awaiting Production"}
                      </span>
                      {c.printed_count ? (
                        <div className="text-[11px] text-slate-500 mt-0.5">
                          Printed {c.printed_count}×
                        </div>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                          isIssued
                            ? "bg-blue-50 text-blue-800 border border-blue-200"
                            : "bg-slate-100 text-slate-700 border border-slate-200"
                        }`}
                      >
                        {isIssued ? "Issued" : "Awaiting Issuance"}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link
                        href={`/staff/cards/${c.id}`}
                        className="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50"
                      >
                        Open Details →
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination Controls */}
      {result && total > 0 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 text-sm border-t border-slate-200">
          <span className="text-slate-600">
            Showing <span className="font-medium text-slate-900">{from}</span>–
            <span className="font-medium text-slate-900">{to}</span> of{" "}
            <span className="font-medium text-slate-900">{total}</span> cards
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
                        ? "bg-nis-primary text-white font-semibold shadow-xs"
                        : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                    }`}
                  >
                    {pNum}
                  </Link>
                )
              )}
            </div>

            {currentPage < totalPages ? (
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

export default function CardsPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <CardProductionContent />
    </Suspense>
  );
}
