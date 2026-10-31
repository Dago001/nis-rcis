"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Panel, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { PushToggle } from "@/components/PushToggle";
import { api } from "@/lib/api-client";
import type { Application } from "@/lib/types";
import { withBase } from "@/lib/base-path";

type Me = { forenames: string; surname: string; email: string; has_draft: boolean };

type DraftInfo = {
  type: string;
  current_step: number;
  data: Record<string, any>;
  documents: any[];
  saved_at: string;
};

const PAGE_SIZE = 10;

/** Format category/class name consistently (e.g. Work (Employment), Business (Trade / Artisans / Enterprise), Student (Intent), Cleric / Missionaries). */
function formatCategory(cat?: string | null): string {
  if (!cat || !cat.trim()) return "Not specified";
  const upper = cat.trim().toUpperCase();
  if (upper === "WORK" || upper.includes("WORK") || upper.includes("EMPLOY")) return "Work (Employment)";
  if (upper === "BUSINESS" || upper.includes("BUSI") || upper.includes("INVEST") || upper.includes("ARTISAN")) return "Business (Trade / Artisans / Enterprise)";
  if (upper === "STUDENT" || upper.includes("STUD")) return "Student (Intent)";
  if (upper === "RELIGIOUS" || upper.includes("RELIG") || upper.includes("CLERIC") || upper.includes("MISSION")) return "Cleric / Missionaries";
  return cat.charAt(0).toUpperCase() + cat.slice(1).toLowerCase();
}

