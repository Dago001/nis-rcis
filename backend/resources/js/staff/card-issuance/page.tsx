"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, Suspense, useCallback, useEffect, useRef, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { Alert, Button, Input, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type CardIssuanceItem = {
  id: number;
  card_number: string;
  booklet_number: string;
  status: string;
  issuance_status: "ISSUED" | "AWAITING_ISSUANCE";
  issuance_status_label: string;
  verification_status: string;
  card_category: string;
  surname: string;
  forenames: string;
  holder: string;
  passport_number: string;
  nationality: string;
  issued_on: string | null;
  expires_on: string | null;
  printed_count: number;
  date_printed: string | null;
  printed_by: string | null;
  photo_url: string | null;
  application: {
    id: number;
    application_number: string;
    reference_number: string;
    status: string;
    status_label: string;
    category: string;
    ready_at: string | null;
    collected_at: string | null;
    enrollment_center?: {
      id: number;
      name: string;
      code: string;
      state: string;
    } | null;
  } | null;
};

type CardIssuanceResponse = {
  data: CardIssuanceItem[];
  stats: {
    awaiting_issuance: number;
    issued_total: number;
    total_printed: number;
    awaiting_collection?: number;
    ready_to_notify?: number;
    collected_total?: number;
    total_produced?: number;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

function CardIssuanceContent() {
  const router = useRouter();
  const params = useSearchParams();
  const { t } = useI18n();

  const currentStage = params.get("stage") || "all";
  const initialSearch = params.get("search") || "";
  const pageParam = parseInt(params.get("page") || "1", 10);
  const currentPage = Number.isInteger(pageParam) && pageParam > 0 ? pageParam : 1;
  const perPage = 10;

  // Search state with debounce and suggestions
  const [searchInput, setSearchInput] = useState(initialSearch);
  const [debouncedSearch, setDebouncedSearch] = useState(initialSearch);
  const [suggestions, setSuggestions] = useState<CardIssuanceItem[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const latestRequestRef = useRef(0);
  const searchContainerRef = useRef<HTMLDivElement>(null);

  // Status & modal states
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Modal for issuing card
  const [issuingCard, setIssuingCard] = useState<CardIssuanceItem | null>(null);
  const [confirmedIdentity, setConfirmedIdentity] = useState(false);
  const [issueNotes, setIssueNotes] = useState("");

  // Sync search input if URL changes
  useEffect(() => {
    setSearchInput(initialSearch);
    setDebouncedSearch(initialSearch);
  }, [initialSearch]);

  // Click outside to dismiss suggestions dropdown
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (searchContainerRef.current && !searchContainerRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // Debounced search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(searchInput.trim());
      const query = searchInput.trim();

      if (query.length >= 2) {
        setIsSearching(true);
        setShowDropdown(true);
        const reqId = ++latestRequestRef.current;
        const endpoint = `card-issuance?stage=all&search=${encodeURIComponent(query)}&per_page=6`;
        api<CardIssuanceResponse>("staff", endpoint)
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

  // URL builder helper
  const buildUrl = (overrides: Record<string, string | number | boolean | null>) => {
    const p = new URLSearchParams();
    const stage = overrides.stage !== undefined ? overrides.stage : currentStage;
    const search = overrides.search !== undefined ? overrides.search : debouncedSearch;
    const page = overrides.page !== undefined ? overrides.page : currentPage;

    if (stage) p.set("stage", String(stage));
    if (search) p.set("search", String(search));
    p.set("page", String(page));
    p.set("per_page", String(perPage));

    return `/staff/card-issuance?${p.toString()}`;
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

  const handleStageChange = (newStage: string) => {
    setShowDropdown(false);
    router.push(buildUrl({ stage: newStage, page: 1 }));
  };

  const handleSelectSuggestion = (card: CardIssuanceItem) => {
    setShowDropdown(false);
    router.push(`/staff/cards/${card.id}`);
  };

  // Main data fetch
  const queryUrl = `card-issuance?stage=${encodeURIComponent(currentStage)}&search=${encodeURIComponent(debouncedSearch)}&page=${currentPage}&per_page=${perPage}`;
  const { data: response, reload, loading } = useFetch<CardIssuanceResponse>("staff", queryUrl);

  const previousResponseRef = useRef<CardIssuanceResponse | null>(null);
  if (response) {
    previousResponseRef.current = response;
  }
  const displayResponse = response ?? previousResponseRef.current;
  const isRefreshing = loading && Boolean(previousResponseRef.current);

  const cards = displayResponse?.data ?? [];
  const total = displayResponse?.meta?.total ?? 0;
  const from = total > 0 ? (currentPage - 1) * perPage + 1 : 0;
  const to = Math.min(currentPage * perPage, total);
  const totalPages = Math.max(1, displayResponse?.meta?.last_page ?? Math.ceil(total / perPage));

  const stats = displayResponse?.stats ?? {
    awaiting_issuance: 0,
    issued_total: 0,
    total_printed: 0,
  };

  // Pagination helper
  const getPageNumbers = () => {
    const pages: (number | string)[] = [];
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

  // Issue card action handler
  const handleIssueOutSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!issuingCard || !confirmedIdentity) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const res = await api<{ message: string }>("staff", `card-issuance/${issuingCard.id}/issue-out`, {
        method: "POST",
        json: { notes: issueNotes.trim() },
      });
      setNotice(res.message);
      setIssuingCard(null);
      setConfirmedIdentity(false);
      setIssueNotes("");
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Card Issuance")}
        subtitle={t("Manage printed residence cards and record authorized issuance to verified applicants.")}
      />

      {/* KPI / Summary Tiles */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 text-left shadow-xs">
          <span className="text-xs font-medium uppercase tracking-wider text-slate-600">
            {t("Awaiting Issuance")}
          </span>
          <span className="mt-1 text-2xl font-medium text-slate-900">
            {stats.awaiting_issuance ?? stats.awaiting_collection ?? 0}
          </span>
          <span className="mt-0.5 text-xs text-slate-500">{t("Printed cards ready for collection")}</span>
        </div>

        <div className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 text-left shadow-xs">
          <span className="text-xs font-medium uppercase tracking-wider text-slate-600">
            {t("Issued Cards")}
          </span>
          <span className="mt-1 text-2xl font-medium text-slate-900">
            {stats.issued_total ?? stats.collected_total ?? 0}
          </span>
          <span className="mt-0.5 text-xs text-slate-500">{t("Successfully handed over to applicants")}</span>
        </div>

        <div className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 text-left shadow-xs">
          <span className="text-xs font-medium uppercase tracking-wider text-slate-600">
            {t("All Printed Cards")}
          </span>
          <span className="mt-1 text-2xl font-medium text-slate-900">
            {stats.total_printed ?? stats.total_produced ?? 0}
          </span>
          <span className="mt-0.5 text-xs text-slate-500">{t("Total verified printed cards")}</span>
        </div>
      </div>

      {notice && <Alert tone="success">{t(notice)}</Alert>}
      {error && <Alert tone="danger">{t(error)}</Alert>}

      {/* Live Search & Filter Bar */}
      <div className="flex flex-col md:flex-row items-stretch md:items-center gap-3">
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
                  if (suggestions.length > 0) setShowDropdown(true);
                }}
                onKeyDown={(e) => {
                  if (e.key === "Escape") setShowDropdown(false);
                }}
                placeholder={t("Search by card number, passport number or applicant name")}
                className="w-full rounded-md border border-slate-300 bg-white !pl-10 !pr-10 py-2.5 text-sm text-slate-900 transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
              />
              <div className="absolute right-2.5 flex items-center gap-1.5">
                {isSearching && (
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-nis-primary" />
                )}
                {searchInput && (
                  <button
                    type="button"
                    onClick={handleClearSearch}
                    aria-label={t("Clear search")}
                    className="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none"
                  >
                    <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                  </button>
                )}
              </div>
            </div>
          </form>

          {/* Suggestions Dropdown */}
          {showDropdown && suggestions.length > 0 && (
            <div className="absolute left-0 right-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
              <div className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                {t("Matching Printed Cards")} ({suggestions.length})
              </div>
              <ul className="divide-y divide-slate-100">
                {suggestions.map((s) => (
                  <li
                    key={s.id}
                    onClick={() => handleSelectSuggestion(s)}
                    className="flex cursor-pointer items-center justify-between px-3 py-2.5 hover:bg-slate-50 transition-colors"
                  >
                    <div className="flex items-center gap-2.5">
                      <div className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                        {s.surname?.[0] || "?"}
                      </div>
                      <div>
                        <div className="text-sm font-semibold text-slate-900">
                          {s.surname}, {s.forenames}
                        </div>
                        <div className="text-xs text-slate-500">
                          {t("Card")}: <span className="font-mono font-medium text-slate-700">{s.card_number}</span> · {t("Passport")}: <span className="font-mono text-slate-700">{s.passport_number}</span>
                        </div>
                      </div>
                    </div>
                    <span
                      className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                        s.issuance_status === "ISSUED"
                          ? "bg-emerald-100 text-emerald-800"
                          : "bg-amber-100 text-amber-800"
                      }`}
                    >
                      {s.issuance_status === "ISSUED" ? t("Issued") : t("Awaiting Issuance")}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {/* Card Issuance Dropdown Filter */}
        <div className="w-full md:w-56 shrink-0">
          <select
            value={currentStage}
            onChange={(e) => handleStageChange(e.target.value)}
            aria-label={t("Issuance filter")}
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-xs transition-colors focus:border-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/20"
          >
            <option value="all">{t("All Cards")}</option>
            <option value="awaiting_issuance">{t("Awaiting Issuance")}</option>
            <option value="issued">{t("Issued Cards")}</option>
          </select>
        </div>
      </div>

      {/* Main Table */}
      {!displayResponse ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xs">
          <div className="flex items-center justify-center gap-2 text-sm text-slate-500">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-nis-primary" />
            <span>{t("Loading printed cards...")}</span>
          </div>
        </div>
      ) : cards.length === 0 ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-xs">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM7 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 9h6M12 13h6M5 16h6" />
            </svg>
          </div>
          <p className="mt-3 text-base font-semibold text-slate-900">{t("No printed cards found")}</p>
          <p className="mt-1 text-sm text-slate-500 max-w-md mx-auto">
            {debouncedSearch
              ? t("No records matching \"{search}\". Try checking your search term or clearing filters.", { search: debouncedSearch })
              : currentStage === "awaiting_issuance"
              ? t("There are currently no printed residence cards awaiting issuance.")
              : currentStage === "issued"
              ? t("No issued cards recorded yet.")
              : t("Cards will appear here once confirmed as printed in the Card Production module.")}
          </p>
          {debouncedSearch && (
            <div className="mt-4">
              <Button variant="secondary" onClick={handleClearSearch}>
                {t("Clear Search")}
              </Button>
            </div>
          )}
        </div>
      ) : (
        <div className={`overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition-opacity ${isRefreshing ? "opacity-60 pointer-events-none" : ""}`}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="w-16 px-4 py-3.5 text-center">S/N</th>
                  <th className="px-4 py-3.5">{t("Card Number")}</th>
                  <th className="px-4 py-3.5">{t("Applicant Name")}</th>
                  <th className="px-4 py-3.5">{t("Passport Number")}</th>
                  <th className="px-4 py-3.5">{t("Card Category")}</th>
                  <th className="px-4 py-3.5">{t("Date Printed")}</th>
                  <th className="px-4 py-3.5">{t("Issuance Status")}</th>
                  <th className="px-4 py-3.5 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {cards.map((card, idx) => {
                  const serialNumber = (currentPage - 1) * perPage + idx + 1;
                  const isIssued = card.issuance_status === "ISSUED" || card.status === "ISSUED";

                  return (
                    <tr key={card.id} className="hover:bg-slate-50/70 transition-colors">
                      {/* S/N */}
                      <td className="w-16 px-4 py-3.5 text-center text-xs font-mono font-medium text-slate-500">
                        {serialNumber}
                      </td>

                      {/* Card Number */}
                      <td className="px-4 py-3.5">
                        <Link
                          href={`/staff/cards/${card.id}`}
                          className="font-bold text-nis-primary hover:underline hover:text-nis-primary-dark"
                        >
                          {card.card_number}
                        </Link>
                        {card.booklet_number && (
                          <div className="text-[11px] font-mono text-slate-400">
                            {t("Booklet")}: {card.booklet_number}
                          </div>
                        )}
                      </td>

                      {/* Applicant Name */}
                      <td className="px-4 py-3.5">
                        <div className="flex items-center gap-3">
                          {card.photo_url ? (
                            <img
                              src={card.photo_url}
                              alt=""
                              className="h-8 w-8 rounded-full object-cover border border-slate-200 shrink-0"
                            />
                          ) : (
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                              {card.surname?.[0] || "?"}
                              {card.forenames?.[0] || ""}
                            </div>
                          )}
                          <div>
                            <div className="font-semibold text-slate-900">
                              {card.surname}, {card.forenames}
                            </div>
                            <div className="text-xs text-slate-500">
                              {t(card.nationality)} · App: {card.application?.application_number ?? "—"}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Passport Number */}
                      <td className="px-4 py-3.5 font-mono text-xs text-slate-700">
                        {card.passport_number || "—"}
                      </td>

                      {/* Card Category */}
                      <td className="px-4 py-3.5 text-xs text-slate-700">
                        <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                          {t(card.card_category || "General")}
                        </span>
                      </td>

                      {/* Date Printed */}
                      <td className="px-4 py-3.5 text-xs text-slate-600">
                        {card.date_printed ? (
                          <div>
                            <div className="font-medium text-slate-800">
                              {dateTime(card.date_printed)}
                            </div>
                            {card.printed_by && (
                              <div className="text-[11px] text-slate-400">
                                {t("By")}: {card.printed_by}
                              </div>
                            )}
                          </div>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </td>

                      {/* Issuance Status */}
                      <td className="px-4 py-3.5">
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${
                            isIssued
                              ? "bg-emerald-100 text-emerald-800"
                              : "bg-amber-100 text-amber-800"
                          }`}
                        >
                          <span
                            className={`h-1.5 w-1.5 rounded-full ${
                              isIssued ? "bg-emerald-500" : "bg-amber-500"
                            }`}
                          />
                          {isIssued ? t("Issued") : t("Awaiting Issuance")}
                        </span>
                      </td>

                      {/* Action */}
                      <td className="px-4 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-3">
                          {!isIssued && (
                            <Button
                              variant="primary"
                              onClick={() => {
                                setIssuingCard(card);
                                setConfirmedIdentity(false);
                                setIssueNotes("");
                              }}
                              className="!px-3 !py-1.5 !text-xs bg-emerald-600 hover:bg-emerald-700 font-medium"
                            >
                              {t("Issue Card")}
                            </Button>
                          )}
                          <Link
                            href={`/staff/cards/${card.id}`}
                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-nis-primary hover:text-nis-primary-dark hover:underline transition-colors"
                            title={t("View Card Details")}
                          >
                            <svg className="w-3.5 h-3.5 text-slate-500 hover:text-nis-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>{t("View")}</span>
                          </Link>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination Footer */}
          <div className="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/50 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-600">
            <div>
              {t("Showing")} <span className="font-semibold text-slate-900">{from}</span> {t("to")}{" "}
              <span className="font-semibold text-slate-900">{to}</span> {t("of")}{" "}
              <span className="font-semibold text-slate-900">{total}</span> {t("printed records")}
            </div>

            <div className="flex items-center gap-1.5">
              <Button
                variant="secondary"
                disabled={currentPage <= 1 || loading}
                onClick={() => router.push(buildUrl({ page: currentPage - 1 }))}
                className="!px-2.5 !py-1 !text-xs"
              >
                {t("Previous")}
              </Button>

              <div className="flex items-center gap-1">
                {getPageNumbers().map((pNum, i) =>
                  pNum === "..." ? (
                    <span key={`ellipsis-${i}`} className="px-2 text-slate-400">
                      ...
                    </span>
                  ) : (
                    <button
                      key={`page-${pNum}`}
                      type="button"
                      onClick={() => router.push(buildUrl({ page: pNum }))}
                      className={`h-7 w-7 rounded-md text-xs font-medium transition-colors ${
                        currentPage === pNum
                          ? "bg-nis-primary text-white"
                          : "border border-slate-200 bg-white text-slate-700 hover:bg-slate-100"
                      }`}
                    >
                      {pNum}
                    </button>
                  )
                )}
              </div>

              <Button
                variant="secondary"
                disabled={currentPage >= totalPages || loading}
                onClick={() => router.push(buildUrl({ page: currentPage + 1 }))}
                className="!px-2.5 !py-1 !text-xs"
              >
                {t("Next")}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Issuance Confirmation Modal */}
      {issuingCard && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
          <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
            <div className="flex items-start justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-lg font-bold text-slate-900">{t("Confirm Card Issuance")}</h3>
                <p className="text-xs text-slate-500">
                  {t("Record physical handover of the residence card to the applicant.")}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIssuingCard(null)}
                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleIssueOutSubmit} className="mt-4 space-y-4 text-sm">
              {/* Card & Applicant Summary Card */}
              <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">{t("Residence Card No")}:</span>
                  <span className="font-mono font-bold text-slate-900">{issuingCard.card_number}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">{t("Applicant")}:</span>
                  <span className="font-semibold text-slate-900">
                    {issuingCard.surname}, {issuingCard.forenames}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">{t("Passport Number")}:</span>
                  <span className="font-mono text-slate-800">{issuingCard.passport_number}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">{t("Application Reference")}:</span>
                  <span className="font-mono text-slate-800">
                    {issuingCard.application?.application_number ?? "—"}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">{t("Date Printed")}:</span>
                  <span className="text-xs text-slate-700">
                    {issuingCard.date_printed ? dateTime(issuingCard.date_printed) : t("Confirmed Printed")}
                  </span>
                </div>
              </div>

              {/* Handover Identity Verification Checkbox */}
              <div className="rounded-lg border border-amber-200 bg-amber-50/70 p-3.5">
                <label className="flex items-start gap-2.5 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={confirmedIdentity}
                    onChange={(e) => setConfirmedIdentity(e.target.checked)}
                    className="mt-0.5 h-4 w-4 rounded border-slate-300 text-nis-primary focus:ring-nis-primary"
                    required
                  />
                  <span className="text-xs font-medium text-amber-900 leading-snug">
                    {t("I confirm that I have physically verified the applicant's travel document (passport) and appointment slip, and am handing over card {number} to the applicant.", { number: issuingCard.card_number })}
                  </span>
                </label>
              </div>

              {/* Optional Notes */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  {t("Issuance / Collection Notes (Optional)")}
                </label>
                <Input
                  value={issueNotes}
                  onChange={(e) => setIssueNotes(e.target.value)}
                  placeholder={t("e.g. Card collected by applicant in person; identity checked.")}
                />
              </div>

              {/* Action Buttons */}
              <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => setIssuingCard(null)}
                  disabled={busy}
                >
                  {t("Cancel")}
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  disabled={busy || !confirmedIdentity}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                  {busy ? t("Processing...") : t("Confirm & Issue Card")}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export default function StaffCardIssuancePage() {
  return (
    <Suspense fallback={<Spinner />}>
      <CardIssuanceContent />
    </Suspense>
  );
}
