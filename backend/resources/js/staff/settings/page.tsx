"use client";

import { useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { Alert, Button, Card, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { BroadcastBanner } from "@/components/BroadcastBanner";
import { CountdownBanner } from "@/components/CountdownBanner";
import { useI18n } from "@/components/I18nProvider";

interface SettingsData {
  broadcast: {
    is_active: boolean;
    title: string;
    message: string;
    tone: "info" | "warning" | "alert" | "success";
    target: "all" | "public" | "applicants" | "staff";
    expires_at: string | null;
    updated_at: string | null;
  };
  countdown: {
    is_active: boolean;
    title: string;
    target_date: string | null;
    description: string;
    link_label: string;
    link_url: string;
    updated_at: string | null;
  };
  stats: {
    push_subscribers: number;
    total_applicants: number;
    total_staff: number;
  };
}

export default function StaffSettingsPage() {
  const { t } = useI18n();
  const [loading, setLoading] = useState(true);
  const [savingBroadcast, setSavingBroadcast] = useState(false);
  const [savingCountdown, setSavingCountdown] = useState(false);
  const [pushingNotice, setPushingNotice] = useState(false);
  const [feedback, setFeedback] = useState<{ type: "success" | "error"; message: string } | null>(null);

  const [broadcast, setBroadcast] = useState<SettingsData["broadcast"]>({
    is_active: false,
    title: "",
    message: "",
    tone: "info",
    target: "all",
    expires_at: null,
    updated_at: null,
  });

  const [countdown, setCountdown] = useState<SettingsData["countdown"]>({
    is_active: false,
    title: "",
    target_date: null,
    description: "",
    link_label: "",
    link_url: "",
    updated_at: null,
  });

  const [stats, setStats] = useState<SettingsData["stats"]>({
    push_subscribers: 0,
    total_applicants: 0,
    total_staff: 0,
  });

  useEffect(() => {
    loadSettings();
  }, []);

  async function loadSettings() {
    try {
      setLoading(true);
      const res = await api<SettingsData>("staff", "settings");
      if (res.broadcast) setBroadcast(res.broadcast);
      if (res.countdown) setCountdown(res.countdown);
      if (res.stats) setStats(res.stats);
    } catch (e: any) {
      setFeedback({ type: "error", message: e.message || "Failed to load settings" });
    } finally {
      setLoading(false);
    }
  }

  async function saveBroadcastSettings(e: React.FormEvent) {
    e.preventDefault();
    setSavingBroadcast(true);
    setFeedback(null);
    try {
      const res = await api<SettingsData>("staff", "settings", {
        method: "POST",
        body: JSON.stringify({ broadcast }),
      });
      setBroadcast(res.broadcast);
      setFeedback({ type: "success", message: "Broadcast notification settings saved successfully." });
    } catch (e: any) {
      setFeedback({ type: "error", message: e.message || "Failed to save broadcast settings." });
    } finally {
      setSavingBroadcast(false);
    }
  }

  async function saveCountdownSettings(e: React.FormEvent) {
    e.preventDefault();
    setSavingCountdown(true);
    setFeedback(null);
    try {
      const res = await api<SettingsData>("staff", "settings", {
        method: "POST",
        body: JSON.stringify({ countdown }),
      });
      setCountdown(res.countdown);
      setFeedback({ type: "success", message: "Countdown timer settings saved successfully." });
    } catch (e: any) {
      setFeedback({ type: "error", message: e.message || "Failed to save countdown settings." });
    } finally {
      setSavingCountdown(false);
    }
  }

  async function handlePushNow() {
    if (!broadcast.title || !broadcast.message) {
      setFeedback({ type: "error", message: "Please enter both a title and a message before pushing the notification." });
      return;
    }

    if (!confirm(`Are you sure you want to push this notification to all targeted users (${broadcast.target})?`)) {
      return;
    }

    setPushingNotice(true);
    setFeedback(null);
    try {
      const res = await api<{ success: boolean; recipients_count: number; message: string }>(
        "staff",
        "settings/push-broadcast",
        {
          method: "POST",
          body: JSON.stringify({
            title: broadcast.title,
            message: broadcast.message,
            tone: broadcast.tone,
            target: broadcast.target === "public" ? "all" : broadcast.target,
          }),
        }
      );
      setFeedback({
        type: "success",
        message: `Notification successfully pushed to ${res.recipients_count} recipient(s).`,
      });
    } catch (e: any) {
      setFeedback({ type: "error", message: e.message || "Failed to dispatch push notification." });
    } finally {
      setPushingNotice(false);
    }
  }

  function setPresetDate(hoursFromNow: number) {
    const d = new Date(Date.now() + hoursFromNow * 60 * 60 * 1000);
    // Format to YYYY-MM-DDTHH:mm for datetime-local input
    const pad = (n: number) => String(n).padStart(2, "0");
    const formatted = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    setCountdown((prev) => ({ ...prev, target_date: formatted }));
  }

  if (loading) return <Spinner />;

  return (
    <div className="space-y-8">
      <PageTitle
        eyebrow={t("SuperAdmin Console")}
        title={t("Settings & System Announcements")}
        subtitle={t("Broadcast push notifications to users, configure public banners, and manage countdown timers.")}
      />

      {feedback && (
        <Alert tone={feedback.type === "success" ? "success" : "danger"}>
          {feedback.message}
        </Alert>
      )}

      {/* Overview Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-nis-mint text-nis-primary">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </div>
            <div>
              <div className="text-2xl font-bold text-slate-900">{stats.push_subscribers}</div>
              <div className="text-xs text-slate-500">{t("Push Subscribers (Devices)")}</div>
            </div>
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <div>
              <div className="text-2xl font-bold text-slate-900">{stats.total_applicants}</div>
              <div className="text-xs text-slate-500">{t("Registered Applicants")}</div>
            </div>
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <div>
              <div className="text-2xl font-bold text-slate-900">{stats.total_staff}</div>
              <div className="text-xs text-slate-500">{t("Active Officers / Staff")}</div>
            </div>
          </div>
        </div>
      </div>

      {/* SECTION 1: Broadcast & Push Notifications */}
      <Panel title={t("Push Notifications & Broadcast Announcements")}>
        <form onSubmit={saveBroadcastSettings} className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <label htmlFor="broadcast-toggle" className="text-sm font-semibold text-slate-900">
                {t("Active Announcement Banner")}
              </label>
              <p className="text-xs text-slate-500">
                {t("When enabled, this announcement appears at the top of portal and public pages.")}
              </p>
            </div>
            <label className="relative inline-flex cursor-pointer items-center">
              <input
                id="broadcast-toggle"
                type="checkbox"
                checked={broadcast.is_active}
                onChange={(e) => setBroadcast({ ...broadcast, is_active: e.target.checked })}
                className="peer sr-only"
              />
              <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-nis-primary peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
            </label>
          </div>

          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                {t("Announcement Title")} <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                required
                maxLength={150}
                value={broadcast.title}
                onChange={(e) => setBroadcast({ ...broadcast, title: e.target.value })}
                placeholder={t("e.g. Scheduled System Maintenance")}
                className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                  {t("Tone / Urgency")}
                </label>
                <select
                  value={broadcast.tone}
                  onChange={(e) => setBroadcast({ ...broadcast, tone: e.target.value as any })}
                  className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary"
                >
                  <option value="info">{t("Information (Blue)")}</option>
                  <option value="warning">{t("Warning (Orange)")}</option>
                  <option value="alert">{t("Critical Alert (Red)")}</option>
                  <option value="success">{t("Success / Update (Green)")}</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                  {t("Target Audience")}
                </label>
                <select
                  value={broadcast.target}
                  onChange={(e) => setBroadcast({ ...broadcast, target: e.target.value as any })}
                  className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary"
                >
                  <option value="all">{t("All Visitors & Users")}</option>
                  <option value="public">{t("Public Pages Only")}</option>
                  <option value="applicants">{t("Applicants Only")}</option>
                  <option value="staff">{t("Officers & Staff Only")}</option>
                </select>
              </div>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              {t("Notification Message")} <span className="text-rose-500">*</span>
            </label>
            <textarea
              required
              rows={3}
              maxLength={1000}
              value={broadcast.message}
              onChange={(e) => setBroadcast({ ...broadcast, message: e.target.value })}
              placeholder={t("Enter the notification text to show to users...")}
              className="mt-1.5 w-full rounded-lg border border-slate-300 p-3 text-sm text-slate-900 focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary"
            />
          </div>

          {/* Live Preview */}
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("Live Preview of Banner")}
            </span>
            <div className="mt-2 overflow-hidden rounded-xl border border-slate-200">
              <BroadcastBanner
                preview
                isActive={true}
                title={broadcast.title || t("Sample Announcement Title")}
                message={broadcast.message || t("Sample notification message showing how text will render to visitors.")}
                tone={broadcast.tone}
              />
            </div>
          </div>

          {/* Action buttons */}
          <div className="flex flex-wrap items-center justify-between gap-4 pt-2">
            <Button type="submit" disabled={savingBroadcast}>
              {savingBroadcast ? t("Saving...") : t("Save Announcement Settings")}
            </Button>

            {/* Instant Push Trigger */}
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={handlePushNow}
                disabled={pushingNotice || !broadcast.title || !broadcast.message}
                className="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-700 disabled:opacity-50"
              >
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                  <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                {pushingNotice ? t("Pushing...") : t("Push Notification to Users Now")}
              </button>
            </div>
          </div>
        </form>
      </Panel>

      {/* SECTION 2: Countdown Timer */}
      <Panel title={t("Portal Countdown Timer")}>
        <form onSubmit={saveCountdownSettings} className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <label htmlFor="countdown-toggle" className="text-sm font-semibold text-slate-900">
                {t("Enable Countdown Timer")}
              </label>
              <p className="text-xs text-slate-500">
                {t("Displays a prominent ticking countdown banner at the top of the portal.")}
              </p>
            </div>
            <label className="relative inline-flex cursor-pointer items-center">
              <input
                id="countdown-toggle"
                type="checkbox"
                checked={countdown.is_active}
                onChange={(e) => setCountdown({ ...countdown, is_active: e.target.checked })}
                className="peer sr-only"
              />
              <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-amber-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
            </label>
          </div>

          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                {t("Countdown Title / Event")} <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                required
                maxLength={150}
                value={countdown.title}
                onChange={(e) => setCountdown({ ...countdown, title: e.target.value })}
                placeholder={t("e.g. Scheduled System Maintenance")}
                className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
              />
            </div>

            <div>
              <div className="flex items-center justify-between">
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                  {t("Target Date & Time")} <span className="text-rose-500">*</span>
                </label>
                <div className="flex items-center gap-1.5 text-[11px] text-slate-500">
                  <span>{t("Presets:")}</span>
                  <button type="button" onClick={() => setPresetDate(1)} className="rounded px-1.5 py-0.5 hover:bg-slate-100 font-medium text-amber-700">+1h</button>
                  <button type="button" onClick={() => setPresetDate(6)} className="rounded px-1.5 py-0.5 hover:bg-slate-100 font-medium text-amber-700">+6h</button>
                  <button type="button" onClick={() => setPresetDate(24)} className="rounded px-1.5 py-0.5 hover:bg-slate-100 font-medium text-amber-700">+24h</button>
                  <button type="button" onClick={() => setPresetDate(72)} className="rounded px-1.5 py-0.5 hover:bg-slate-100 font-medium text-amber-700">+3d</button>
                  <button type="button" onClick={() => setPresetDate(168)} className="rounded px-1.5 py-0.5 hover:bg-slate-100 font-medium text-amber-700">+7d</button>
                </div>
              </div>
              <input
                type="datetime-local"
                required
                value={countdown.target_date || ""}
                onChange={(e) => setCountdown({ ...countdown, target_date: e.target.value })}
                className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
              {t("Description / Instructions")}
            </label>
            <textarea
              rows={2}
              maxLength={500}
              value={countdown.description || ""}
              onChange={(e) => setCountdown({ ...countdown, description: e.target.value })}
              placeholder={t("e.g. Online applications and biometric scheduling will be briefly suspended during this maintenance window.")}
              className="mt-1.5 w-full rounded-lg border border-slate-300 p-3 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                {t("Action Link Label (Optional)")}
              </label>
              <input
                type="text"
                maxLength={100}
                value={countdown.link_label || ""}
                onChange={(e) => setCountdown({ ...countdown, link_label: e.target.value })}
                placeholder={t("e.g. Read Maintenance FAQ")}
                className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                {t("Action Link URL (Optional)")}
              </label>
              <input
                type="text"
                maxLength={255}
                value={countdown.link_url || ""}
                onChange={(e) => setCountdown({ ...countdown, link_url: e.target.value })}
                placeholder={t("e.g. /faq or https://...")}
                className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
              />
            </div>
          </div>

          {/* Live Preview */}
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("Live Preview of Countdown")}
            </span>
            <div className="mt-2 overflow-hidden rounded-xl border border-slate-200">
              <CountdownBanner
                preview
                isActive={true}
                title={countdown.title || t("Scheduled System Maintenance")}
                targetDate={countdown.target_date || new Date(Date.now() + 86400000).toISOString()}
                description={countdown.description || t("Countdown timer actively updates second by second.")}
                linkLabel={countdown.link_label || t("Learn more")}
                linkUrl={countdown.link_url || "#"}
              />
            </div>
          </div>

          <div className="pt-2">
            <Button type="submit" disabled={savingCountdown} className="!bg-amber-600 hover:!bg-amber-700">
              {savingCountdown ? t("Saving...") : t("Save Countdown Settings")}
            </Button>
          </div>
        </form>
      </Panel>

      {/* SECTION 3: Cookie & Privacy Policy Overview */}
      <Panel title={t("Cookie Consent & Privacy Policy Status")}>
        <div className="space-y-4 text-sm text-slate-600">
          <div className="flex items-start gap-3 rounded-lg bg-slate-50 p-4 border border-slate-200">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-nis-mint text-nis-primary">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
            </div>
            <div>
              <h4 className="font-semibold text-slate-900">{t("Active Cookie Consent Banner")}</h4>
              <p className="mt-1 text-xs sm:text-sm text-slate-600">
                {t(
                  "The 'Cookie Consent Notice' is automatically presented to all visitors across the portal. It informs users about essential session cookies, local storage authentication tokens, and strict compliance with the Nigeria Data Protection Act (NDPA)."
                )}
              </p>
            </div>
          </div>
        </div>
      </Panel>
    </div>
  );
}
