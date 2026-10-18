"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { Alert, Button, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import type { AppNotification, Paginated } from "@/lib/types";
import { useI18n } from "@/components/I18nProvider";

export default function NotificationsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { t } = useI18n();

  const currentPage = Math.max(1, parseInt(searchParams.get("page") ?? "1", 10) || 1);

  const [loading, setLoading] = useState(true);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [meta, setMeta] = useState<Paginated<AppNotification>["meta"]>();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loadNotifications = useCallback(async (page: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api<{
        data: AppNotification[];
        unread: number;
        meta?: Paginated<AppNotification>["meta"];
      }>("applicant", `notifications?per_page=10&page=${page}`);

      const sorted = (res.data || []).sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      );
      setNotifications(sorted);
      setUnreadCount(res.unread || 0);
      setMeta(res.meta);
    } catch (e) {
      setError((e as Error).message || "Failed to load notifications.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadNotifications(currentPage);
  }, [currentPage, loadNotifications]);

  async function handleNotificationClick(n: AppNotification) {
    if (!n.read_at) {
      try {
        await api("applicant", "notifications/read", {
          method: "POST",
          json: { id: n.id },
        });
        setUnreadCount((c) => Math.max(0, c - 1));
        setNotifications((prev) =>
          prev.map((item) => (item.id === n.id ? { ...item, read_at: new Date().toISOString() } : item))
        );
      } catch {
        // Ignore
      }
    }

    if (n.data?.application_id) {
      const isQueried = n.data.status === "QUERIED";
      const targetUrl = isQueried
        ? `/portal/apply?query_app=${n.data.application_id}&step=4`
        : (n.data.action_url || `/portal/applications/${n.data.application_id}`);
      router.push(targetUrl);
    }
  }

  async function markAllAsRead() {
    setBusy(true);
    try {
      await api("applicant", "notifications/read", { method: "POST" });
      setUnreadCount(0);
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
    } catch (e) {
      setError((e as Error).message || "Failed to mark notifications as read.");
    } finally {
      setBusy(false);
    }
  }

  const total = meta?.total ?? notifications.length;
  const perPage = meta?.per_page ?? 10;
  const lastPage = Math.max(1, meta?.last_page ?? (Math.ceil(total / perPage) || 1));
  const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const to = Math.min(total, currentPage * perPage);

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant portal")}
        title={t("Notifications")}
        subtitle={t("Stay informed on the progress of your application, query notices, and appointment details.")}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {unreadCount > 0 && (
              <Button
                variant="secondary"
                onClick={markAllAsRead}
                disabled={busy || loading}
              >
                {t("Mark all as read")}
              </Button>
            )}
            <Link
              href="/portal"
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors"
            >
              <span>&larr;</span>
              <span>{t("Back to Dashboard")}</span>
            </Link>
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      <Panel
        title={
          <div className="flex items-center gap-2">
            <span>{t("All Notifications")}</span>
            {unreadCount > 0 && (
              <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                {unreadCount} {t("unread")}
              </span>
            )}
          </div>
        }
      >
        {loading ? (
          <Spinner />
        ) : notifications.length === 0 ? (
          <div className="flex flex-col items-center justify-center p-12 text-center text-slate-500">
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
              <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </div>
            <p className="text-base font-semibold text-slate-800">{t("No notifications yet.")}</p>
            <p className="text-xs text-slate-500 mt-1 max-w-sm">
              {t("You have not received any notifications yet. Updates regarding your applications and appointments will appear here.")}
            </p>
            <div className="mt-4">
              <Link
                href="/portal"
                className="inline-flex items-center gap-1 text-xs font-semibold text-nis-primary hover:underline"
              >
                &larr; {t("Back to Dashboard")}
              </Link>
            </div>
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {notifications.map((n) => {
              const isUnread = !n.read_at;
              const isQueried = n.data?.status === "QUERIED";
              const refNo = n.data?.application_number || n.data?.reference_number;
              return (
                <div
                  key={n.id}
                  onClick={() => handleNotificationClick(n)}
                  className={`flex items-start gap-4 p-4 text-left transition hover:bg-slate-50/80 cursor-pointer rounded-xl ${
                    isUnread
                      ? isQueried
                        ? "bg-amber-50/60 border border-amber-200/70"
                        : "bg-emerald-50/40 border border-emerald-100"
                      : "bg-white"
                  }`}
                >
                  <div className="pt-1.5 shrink-0">
                    {isUnread ? (
                      <span
                        className={`block h-3 w-3 rounded-full ring-2 ${
                          isQueried
                            ? "bg-amber-500 ring-amber-200"
                            : "bg-nis-primary ring-emerald-200"
                        }`}
                        title="Unread notification"
                      />
                    ) : (
                      <span className="block h-2.5 w-2.5 rounded-full bg-slate-300" title="Read" />
                    )}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                      <div className="flex items-center gap-2">
                        <p
                          className={`text-sm ${
                            isUnread
                              ? isQueried
                                ? "font-bold text-amber-950"
                                : "font-bold text-slate-900"
                              : "font-semibold text-slate-700"
                          }`}
                        >
                          {n.data?.title || (isQueried ? t("Application Requires Your Attention") : t("Application Update"))}
                        </p>
                        {isUnread && (
                          <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                            NEW
                          </span>
                        )}
                      </div>
                      <span className="text-xs text-slate-400 whitespace-nowrap">
                        {dateTime(n.created_at)}
                      </span>
                    </div>

                    {refNo && (
                      <p className={`text-xs font-semibold mt-1 ${isQueried ? "text-amber-800" : "text-nis-primary"}`}>
                        Application Reference: {refNo}
                      </p>
                    )}

                    {isQueried && (n.data?.query_reason || n.data?.notes) ? (
                      <div className="mt-2 rounded-lg border border-amber-200 bg-amber-50/90 p-3 text-xs text-amber-950 leading-relaxed">
                        <span className="font-semibold">{t("Query reason / instructions")}: </span>
                        <span>{n.data.query_reason || n.data.notes}</span>
                      </div>
                    ) : (
                      n.data?.notes && (
                        <p className="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                          {n.data.notes}
                        </p>
                      )
                    )}

                    <div className="mt-3 flex items-center gap-3">
                      {isQueried ? (
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-700 transition">
                          <span>{t("Respond to Query")}</span>
                          <span aria-hidden="true">&rarr;</span>
                        </span>
                      ) : n.data?.application_id ? (
                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-nis-green hover:underline">
                          <span>{t("View Application Details")}</span>
                          <span aria-hidden="true">&rarr;</span>
                        </span>
                      ) : null}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </Panel>

      {/* Pagination Controls */}
      {total > 0 && lastPage > 1 && (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 text-sm">
          <span className="text-slate-600 text-xs sm:text-sm">
            Showing <span className="font-medium text-slate-900">{from}</span>–
            <span className="font-medium text-slate-900">{to}</span> of{" "}
            <span className="font-medium text-slate-900">{total}</span> notifications
          </span>

          <div className="flex items-center gap-1">
            {currentPage > 1 ? (
              <Link
                href={`/portal/notifications?page=${currentPage - 1}`}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
              >
                Previous
              </Link>
            ) : (
              <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">
                Previous
              </span>
            )}

            <div className="flex items-center gap-1">
              {Array.from({ length: lastPage }, (_, i) => i + 1).map((pNum) => (
                <Link
                  key={pNum}
                  href={`/portal/notifications?page=${pNum}`}
                  className={`min-w-[32px] text-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors ${
                    currentPage === pNum
                      ? "bg-nis-primary text-white font-semibold"
                      : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                  }`}
                >
                  {pNum}
                </Link>
              ))}
            </div>

            {currentPage < lastPage ? (
              <Link
                href={`/portal/notifications?page=${currentPage + 1}`}
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
