"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Panel, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { PushToggle } from "@/components/PushToggle";
import { api } from "@/lib/api-client";
import { dateTime, nisDate, applicationType } from "@/lib/format";
import type { AppNotification, Application } from "@/lib/types";
import { withBase } from "@/lib/base-path";

type Me = { forenames: string; surname: string; email: string; has_draft: boolean };

type DraftInfo = {
  type: string;
  current_step: number;
  data: Record<string, any>;
  documents: any[];
  saved_at: string;
};

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
  const [notifications, setNotifications] = useState<AppNotification[]>(() => {
    if (typeof window === "undefined") return [];
    try {
      const cached = sessionStorage.getItem("nis_applicant_notifs");
      return cached ? JSON.parse(cached) : [];
    } catch {
      return [];
    }
  });
  const [activeTab, setActiveTab] = useState<"all" | "drafts">("all");
  const { t } = useI18n();

  useEffect(() => {
    Promise.all([
      api<Me>("applicant", "me").catch(() => null),
      api<{ data: Application[] }>("applicant", "applications").catch(() => null),
      api<{ draft: DraftInfo | null }>("applicant", "draft").catch(() => null),
      api<{ data: AppNotification[] }>("applicant", "notifications").catch(() => null),
    ]).then(([meData, appsData, draftData, notifsData]) => {
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
      if (notifsData) {
        setNotifications(notifsData.data);
        try { sessionStorage.setItem("nis_applicant_notifs", JSON.stringify(notifsData.data)); } catch {}
      }
    });
  }, []);

  if (!me || !applications) return <Spinner label={t("Loading…")} />;

  const queried = applications.filter((a) => a.status === "QUERIED");
  const totalAllCount = applications.length + (draft ? 1 : 0);

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant console")}
        title={t("Welcome, {name}", { name: me.forenames })}
        subtitle={me.email}
        actions={
          <div className="flex gap-2">
            <Link
              href="/portal/apply"
              className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
            >
              {t("New Application")}
            </Link>
            <Link
              href="/portal/apply?type=renewal"
              className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
              {t("Renew Card")}
            </Link>
          </div>
        }
      />

      {queried.map((a) => (
        <Alert key={a.id} tone="warning">
          Application <strong>{a.application_number}</strong> has been queried: {a.decision_notes}{" "}
          <Link href={`/portal/applications/${a.id}`} className="font-semibold underline">{t("Respond now")}</Link>
        </Alert>
      ))}

      <Panel title={t("My Applications")}>
        {/* Simple Tabs: All Applications | Draft Applications */}
        <div className="flex border-b border-slate-200 mb-4 gap-6 text-sm font-medium">
          <button
            type="button"
            onClick={() => setActiveTab("all")}
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
            onClick={() => setActiveTab("drafts")}
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
                      <th scope="col" className="px-3.5 py-3 text-center w-12">{t("S/N")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Application Reference")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Application Type")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Date")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Status")}</th>
                      <th scope="col" className="px-3.5 py-3 text-right">{t("Action")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    {/* Display draft first if exists */}
                    {draft && (
                      <tr className="hover:bg-slate-50/60 transition-colors">
                        <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">1</td>
                        <td className="px-3.5 py-3 font-semibold text-slate-700">
                          {draft.data?.reference_number || draft.data?.application_number || "Draft"}
                        </td>
                        <td className="px-3.5 py-3 text-slate-600">
                          {draft.type === "RENEWAL" ? t("Renewal") : draft.type === "REPLACE" ? t("Card Replacement") : t("New Application")}
                        </td>
                        <td className="px-3.5 py-3 text-slate-500 text-xs">{nisDate(draft.saved_at)}</td>
                        <td className="px-3.5 py-3">
                          <StatusBadge status="DRAFT" label={t("Draft")} />
                        </td>
                        <td className="px-3.5 py-3 text-right">
                          <Link
                            href="/portal/apply?resume=1"
                            className="inline-flex items-center gap-1 rounded bg-nis-green px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
                          >
                            {t("Continue")}
                          </Link>
                        </td>
                      </tr>
                    )}

                    {/* Display submitted applications */}
                    {applications.map((a, idx) => {
                      const sn = (draft ? 1 : 0) + idx + 1;
                      return (
                        <tr key={a.id} className="hover:bg-slate-50/60 transition-colors">
                          <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">{sn}</td>
                          <td className="px-3.5 py-3">
                            <Link href={`/portal/applications/${a.id}`} className="font-semibold text-nis-green hover:underline">
                              {a.application_number || a.reference_number}
                            </Link>
                          </td>
                          <td className="px-3.5 py-3 text-slate-600">{applicationType(a)}</td>
                          <td className="px-3.5 py-3 text-slate-500 text-xs">
                            {nisDate(a.submitted_at || a.created_at)}
                            {a.status === "APPROVED_FOR_BIOMETRICS" && a.appointment_date && (
                              <div className="text-[11px] text-slate-400">
                                {t("Biometrics: {d}", { d: nisDate(a.appointment_date) })}
                              </div>
                            )}
                          </td>
                          <td className="px-3.5 py-3">
                            <StatusBadge status={a.status} label={a.status_label} />
                          </td>
                          <td className="px-3.5 py-3 text-right">
                            <Link
                              href={`/portal/applications/${a.id}`}
                              className="font-semibold text-xs sm:text-sm text-nis-green hover:underline"
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
          </div>
        )}

        {/* Tab 2: Draft Applications */}
        {activeTab === "drafts" && (
          <div>
            {!draft ? (
              <div className="py-8 text-center text-sm text-slate-500">
                <p className="font-medium text-slate-700">{t("No draft applications.")}</p>
                <p className="text-xs text-slate-400 mt-1">{t("You do not have any saved draft applications.")}</p>
              </div>
            ) : (
              <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                      <th scope="col" className="px-3.5 py-3 text-center w-12">{t("S/N")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Application Reference")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Application Type")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Date")}</th>
                      <th scope="col" className="px-3.5 py-3">{t("Status")}</th>
                      <th scope="col" className="px-3.5 py-3 text-right">{t("Action")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    <tr className="hover:bg-slate-50/60 transition-colors">
                      <td className="px-3.5 py-3 text-center text-xs font-medium text-slate-500">1</td>
                      <td className="px-3.5 py-3 font-semibold text-slate-700">
                        {draft.data?.reference_number || draft.data?.application_number || "Draft"}
                      </td>
                      <td className="px-3.5 py-3 text-slate-600">
                        {draft.type === "RENEWAL" ? t("Renewal") : draft.type === "REPLACE" ? t("Card Replacement") : t("New Application")}
                      </td>
                      <td className="px-3.5 py-3 text-slate-500 text-xs">{nisDate(draft.saved_at)}</td>
                      <td className="px-3.5 py-3">
                        <StatusBadge status="DRAFT" label={t("Draft")} />
                      </td>
                      <td className="px-3.5 py-3 text-right">
                        <Link
                          href="/portal/apply?resume=1"
                          className="inline-flex items-center gap-1 rounded bg-nis-green px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-green/90"
                        >
                          {t("Continue")}
                        </Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </Panel>

      <Panel title={t("Notifications")}>
        {notifications.length === 0 ? (
          <p className="text-sm text-slate-600">{t("No notifications yet.")}</p>
        ) : (
          <ul className="space-y-3">
            {notifications.slice(0, 10).map((n) => (
              <li key={n.id} className="text-sm">
                <span className="font-medium">{n.data.title}</span>
                {n.data.application_number && <span className="text-slate-500"> — {n.data.application_number}</span>}
                {n.data.notes && <p className="text-slate-600">{n.data.notes}</p>}
                <p className="text-xs text-slate-400">{dateTime(n.created_at)}</p>
              </li>
            ))}
          </ul>
        )}
      </Panel>
      <PushToggle />
      <section className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm" aria-label="Your personal data">
        <h2 className="text-[15px] font-medium text-nis-primary-dark">{t("Your personal data")}</h2>
        <p className="mt-1 text-slate-600">
          Under the Nigeria Data Protection Act 2023 you can download everything we hold about you. See the <Link href="/privacy" className="text-nis-primary underline">privacy notice</Link> for how your data is used and kept.
        </p>
        <a href={withBase("/api/bff/applicant/my-data")} download="my-nis-rcis-data.json" className="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">{t("Download my data")}</a>
      </section>
    </div>
  );
}