/** Format dates compactly as '15 Oct 2026' without timestamps or suffix clutter. */
function compactDate(value?: string | null): string {
  if (!value) return "—";
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  if (Number.isNaN(d.getTime())) return value;
  const day = d.getDate();
  const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  return `${day} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

/** Map long statuses to compact, professional display labels. */
function compactStatusLabel(status: string, label?: string): string {
  const s = status.toUpperCase();
  if (s === "DRAFT") return "Draft";
  if (s === "SUBMITTED") return "Submitted";
  if (s === "PENDING_APPROVAL" || s === "VETTED") return "Pending";
  if (s === "APPROVED" || s === "APPROVED_FOR_BIOMETRICS") return "Approved";
  if (s === "BIOMETRICS_CAPTURED") return "Biometrics Captured";
  if (s === "CARD_IN_PRODUCTION" || s === "IN_PRODUCTION") return "In Production";
  if (s === "READY_FOR_COLLECTION") return "Ready";
  if (s === "ISSUED") return "Issued";
  if (s === "QUERIED") return "Queried";
  if (s === "REJECTED") return "Rejected";
  if (s === "RENEWED") return "Renewed";
  return label || status.replaceAll("_", " ");
}

export default function PortalHome() {
  const [me, setMe] = useState<Me | null>(() => {
    if (typeof window === "undefined") return null;
    try {
      const cached = sessionStorage.getItem("nis_applicant_me");
      return cached ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [applications, setApplications] = useState<Application[] | null>(() => {
    if (typeof window === "undefined") return null;
    try {
      const cached = sessionStorage.getItem("nis_applicant_apps");
      return cached ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [draft, setDraft] = useState<DraftInfo | null>(() => {
    if (typeof window === "undefined") return null;
    try {
      const cached = sessionStorage.getItem("nis_applicant_draft");
      return cached ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [activeTab, setActiveTab] = useState<"all" | "drafts">("all");
  const [pageAll, setPageAll] = useState(1);
  const [pageDrafts, setPageDrafts] = useState(1);
  const { t } = useI18n();

  useEffect(() => {
    Promise.all([
      api<Me>("applicant", "me").catch(() => null),
      api<{ data: Application[] }>("applicant", "applications").catch(() => null),
      api<{ draft: DraftInfo | null }>("applicant", "draft").catch(() => null),
    ]).then(([meData, appsData, draftData]) => {
      if (meData) {
        setMe(meData);
        try { sessionStorage.setItem("nis_applicant_me", JSON.stringify(meData)); } catch {}
      }
      if (appsData) {
        setApplications(appsData.data);
        try { sessionStorage.setItem("nis_applicant_apps", JSON.stringify(appsData.data)); } catch {}
      }
      if (draftData) {
        setDraft(draftData.draft);
        try { sessionStorage.setItem("nis_applicant_draft", JSON.stringify(draftData.draft)); } catch {}
      }
    });
  }, []);

  if (!me || !applications) return <Spinner label={t("Loading…")} />;

  const queried = applications.filter((a) => a.status === "QUERIED");

  // Tab switching with page reset
  const handleTabChange = (tab: "all" | "drafts") => {
    setActiveTab(tab);
    if (tab === "all") setPageAll(1);
    else setPageDrafts(1);
  };

  // Build items for All Applications (including draft at top if present)
  type TableRowItem =
    | { isDraft: true; draft: DraftInfo }
    | { isDraft: false; app: Application };

  const allItems: TableRowItem[] = [
    ...(draft ? [{ isDraft: true as const, draft }] : []),
    ...applications.map((app) => ({ isDraft: false as const, app })),
  ];

  const totalAllCount = allItems.length;
  const totalPagesAll = Math.max(1, Math.ceil(totalAllCount / PAGE_SIZE));
  const currentPageAll = Math.min(pageAll, totalPagesAll);
  const paginatedAll = allItems.slice((currentPageAll - 1) * PAGE_SIZE, currentPageAll * PAGE_SIZE);

  // Build items for Draft Applications
  const draftItems: DraftInfo[] = draft ? [draft] : [];
  const totalDraftsCount = draftItems.length;
  const totalPagesDrafts = Math.max(1, Math.ceil(totalDraftsCount / PAGE_SIZE));
  const currentPageDrafts = Math.min(pageDrafts, totalPagesDrafts);
  const paginatedDrafts = draftItems.slice(
    (currentPageDrafts - 1) * PAGE_SIZE,
    currentPageDrafts * PAGE_SIZE
  );

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant console")}
        title={t("Welcome, {name}", { name: me.forenames })}
        subtitle={me.email}
        actions={
          <div className="w-full sm:w-auto flex flex-col sm:flex-row gap-2">
            <Link
              href="/portal/apply"
              className="flex min-h-[40px] items-center justify-center rounded-lg bg-nis-green px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
            >
              {t("New Application")}
            </Link>
            <Link
              href="/portal/apply?type=renewal"
              className="flex min-h-[40px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              {t("Renew Card")}
            </Link>
          </div>
        }
      />

      {queried.map((a) => (
        <Alert key={a.id} tone="warning">
          Application <strong>{a.application_number}</strong> has been queried: {a.decision_notes}{" "}
          <Link href={`/portal/apply?query_app=${a.id}&step=4`} className="font-semibold underline">{t("Respond now")}</Link>
        </Alert>
      ))}

      <Panel title={t("My Applications")}>
        {/* Simple Tabs: All Applications | Draft Applications */}
        <div className="flex border-b border-slate-200 mb-4 gap-6 text-sm font-medium">
          <button
            type="button"
            onClick={() => handleTabChange("all")}
            className={`pb-2.5 transition-colors relative flex items-center gap-1.5 ${
              activeTab === "all"
                ? "text-nis-green font-semibold border-b-2 border-nis-green -mb-[2px]"
                : "text-slate-500 hover:text-slate-800"
            }`}
          >
            <span>{t("All Applications")}</span>
            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
              {totalAllCount}
            </span>
          </button>
          <button
            type="button"
            onClick={() => handleTabChange("drafts")}
            className={`pb-2.5 transition-colors relative flex items-center gap-1.5 ${
              activeTab === "drafts"
                ? "text-nis-green font-semibold border-b-2 border-nis-green -mb-[2px]"
                : "text-slate-500 hover:text-slate-800"
            }`}
          >
            <span>{t("Draft Applications")}</span>
            {draft && (
              <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 font-semibold">
                1
              </span>
            )}
          </button>
        </div>

        {/* Tab 1: All Applications */}
        {activeTab === "all" && (
          <div>
            {totalAllCount === 0 ? (
              <div className="py-8 text-center text-sm text-slate-500">
                <p className="font-medium text-slate-700">{t("No applications found.")}</p>
                <p className="text-xs text-slate-400 mt-1">{t("You have not started or submitted any applications yet.")}</p>
              </div>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                      <th scope="col" className="px-3.5 py-3 text-center w-12 sm:w-14">{t("S/N")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Application Reference")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Application Type")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Date")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Status")}</th>
                      <th scope="col" className="px-3.5 py-3 text-right whitespace-nowrap">{t("Action")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    {paginatedAll.map((item, idx) => {
                      const sn = (currentPageAll - 1) * PAGE_SIZE + idx + 1;
                      if (item.isDraft) {
                        const d = item.draft;
                        const categoryLabel = formatCategory(d.data?.category);
                        return (
                          <tr key="draft-row" className="hover:bg-slate-50/60 transition-colors">
                            <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">{sn}</td>
                            <td className="px-3.5 py-3 font-semibold text-slate-700 whitespace-nowrap">
                              {d.data?.reference_number || d.data?.application_number || "Draft"}
                            </td>
                            <td className="px-3.5 py-3 font-medium text-slate-700 whitespace-nowrap">
                              {categoryLabel}
                            </td>
                            <td className="px-3.5 py-3 text-slate-600 text-xs whitespace-nowrap">
                              {compactDate(d.saved_at)}
                            </td>
                            <td className="px-3.5 py-3 whitespace-nowrap">
                              <StatusBadge status="DRAFT" label={t("Draft")} />
                            </td>
                            <td className="px-3.5 py-3 text-right whitespace-nowrap">
                              <Link
                                href="/portal/apply?resume=1"
                                className="inline-flex items-center gap-1 rounded bg-nis-green px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
                              >
                                {t("Continue")}
                              </Link>
                            </td>
                          </tr>
                        );
                      }

                      const a = item.app;
                      const categoryLabel = formatCategory(a.category);
                      return (
                        <tr key={a.id} className="hover:bg-slate-50/60 transition-colors">
                          <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">{sn}</td>
                          <td className="px-3.5 py-3">
                            <Link href={`/portal/applications/${a.id}`} className="font-semibold text-nis-green hover:underline">
                              {a.application_number || a.reference_number}
                            </Link>
                            {a.dependant_relationship && (
                              <div className="text-xs font-medium text-purple-700 mt-0.5">
                                {t("Dependant")} ({a.dependant_relationship})
                              </div>
                            )}
                            {(a.principal || (a as any).principal_application_number) && (
                              <div className="text-[11px] text-slate-500 mt-0.5">
                                {t("Principal")}: <span className="font-mono text-slate-700 font-medium">{a.principal?.application_number || (a as any).principal_application_number}</span>
                              </div>
                            )}
                          </td>
                          <td className="px-3.5 py-3 font-medium text-slate-700 whitespace-nowrap">
                            {categoryLabel}
                          </td>
                          <td className="px-3.5 py-3 text-slate-600 text-xs whitespace-nowrap">
                            {compactDate(a.submitted_at || a.created_at)}
                          </td>
                          <td className="px-3.5 py-3 whitespace-nowrap">
                            <StatusBadge status={a.status} label={compactStatusLabel(a.status, a.status_label)} />
                          </td>
                          <td className="px-3.5 py-3 text-right whitespace-nowrap">
                            {a.status === "QUERIED" ? (
                              <Link
                                href={`/portal/apply?query_app=${a.id}&step=4`}
                                className="inline-flex items-center gap-1 rounded bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700"
                              >
                                {t("Respond to Query")}
                              </Link>
                            ) : (
                              <Link
                                href={`/portal/applications/${a.id}`}
                                className="font-semibold text-xs sm:text-sm text-nis-green hover:underline"
                              >
                                {t("View")}
                              </Link>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>

                {/* Pagination Controls for All Applications */}
                {totalAllCount > PAGE_SIZE && (
                  <div className="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-600">
                    <div>
                      {t("Showing {start}–{end} of {total} applications", {
                        start: (currentPageAll - 1) * PAGE_SIZE + 1,
                        end: Math.min(currentPageAll * PAGE_SIZE, totalAllCount),
                        total: totalAllCount,
                      })}
                    </div>
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => setPageAll((p) => Math.max(1, p - 1))}
                        disabled={currentPageAll === 1}
                        className="rounded border border-slate-300 bg-white px-2.5 py-1 font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        {t("Previous")}
                      </button>
                      {Array.from({ length: totalPagesAll }, (_, i) => i + 1).map((pageNum) => (
                        <button
                          key={pageNum}
                          type="button"
                          onClick={() => setPageAll(pageNum)}
                          className={`min-w-[28px] rounded px-2 py-1 font-semibold transition ${
                            pageNum === currentPageAll
                              ? "bg-nis-green text-white shadow-sm"
                              : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                          }`}
                        >
                          {pageNum}
                        </button>
                      ))}
                      <button
                        type="button"
                        onClick={() => setPageAll((p) => Math.min(totalPagesAll, p + 1))}
                        disabled={currentPageAll === totalPagesAll}
                        className="rounded border border-slate-300 bg-white px-2.5 py-1 font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        {t("Next")}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Tab 2: Draft Applications */}
        {activeTab === "drafts" && (
          <div>
            {totalDraftsCount === 0 ? (
              <div className="py-8 text-center text-sm text-slate-500">
                <p className="font-medium text-slate-700">{t("No draft applications.")}</p>
                <p className="text-xs text-slate-400 mt-1">{t("You do not have any saved draft applications.")}</p>
              </div>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                      <th scope="col" className="px-3.5 py-3 text-center w-12 sm:w-14">{t("S/N")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Application Reference")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Application Type")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Date")}</th>
                      <th scope="col" className="px-3.5 py-3 whitespace-nowrap">{t("Status")}</th>
                      <th scope="col" className="px-3.5 py-3 text-right whitespace-nowrap">{t("Action")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    {paginatedDrafts.map((d, idx) => {
                      const sn = (currentPageDrafts - 1) * PAGE_SIZE + idx + 1;
                      const categoryLabel = formatCategory(d.data?.category);
                      return (
                        <tr key="draft-tab-row" className="hover:bg-slate-50/60 transition-colors">
                          <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">{sn}</td>
                          <td className="px-3.5 py-3 font-semibold text-slate-700 whitespace-nowrap">
                            {d.data?.reference_number || d.data?.application_number || "Draft"}
                          </td>
                          <td className="px-3.5 py-3 font-medium text-slate-700 whitespace-nowrap">
                            {categoryLabel}
                          </td>
                          <td className="px-3.5 py-3 text-slate-600 text-xs whitespace-nowrap">
                            {compactDate(d.saved_at)}
                          </td>
                          <td className="px-3.5 py-3 whitespace-nowrap">
                            <StatusBadge status="DRAFT" label={t("Draft")} />
                          </td>
                          <td className="px-3.5 py-3 text-right whitespace-nowrap">
                            <Link
                              href="/portal/apply?resume=1"
                              className="inline-flex items-center gap-1 rounded bg-nis-green px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
                            >
                              {t("Continue")}
                            </Link>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>

                {/* Pagination Controls for Draft Applications (if > 10) */}
                {totalDraftsCount > PAGE_SIZE && (
                  <div className="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-600">
                    <div>
                      {t("Showing {start}–{end} of {total} draft applications", {
                        start: (currentPageDrafts - 1) * PAGE_SIZE + 1,
                        end: Math.min(currentPageDrafts * PAGE_SIZE, totalDraftsCount),
                        total: totalDraftsCount,
                      })}
                    </div>
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => setPageDrafts((p) => Math.max(1, p - 1))}
                        disabled={currentPageDrafts === 1}
                        className="rounded border border-slate-300 bg-white px-2.5 py-1 font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        {t("Previous")}
                      </button>
                      {Array.from({ length: totalPagesDrafts }, (_, i) => i + 1).map((pageNum) => (
                        <button
                          key={pageNum}
                          type="button"
                          onClick={() => setPageDrafts(pageNum)}
                          className={`min-w-[28px] rounded px-2 py-1 font-semibold transition ${
                            pageNum === currentPageDrafts
                              ? "bg-nis-green text-white shadow-sm"
                              : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                          }`}
                        >
                          {pageNum}
                        </button>
                      ))}
                      <button
                        type="button"
                        onClick={() => setPageDrafts((p) => Math.min(totalPagesDrafts, p + 1))}
                        disabled={currentPageDrafts === totalPagesDrafts}
                        className="rounded border border-slate-300 bg-white px-2.5 py-1 font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        {t("Next")}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        )}
      </Panel>

      <PushToggle />
      {/* NDPA 2023 Personal Data Download Section - Temporarily hidden via feature flag; underlying API (/api/bff/applicant/my-data) preserved */}
      {false /* SHOW_PERSONAL_DATA_DOWNLOAD */ && (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm" aria-label="Your personal data">
          <h2 className="text-[15px] font-medium text-nis-primary-dark">{t("Your personal data")}</h2>
          <p className="mt-1 text-slate-600">
            Under the Nigeria Data Protection Act 2023 you can download everything we hold about you. See the <Link href="/privacy" className="text-nis-primary underline">{t("privacy notice")}</Link> for how your data is used and kept.
          </p>
          <a href={withBase("/api/bff/applicant/my-data")} download="my-nis-rcis-data.json" className="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">{t("Download my data")}</a>
        </section>
      )}
    </div>
  );
}
