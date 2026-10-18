"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useI18n } from "@/components/I18nProvider";
import { api } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import type { AppNotification } from "@/lib/types";

export function NotificationBell({ portal = "applicant" }: { portal?: "applicant" | "staff" }) {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const { t } = useI18n();

  const scope = portal === "staff" ? "staff" : "applicant";

  async function fetchNotifications() {
    try {
      const res = await api<{ data: AppNotification[]; unread: number }>(scope, "notifications");
      // Sort newest first
      const sorted = (res.data || []).sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      );
      setNotifications(sorted);
      setUnreadCount(res.unread || 0);
    } catch {
      // Ignore background notification fetch errors
    }
  }

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 30000);
    return () => clearInterval(interval);
  }, [portal]);

  // Close on outside click or Escape key
  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setOpen(false);
      }
    }
    if (open) {
      document.addEventListener("mousedown", handleClickOutside);
      document.addEventListener("keydown", handleKeyDown);
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [open]);

  async function markAllAsRead() {
    setLoading(true);
    try {
      await api(scope, "notifications/read", { method: "POST" });
      setUnreadCount(0);
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
    } catch {
      // Ignore
    } finally {
      setLoading(false);
    }
  }

  async function handleNotificationClick(n: AppNotification) {
    if (!n.read_at) {
      try {
        await api(scope, "notifications/read", {
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
    setOpen(false);
    if (n.data?.application_id) {
      const isQueried = n.data.status === "QUERIED";
      const targetUrl = portal === "staff"
        ? `/staff/applications/${n.data.application_id}`
        : (isQueried
            ? `/portal/apply?query_app=${n.data.application_id}&step=4`
            : (n.data.action_url || `/portal/applications/${n.data.application_id}`));
      window.location.href = targetUrl;
    }
  }

  const recentNotifications = notifications.slice(0, 3);

  return (
    <div className="relative inline-flex items-center" ref={dropdownRef}>
      {/* Standalone navigation icon without card/container/border background */}
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-label={t("Notifications ({n} unread)", { n: unreadCount })}
        className="relative inline-flex items-center justify-center p-2 rounded-lg text-slate-700 transition-colors hover:text-nis-primary hover:bg-slate-100/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-nis-primary"
      >
        <svg
          className="h-5 w-5 sm:h-5 sm:w-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>

        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white">
            {unreadCount > 9 ? "9+" : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-2 w-80 sm:w-96 rounded-2xl border border-slate-200 bg-white shadow-xl ring-1 ring-black/5 animate-in fade-in-50 zoom-in-95 duration-100">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-4 py-3 rounded-t-2xl">
            <div className="flex items-center gap-2">
              <span className="text-sm font-semibold text-slate-900">{t("Notifications")}</span>
              {unreadCount > 0 && (
                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                  {unreadCount} {t("new")}
                </span>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={markAllAsRead}
                disabled={loading}
                className="text-xs font-semibold text-nis-primary hover:underline disabled:opacity-50"
              >
                {t("Mark all as read")}
              </button>
            )}
          </div>

          {/* Recent notifications list (maximum 3) */}
          <div className="divide-y divide-slate-100">
            {recentNotifications.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-6 text-center text-slate-500">
                <p className="text-sm font-medium text-slate-700">{t("No notifications yet.")}</p>
              </div>
            ) : (
              recentNotifications.map((n) => {
                const isUnread = !n.read_at;
                const isQueried = n.data?.status === "QUERIED";
                const refNo = n.data?.application_number || n.data?.reference_number;
                return (
                  <div
                    key={n.id}
                    onClick={() => handleNotificationClick(n)}
                    className={`flex items-start gap-3 p-3.5 text-left transition hover:bg-slate-50 cursor-pointer ${
                      isUnread ? (isQueried ? "bg-amber-50/50" : "bg-emerald-50/40") : "bg-white"
                    }`}
                  >
                    <div className="pt-1">
                      {isUnread ? (
                        <span
                          className={`block h-2.5 w-2.5 rounded-full ring-2 ${
                            isQueried
                              ? "bg-amber-500 ring-amber-200"
                              : "bg-nis-primary ring-emerald-200"
                          }`}
                        />
                      ) : (
                        <span className="block h-2 w-2 rounded-full bg-slate-300" />
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-2">
                        <p
                          className={`text-xs truncate ${
                            isUnread
                              ? isQueried
                                ? "font-bold text-amber-950"
                                : "font-bold text-slate-900"
                              : "font-medium text-slate-700"
                          }`}
                        >
                          {n.data?.title || (isQueried ? t("Application Requires Your Attention") : t("Application Update"))}
                        </p>
                        <span className="shrink-0 text-[10px] text-slate-400 whitespace-nowrap">
                          {dateTime(n.created_at)}
                        </span>
                      </div>
                      {refNo && (
                        <p className={`text-[11px] font-semibold mt-0.5 ${isQueried ? "text-amber-800" : "text-nis-primary"}`}>
                          Ref: {refNo}
                        </p>
                      )}
                      {isQueried && (n.data?.query_reason || n.data?.notes) ? (
                        <div className="mt-1.5 rounded-md border border-amber-200 bg-amber-50/80 p-2 text-xs text-amber-900">
                          <span className="font-semibold">{t("Query reason")}: </span>
                          <span>{n.data.query_reason || n.data.notes}</span>
                        </div>
                      ) : (
                        n.data?.notes && (
                          <p className="mt-1 text-xs text-slate-600 line-clamp-2">
                            {n.data.notes}
                          </p>
                        )
                      )}
                      <div className="mt-2">
                        {isQueried ? (
                          <span className="inline-flex items-center gap-1 rounded bg-amber-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-xs hover:bg-amber-700 transition">
                            {t("Respond to Query →")}
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-nis-green hover:underline">
                            {t("View Application →")}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer with View All Notifications link */}
          <div className="border-t border-slate-100 bg-slate-50/80 px-4 py-2.5 text-center rounded-b-2xl">
            <Link
              href={portal === "staff" ? "/staff/notifications" : "/portal/notifications"}
              onClick={() => setOpen(false)}
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-nis-primary hover:text-nis-primary-dark transition-colors"
            >
              <span>{t("View All Notifications")}</span>
              <span aria-hidden="true">&rarr;</span>
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
