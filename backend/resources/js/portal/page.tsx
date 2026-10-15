"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Alert, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { PushToggle } from "@/components/PushToggle";
import { api } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import type { AppNotification, Application } from "@/lib/types";
import { withBase } from "@/lib/base-path";

type Me = {
  id?: number;
  forenames: string;
  surname: string;
  email: string;
  has_draft: boolean;
  unread_notifications?: number;
};

type DraftInfo = {
  type: string;
  current_step: number;
  updated_at?: string;
};

function getStatusBadgeConfig(status: string) {
  const s = status.toUpperCase();

  // Grey – Draft
  if (s === "DRAFT") {
    return {
      label: "Draft",
      cls: "bg-slate-100 text-slate-700 border-slate-300",
      dot: "bg-slate-400",
    };
  }

  // Blue – Submitted / Processing
  if (
    s === "SUBMITTED" ||
    s === "APPROVED_FOR_BIOMETRICS" ||
    s === "BIOMETRICS_CAPTURED"
  ) {
    let label = "Submitted";
    if (s === "APPROVED_FOR_BIOMETRICS") label = "Approved for Biometrics";
    if (s === "BIOMETRICS_CAPTURED") label = "Biometrics Captured";
    return {
      label,
      cls: "bg-blue-50 text-blue-700 border-blue-200",
      dot: "bg-blue-500",
    };
  }

  // Amber – Pending Approval / Queried / Pending Payment
  if (s === "PENDING_APPROVAL" || s === "QUERIED" || s === "PENDING_PAYMENT") {
    let label = "Pending Approval";
    if (s === "QUERIED") label = "Queried — Action Required";
    if (s === "PENDING_PAYMENT") label = "Pending Payment";
    return {
      label,
      cls: "bg-amber-50 text-amber-800 border-amber-200",
      dot: "bg-amber-500",
    };
  }

  // Green – Approved / Active
  if (
    s === "READY_FOR_COLLECTION" ||
    s === "ISSUED" ||
    s === "APPROVED" ||
    s === "CURRENT" ||
    s === "ACTIVE" ||
    s === "VALID"
  ) {
    let label = "Approved";
    if (s === "READY_FOR_COLLECTION") label = "Ready for Collection";
    if (s === "ISSUED") label = "Current / Active";
    if (s === "VALID" || s === "ACTIVE") label = "Active";
    return {
      label,
      cls: "bg-emerald-50 text-emerald-800 border-emerald-200",
      dot: "bg-emerald-500",
    };
  }

  // Red – Rejected / Expired / Revoked
  if (s === "REJECTED" || s === "EXPIRED" || s === "REVOKED") {
    let label = "Rejected";
    if (s === "EXPIRED") label = "Expired";
    if (s === "REVOKED") label = "Revoked";
    return {
      label,
      cls: "bg-red-50 text-red-700 border-red-200",
      dot: "bg-red-500",
    };
  }

  return {
    label: s.replace(/_/g, " "),
    cls: "bg-slate-100 text-slate-700 border-slate-300",
    dot: "bg-slate-400",
  };
}

