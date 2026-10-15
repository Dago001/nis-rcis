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
      setNotifications(res.data || []);
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

  // Close on outside click
  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    if (open) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
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
      const targetUrl = portal === "staff"
        ? `/staff/applications/${n.data.application_id}`
        : `/portal/applications/${n.data.application_id}`;
      window.location.href = targetUrl;
    }
  }

  return (
    <div className="relative inline-block" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-label={t("Notifications ({n} unread)", { n: unreadCount })}
        className="relative flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-nis-primary hover:text-nis-primary focus:outline-none focus:ring-2 focus:ring-nis-primary/30"
      >
        {/* Bell SVG */}
        <svg
          className="h-4 w-4 sm:h-5 sm:w-5"
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
          <span className="absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white">
            {unreadCount > 9 ? "9+" : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-80 sm:w-96 rounded-2xl border border-slate-200 bg-white shadow-xl ring-1 ring-black/5">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3 rounded-t-2xl">
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
                className="text-xs font-medium text-nis-primary hover:underline disabled:opacity-50"
              >
                {t("Mark all as read")}
              </button>
            )}
          </div>

          {/* List */}
          <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
            {notifications.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-6 text-center text-slate-500">
                <svg className="h-8 w-8 text-slate-300 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                  <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <p className="text-sm font-medium text-slate-700">{t("No notifications yet")}</p>
                <p className="text-xs text-slate-400 mt-0.5">{t("You will receive updates here as your application is processed.")}</p>
              </div>
            ) : (
              notifications.slice(0, 15).map((n) => {
                const isUnread = !n.read_at;
                return (
                  <div
                    key={n.id}
                    onClick={() => handleNotificationClick(n)}
                    className={`flex items-start gap-3 p-3.5 text-left transition hover:bg-slate-50 cursor-pointer ${
                      isUnread ? "bg-emerald-50/30" : ""
                    }`}
                  >
                    <div className="pt-0.5">
                      {isUnread ? (
                        <span className="block h-2 w-2 rounded-full bg-nis-primary ring-2 ring-emerald-200" />
                      ) : (
                        <span className="block h-2 w-2 rounded-full bg-slate-300" />
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-2">
                        <p className={`text-xs truncate ${isUnread ? "font-bold text-slate-900" : "font-medium text-slate-700"}`}>
                          {n.data?.title || t("Application Update")}
                        </p>
                        <span className="shrink-0 text-[10px] text-slate-400">
                          {dateTime(n.created_at)}
                        </span>
                      </div>
                      {n.data?.application_number && (
                        <p className="text-[11px] font-semibold text-nis-primary mt-0.5">
                          Ref: {n.data.application_number}
                        </p>
                      )}
                      {n.data?.notes && (
                        <p className="mt-1 text-xs text-slate-600 line-clamp-2">
                          {n.data.notes}
                        </p>
                      )}
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer */}
          <div className="border-t border-slate-100 bg-slate-50/50 p-2.5 text-center rounded-b-2xl">
            <Link
              href="/portal"
              onClick={() => setOpen(false)}
              className="text-xs font-semibold text-nis-primary hover:underline"
            >
              {t("View all in dashboard")}
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
