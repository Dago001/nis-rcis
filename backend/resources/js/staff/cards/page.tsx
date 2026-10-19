"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, useEffect, useRef, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
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
  const { t } = useI18n();

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

  const previousResultRef = useRef<Paginated<Card> | null>(null);
  if (result) {
    previousResultRef.current = result;
  }
  const displayResult = result ?? previousResultRef.current;
  const isRefreshing = !result && Boolean(previousResultRef.current);

  const total = displayResult?.total ?? 0;
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
        title={t("Card Production")}
        subtitle={t("Prepare, inspect and print official ECOWAS Residence Cards for verified applicants.")}
      />

      {/* Unified Search Bar & Dropdown Filter Row */}
      <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
        {/* Search Bar with Live Suggestions */}
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
                  if (searchInput.trim().length >= 1) setShowDropdown(true);
                }}
                onKeyDown={(e) => {
                  if (e.key === "Escape") setShowDropdown(false);
                }}
                placeholder={t("Search by card number, passport number or applicant name")}
                className="w-full rounded-md border border-slate-300 bg-white !pl-10 !pr-10 py-2.5 text-sm text-slate-900 transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
              />
              {isSearching ? (
                <span className="absolute right-3 h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-nis-primary" />
              ) : searchInput ? (
                <button
                  type="button"
                  onClick={handleClearSearch}
                  className="absolute right-3 flex items-center text-xs text-slate-400 hover:text-slate-600 focus:outline-none"
                  title={t("Clear search")}
                >
                  ✕
                </button>
              ) : null}
            </div>
          </form>

          {/* Suggestions Dropdown */}
          {showDropdown && searchInput.trim().length >= 1 && (
            <div className="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
              {isSearching && suggestions.length === 0 ? (
                <div className="flex items-center justify-center gap-2 px-4 py-6 text-sm text-slate-500">
                  <Spinner />
                  <span>{t("Searching cards...")}</span>
                </div>
              ) : suggestions.length === 0 ? (
                <div className="px-4 py-6 text-center text-sm font-medium text-slate-500">
                  {t("No matching card records found")}
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
                            <span>{t("Passport")}: {c.passport_number}</span>
                            <span>|</span>
                            <span>{t(c.nationality)}</span>
                          </div>
                        </div>
                        <div className="shrink-0 text-xs font-medium text-slate-700">
                          {isPrinted ? t("Printed") : t("Awaiting Production")}
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>

        {/* Card Production Dropdown Filter */}
        <div className="w-full md:w-56 shrink-0">
          <select
            value={currentFilter}
            onChange={(e) => handleFilterChange(e.target.value)}
            aria-label={t("Card filter")}
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-xs transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
          >
            <option value="">{t("All Cards")}</option>
            <option value="awaiting_production">{t("Awaiting Production")}</option>
            <option value="new_cards">{t("New Cards")}</option>
            <option value="renewed_cards">{t("Renewed Cards")}</option>
            <option value="damaged_cards">{t("Damaged Cards")}</option>
          </select>
        </div>
      </div>

      {/* Table Results */}
      {!displayResult ? (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
          <div className="flex items-center justify-center gap-2 text-sm text-slate-500">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-nis-primary" />
            <span>{t("Loading card records...")}</span>
          </div>
        </div>
      ) : displayResult.data.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-200 bg-white p-12 text-center">
          {currentFilter === "damaged_cards" ? (
            <div className="space-y-1">
              <p className="text-sm font-medium text-slate-800">
                {t("No damaged card replacement records found.")}
              </p>
              <p className="text-xs text-slate-500">
                {t("Damaged card replacement requests are not currently registered in the database.")}
              </p>
            </div>
          ) : (
            <p className="text-sm font-medium text-slate-600">{t("No card records found.")}</p>
          )}
          {(debouncedSearch || currentFilter) && (
            <div className="mt-3">
              <Link
                href="/staff/cards"
                className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              >
                {t("Clear all filters")}
              </Link>
            </div>
          )}
        </div>
      ) : (
        <div className={`overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs transition-opacity ${isRefreshing ? "opacity-60 pointer-events-none" : ""}`}>
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="w-12 px-3 py-3 text-center font-semibold">S/N</th>
                <th className="px-4 py-3 font-semibold">{t("Card Number")}</th>
                <th className="px-4 py-3 font-semibold">{t("Holder Details")}</th>
                <th className="px-4 py-3 font-semibold">{t("Destination (State / LGA)")}</th>
                <th className="px-4 py-3 font-semibold">{t("Nationality")}</th>
                <th className="px-4 py-3 font-semibold">{t("Category")}</th>
                <th className="px-4 py-3 font-semibold">{t("Production Status")}</th>
                <th className="w-16 px-4 py-3 text-center font-semibold">{t("View")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {displayResult.data.map((c, index) => {
                const serialNumber = (currentPage - 1) * perPage + index + 1;
                const isPrinted = (c.printed_count ?? 0) > 0;
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
                        {t("Booklet")}: {c.booklet_number || "—"}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900">
                        {c.surname}, {c.forenames}
                      </div>
                      <div className="text-xs text-slate-500">
                        {t("Passport")}: {c.passport_number}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-800">
                        {c.domicile_state ? `${c.domicile_state} ${t("State")}` : "—"}
                      </div>
                      <div className="text-xs text-slate-500">
                        {c.domicile_lga ? `${c.domicile_lga} ${t("LGA")}` : c.domicile || ""}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-slate-700">{t(c.nationality)}</td>
                    <td className="px-4 py-3 text-slate-700 font-medium">
                      {t(category)}
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-800">
                        {isPrinted ? t("Printed") : t("Awaiting Production")}
                      </div>
                      {c.printed_count ? (
                        <div className="text-xs text-slate-500">
                          {t("Printed {count}×", { count: c.printed_count })}
                        </div>
                      ) : null}
                    </td>
                    <td className="w-16 px-4 py-3 text-center">
                      <Link
                        href={`/staff/cards/${c.id}`}
                        title={t("View")}
                        aria-label={t("View card details for {number}", { number: c.card_number })}
                        className="inline-flex items-center justify-center p-1.5 text-slate-500 hover:text-nis-primary transition-colors rounded-md hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-nis-primary/30"
                      >
                        <svg
                          className="h-4 w-4"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="2"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          aria-hidden="true"
                        >
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                          <circle cx="12" cy="12" r="3" />
                        </svg>
                        <span className="sr-only">{t("View")}</span>
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
      {displayResult && total > 0 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 text-sm border-t border-slate-200">
          <span className="text-slate-600">
            {t("Showing")} <span className="font-medium text-slate-900">{from}</span>–
            <span className="font-medium text-slate-900">{to}</span> {t("of")}{" "}
            <span className="font-medium text-slate-900">{total}</span> {t("cards")}
          </span>

          <div className="flex items-center gap-1">
            {currentPage > 1 ? (
              <Link
                href={buildUrl({ page: currentPage - 1 })}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
              >
                {t("Previous")}
              </Link>
            ) : (
              <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">
                {t("Previous")}
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
                {t("Next")}
              </Link>
            ) : (
              <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">
                {t("Next")}
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
