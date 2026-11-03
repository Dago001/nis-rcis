"use client";

import { useCallback, useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { useI18n } from "@/components/I18nProvider";

interface NotificationItem {
  id: string;
  data: {
    title?: string;
    message?: string;
    type?: string;
    application_id?: number;
    application_number?: string;
    action_url?: string;
  };
  read_at: string | null;
  created_at: string;
}

interface NotificationResponse {
  data: NotificationItem[];
  unread: number;
}

export default function StaffNotificationsPage() {
  const me = useStaff();
  const { t } = useI18n();
  const [notifications, setNotifications] = useState<NotificationItem[] | null>(null);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [marking, setMarking] = useState<boolean>(false);

  const loadNotifications = useCallback(() => {
    setLoading(true);
    api<NotificationResponse>("staff", "notifications")
      .then((res) => {
        setNotifications(res.data || []);
        setUnreadCount(res.unread || 0);
        setError(null);
      })
      .catch((err) => {
        setError(err.message || "Failed to load notifications.");
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications]);

  const markAllRead = async () => {
    setMarking(true);
    try {
      await api("staff", "notifications/read", { method: "POST", json: {} });
      setNotifications((prev) =>
        prev ? prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })) : []
      );
      setUnreadCount(0);
    } catch {
      // Ignored
    } finally {
      setMarking(false);
    }
  };

  const markSingleRead = async (id: string) => {
    try {
      await api("staff", "notifications/read", { method: "POST", json: { id } });
      setNotifications((prev) =>
        prev ? prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)) : []
      );
      setUnreadCount((c) => Math.max(0, c - 1));
    } catch {
      // Ignored
    }
  };

  const displayed = (notifications || []).filter((n) => {
    if (filter === "unread") return !n.read_at;
    return true;
  });

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Notifications")}
        subtitle={t("Operational alerts, dispatch notices, and assignment updates")}
        actions={
          unreadCount > 0 ? (
            <Button
              variant="outline"
              onClick={markAllRead}
              disabled={marking}
              className="text-xs font-semibold"
            >
              {marking ? t("Marking…") : t("Mark all as read")}
            </Button>
          ) : undefined
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
          type="button"
          onClick={() => setFilter("all")}
          className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
            filter === "all"
              ? "bg-nis-primary text-white shadow-xs"
              : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          }`}
        >
          {t("All")} ({notifications?.length ?? 0})
        </button>
        <button
          type="button"
          onClick={() => setFilter("unread")}
          className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
            filter === "unread"
              ? "bg-nis-primary text-white shadow-xs"
              : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          }`}
        >
          {t("Unread")} ({unreadCount})
        </button>
      </div>

      <Panel>
        {loading ? (
          <div className="py-12 text-center">
            <Spinner />
          </div>
        ) : displayed.length === 0 ? (
          <div className="py-12 text-center text-sm text-slate-500">
            {filter === "unread" ? t("No unread notifications.") : t("No notifications available.")}
          </div>
        ) : (
          <ul className="divide-y divide-slate-100">
            {displayed.map((item) => {
              const isUnread = !item.read_at;
              return (
                <li
                  key={item.id}
                  className={`flex flex-col gap-2 p-4 transition sm:flex-row sm:items-center sm:justify-between ${
                    isUnread ? "bg-emerald-50/40 hover:bg-emerald-50/70" : "hover:bg-slate-50"
                  }`}
                >
                  <div className="flex items-start gap-3 min-w-0">
                    <span
                      className={`mt-1 flex h-2.5 w-2.5 shrink-0 rounded-full ${
                        isUnread ? "bg-nis-primary ring-2 ring-emerald-200" : "bg-slate-300"
                      }`}
                    />
                    <div className="min-w-0">
                      <div className="text-sm font-semibold text-slate-900">
                        {item.data?.title || t("Operational Notice")}
                      </div>
                      <div className="mt-0.5 text-xs text-slate-600 leading-relaxed">
                        {item.data?.message || JSON.stringify(item.data)}
                      </div>
                      <div className="mt-1 text-[11px] text-slate-400">
                        {dateTime(item.created_at)}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    {isUnread && (
                      <button
                        type="button"
                        onClick={() => markSingleRead(item.id)}
                        className="rounded px-2.5 py-1 text-[11px] font-semibold text-nis-primary hover:bg-emerald-100/60"
                      >
                        {t("Mark as read")}
                      </button>
                    )}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </Panel>
    </div>
  );
}