function PortalStatusBadge({ status, labelOverride }: { status: string; labelOverride?: string }) {
  const conf = getStatusBadgeConfig(status);
  const displayLabel = labelOverride || conf.label;
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${conf.cls}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${conf.dot}`} />
      <span>{displayLabel}</span>
    </span>
  );
}

export default function PortalHome() {
  const router = useRouter();
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

  const [draft, setDraft] = useState<DraftInfo | null>(null);

  const [notifications, setNotifications] = useState<AppNotification[]>(() => {
    if (typeof window === "undefined") return [];
    try {
      const cached = sessionStorage.getItem("nis_applicant_notifs");
      return cached ? JSON.parse(cached) : [];
    } catch {
      return [];
    }
  });

  const [notifLoading, setNotifLoading] = useState(false);
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
      if (draftData && draftData.draft) {
        setDraft(draftData.draft);
      }
      if (notifsData) {
        setNotifications(notifsData.data);
        try { sessionStorage.setItem("nis_applicant_notifs", JSON.stringify(notifsData.data)); } catch {}
      }
    });
  }, []);

  async function markAllNotificationsRead() {
    setNotifLoading(true);
    try {
      await api("applicant", "notifications/read", { method: "POST" });
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
    } catch {
      // Ignore
    } finally {
      setNotifLoading(false);
    }
  }

  async function handleNotificationClick(n: AppNotification) {
    if (!n.read_at) {
      try {
        await api("applicant", "notifications/read", {
          method: "POST",
          json: { id: n.id },
        });
        setNotifications((prev) =>
          prev.map((item) => (item.id === n.id ? { ...item, read_at: new Date().toISOString() } : item))
        );
      } catch {
        // Ignore
      }
    }
    if (n.data?.application_id) {
      router.push(`/portal/applications/${n.data.application_id}`);
    }
  }

  if (!me || !applications) return <Spinner label={t("Loading…")} />;

  // Only the account holder's own application blocks a new one; dependants' applications do not.
  const activeApp = applications.find(
    (a) => !a.dependant_relationship && !["REJECTED", "ISSUED"].includes(a.status)
  );
  const queried = applications.filter((a) => a.status === "QUERIED");
  const hasDraft = me.has_draft || !!draft;
  const unreadNotifsCount = notifications.filter((n) => !n.read_at).length;

  return (
    <div className="space-y-8">
      {/* 1. Applicant Welcome Header */}
      <section className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-nis-primary">
              <span className="h-2 w-2 rounded-full bg-nis-primary" />
              {t("Applicant Dashboard")}
            </div>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
              {t("Welcome, {name}", { name: `${me.forenames} ${me.surname}`.trim() })}
            </h1>
            <p className="mt-1 text-sm text-slate-500">
              {me.email} &bull; {t("ECOWAS Residence Card Portal")}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            {hasDraft && (
              <span className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800">
                <svg className="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {t("Draft in progress")}
              </span>
            )}
            <span className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
              {applications.length} {applications.length === 1 ? t("Application") : t("Applications")}
            </span>
          </div>
        </div>
      </section>

      {/* Queried Action Banner */}
      {queried.map((a) => (
        <Alert key={a.id} tone="warning">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <strong>Action required:</strong> Application <strong>{a.application_number}</strong> has been queried.
              {a.decision_notes && <span className="block mt-0.5 text-xs text-amber-900">{a.decision_notes}</span>}
            </div>
            <Link
              href={`/portal/applications/${a.id}`}
              className="inline-flex shrink-0 items-center gap-1 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700"
            >
              {t("Respond now")} &rarr;
            </Link>
          </div>
        </Alert>
      ))}

      {/* 2. Top Action Cards Grid */}
      <section className="grid grid-cols-1 gap-5 md:grid-cols-3">
        {/* Card 1: Apply for New Residence Card */}
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-nis-primary/50 hover:shadow-md">
          <div>
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-nis-primary">
              <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <line x1="7" y1="8" x2="17" y2="8" />
                <line x1="7" y1="12" x2="13" y2="12" />
                <line x1="7" y1="16" x2="11" y2="16" />
              </svg>
            </div>
            <h2 className="mt-4 text-lg font-bold text-slate-900">
              {t("Apply for New Residence Card")}
            </h2>
            <p className="mt-2 text-sm text-slate-600 leading-relaxed">
              {t("Submit a new application for your ECOWAS Residence Card for work, business, study, or residence in Nigeria.")}
            </p>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100">
            {hasDraft && draft?.type !== "RENEWAL" ? (
              <Link
                href="/portal/apply"
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-nis-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-nis-primary-dark"
              >
                <span>{t("Continue Application")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            ) : (
              <Link
                href="/portal/apply"
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-nis-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-nis-primary-dark"
              >
                <span>{t("Start New Application")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            )}
          </div>
        </div>

        {/* Card 2: Renew Residence Card */}
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-nis-primary/50 hover:shadow-md">
          <div>
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-nis-primary">
              <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
              </svg>
            </div>
            <h2 className="mt-4 text-lg font-bold text-slate-900">
              {t("Renew Residence Card")}
            </h2>
            <p className="mt-2 text-sm text-slate-600 leading-relaxed">
              {t("Extend the validity of an existing ECOWAS Residence Card prior to or following expiration.")}
            </p>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100">
            {hasDraft && draft?.type === "RENEWAL" ? (
              <Link
                href="/portal/apply?type=renewal"
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-nis-primary bg-nis-mint px-4 py-2.5 text-center text-sm font-semibold text-nis-primary transition hover:bg-emerald-100"
              >
                <span>{t("Continue Renewal")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            ) : (
              <Link
                href="/portal/apply?type=renewal"
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:border-nis-primary hover:text-nis-primary"
              >
                <span>{t("Renew Card")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            )}
          </div>
        </div>

        {/* Card 3: Application Status */}
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-nis-primary/50 hover:shadow-md">
          <div>
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-nis-primary">
              <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <path d="m9 12 2 2 4-4" />
              </svg>
            </div>
            <h2 className="mt-4 text-lg font-bold text-slate-900">
              {t("Application Status")}
            </h2>
            <p className="mt-2 text-sm text-slate-600 leading-relaxed">
              {activeApp ? (
                <>
                  <span className="font-semibold text-slate-800">Ref: {activeApp.application_number}</span>
                  <span className="block text-xs text-slate-500 mt-1">
                    Status: <span className="font-medium text-nis-primary">{activeApp.status_label}</span>
                  </span>
                </>
              ) : (
                t("Track the real-time processing status of your submitted applications and biometrics appointments.")
              )}
            </p>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100">
            {activeApp ? (
              <Link
                href={`/portal/applications/${activeApp.id}`}
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:border-nis-primary hover:text-nis-primary"
              >
                <span>{t("Track Application")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            ) : (
              <Link
                href="/portal/cards"
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:border-nis-primary hover:text-nis-primary"
              >
                <span>{t("View Residence Cards")}</span>
                <span aria-hidden>&rarr;</span>
              </Link>
            )}
          </div>
        </div>
      </section>

      {/* 3. My Applications Section (Responsive Table) */}
      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/60 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-lg font-bold text-slate-900">{t("My Applications")}</h2>
            <p className="mt-0.5 text-xs text-slate-500">
              {t("Complete history of all ECOWAS Residence Card applications under your account.")}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
              {applications.length + (hasDraft ? 1 : 0)} Total
            </span>
          </div>
        </div>

        {applications.length === 0 && !hasDraft ? (
          <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
              <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <line x1="7" y1="8" x2="17" y2="8" />
                <line x1="7" y1="12" x2="13" y2="12" />
              </svg>
            </div>
            <p className="text-base font-semibold text-slate-800">{t("No applications found")}</p>
            <p className="mt-1 text-sm text-slate-500 max-w-sm">
              {t("You have not submitted any applications yet. Begin your application today.")}
            </p>
            <Link
              href="/portal/apply"
              className="mt-4 rounded-xl bg-nis-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-nis-primary-dark transition"
            >
              {t("Apply for Residence Card")}
            </Link>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[650px] border-collapse text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                  <th scope="col" className="px-6 py-3.5">{t("Application Reference")}</th>
                  <th scope="col" className="px-6 py-3.5">{t("Application Type")}</th>
                  <th scope="col" className="px-6 py-3.5">{t("Date Submitted")}</th>
                  <th scope="col" className="px-6 py-3.5">{t("Status")}</th>
                  <th scope="col" className="px-6 py-3.5 text-right">{t("Action")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {/* Draft Row if available */}
                {hasDraft && (
                  <tr className="bg-amber-50/20 transition hover:bg-amber-50/40">
                    <td className="px-6 py-4 font-semibold text-slate-800">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs font-bold text-amber-700">DRAFT</span>
                        <span className="text-[11px] font-normal text-slate-500">
                          (Step {draft?.current_step || 1} of 7)
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      {draft?.type === "RENEWAL" ? t("Renewal") : t("New Application")}
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-400">
                      {draft?.updated_at ? `Saved ${dateTime(draft.updated_at)}` : "—"}
                    </td>
                    <td className="px-6 py-4">
                      <PortalStatusBadge status="DRAFT" />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <Link
                        href="/portal/apply"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-nis-primary px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-primary-dark"
                      >
                        <span>{t("Continue Application")}</span>
                        <span aria-hidden>&rarr;</span>
                      </Link>
                    </td>
                  </tr>
                )}

                {/* Submitted Applications */}
                {applications.map((a) => (
                  <tr key={a.id} className="transition hover:bg-slate-50/80">
                    <td className="px-6 py-4 font-semibold">
                      <Link
                        href={`/portal/applications/${a.id}`}
                        className="font-mono font-bold text-nis-primary hover:underline"
                      >
                        {a.application_number || a.reference_number || `APP-${a.id}`}
                      </Link>
                      {a.dependant_relationship && (
                        <div className="text-[11px] font-normal text-slate-500 mt-0.5">
                          {a.forenames} {a.surname} ({a.dependant_relationship})
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 font-medium text-slate-800">
                      {a.type === "RENEWAL"
                        ? t("Renewal")
                        : a.type === "REPLACE"
                        ? t("Card Replacement")
                        : t("New Application")}
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-600">
                      {a.submitted_at ? nisDate(a.submitted_at) : "—"}
                    </td>
                    <td className="px-6 py-4">
                      <PortalStatusBadge status={a.status} labelOverride={a.status_label} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <Link
                        href={`/portal/applications/${a.id}`}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-nis-primary hover:text-nis-primary"
                      >
                        <span>{t("View Details")}</span>
                        <span aria-hidden>&rarr;</span>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {/* 4. Bottom Grid: Notifications & Personal Data */}
      <section className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Notifications Panel */}
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div>
            <div className="flex items-center justify-between border-b border-slate-100 pb-4">
              <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-nis-primary">
                  <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                  </svg>
                </div>
                <h2 className="text-base font-bold text-slate-900">{t("Notifications")}</h2>
                {unreadNotifsCount > 0 && (
                  <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                    {unreadNotifsCount} new
                  </span>
                )}
              </div>

              {unreadNotifsCount > 0 && (
                <button
                  type="button"
                  onClick={markAllNotificationsRead}
                  disabled={notifLoading}
                  className="text-xs font-medium text-nis-primary hover:underline disabled:opacity-50"
                >
                  {t("Mark all as read")}
                </button>
              )}
            </div>

            <div className="mt-4">
              {notifications.length === 0 ? (
                <div className="py-8 text-center text-slate-500">
                  <p className="text-sm font-medium text-slate-700">{t("No notifications yet")}</p>
                  <p className="text-xs text-slate-400 mt-1">
                    {t("You will receive updates here as your application is processed.")}
                  </p>
                </div>
              ) : (
                <ul className="divide-y divide-slate-100">
                  {notifications.slice(0, 5).map((n) => {
                    const isUnread = !n.read_at;
                    return (
                      <li
                        key={n.id}
                        onClick={() => handleNotificationClick(n)}
                        className={`flex items-start gap-3 py-3 transition hover:bg-slate-50/80 cursor-pointer rounded-lg px-2 ${
                          isUnread ? "bg-emerald-50/40" : ""
                        }`}
                      >
                        <div className="pt-1">
                          {isUnread ? (
                            <span className="block h-2 w-2 rounded-full bg-nis-primary ring-2 ring-emerald-200" />
                          ) : (
                            <span className="block h-2 w-2 rounded-full bg-slate-300" />
                          )}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between gap-2">
                            <p className={`text-xs truncate ${isUnread ? "font-bold text-slate-900" : "font-medium text-slate-700"}`}>
                              {n.data.title || t("Application Update")}
                            </p>
                            <span className="shrink-0 text-[10px] text-slate-400">
                              {dateTime(n.created_at)}
                            </span>
                          </div>
                          {n.data.application_number && (
                            <p className="text-[11px] font-semibold text-nis-primary mt-0.5">
                              Ref: {n.data.application_number}
                            </p>
                          )}
                          {n.data.notes && (
                            <p className="mt-0.5 text-xs text-slate-600 line-clamp-2">
                              {n.data.notes}
                            </p>
                          )}
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100">
            <PushToggle />
          </div>
        </div>

        {/* Personal Data & NDPA 2023 */}
        <div className="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div>
            <div className="flex items-center gap-2 border-b border-slate-100 pb-4">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-nis-primary">
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              </div>
              <h2 className="text-base font-bold text-slate-900">{t("Your Personal Data")}</h2>
            </div>

            <div className="mt-4 text-sm text-slate-600 leading-relaxed">
              <p>
                {t("Under the Nigeria Data Protection Act (NDPA) 2023, you have the right to request and download a full export of all personal data, documents, and application history held by the Nigeria Immigration Service.")}
              </p>
              <p className="mt-3 text-xs text-slate-500">
                {t("Review our ")}
                <Link href="/privacy" className="text-nis-primary font-medium underline">
                  {t("Privacy Notice")}
                </Link>
                {t(" for details on how your biometric and biographical data is processed and protected.")}
              </p>
            </div>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100">
            <a
              href={withBase("/api/bff/applicant/my-data")}
              download="my-nis-rcis-data.json"
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-nis-primary hover:text-nis-primary"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>{t("Download My Data (JSON)")}</span>
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}
