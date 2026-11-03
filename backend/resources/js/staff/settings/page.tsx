"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { PageTitle } from "@/components/PageTitle";
import { Alert, Button, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { BroadcastBanner } from "@/components/BroadcastBanner";
import { useI18n } from "@/components/I18nProvider";

type SettingsTab =
  | "general"
  | "users"
  | "zones"
  | "security"
  | "notifications"
  | "workflow"
  | "audit";

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
  stats: {
    push_subscribers: number;
    total_applicants: number;
    total_staff: number;
  };
  general?: {
    portal_name: string;
    organization: string;
    directorate: string;
    authority: string;
    timezone: string;
    environment: string;
  };
  workflow?: {
    card_validity_years: number;
    temporary_card_validity_days: number;
    sla_working_days: number;
    fee_naira: number;
    cod_fee_naira: number;
  };
  security?: {
    two_factor_required: boolean;
    geofencing_enabled: boolean;
    geofencing_strict: boolean;
  };
  notifications?: {
    alert_emails: string[];
  };
  user_can_edit?: boolean;
}

export default function StaffSettingsPage() {
  const { t } = useI18n();
  const [activeTab, setActiveTab] = useState<SettingsTab>("general");
  const [loading, setLoading] = useState(true);
  const [savingBroadcast, setSavingBroadcast] = useState(false);
  const [pushingNotice, setPushingNotice] = useState(false);
  const [feedback, setFeedback] = useState<{ type: "success" | "error"; message: string } | null>(null);

  const [settings, setSettings] = useState<SettingsData | null>(null);

  const [broadcast, setBroadcast] = useState<SettingsData["broadcast"]>({
    is_active: false,
    title: "",
    message: "",
    tone: "info",
    target: "all",
    expires_at: null,
    updated_at: null,
  });

  useEffect(() => {
    loadSettings();
  }, []);

  async function loadSettings() {
    try {
      setLoading(true);
      const res = await api<SettingsData>("staff", "settings");
      setSettings(res);
      if (res.broadcast) setBroadcast(res.broadcast);
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
      setSettings((prev) => (prev ? { ...prev, broadcast: res.broadcast } : prev));
      setFeedback({ type: "success", message: "Broadcast notification settings saved successfully." });
    } catch (e: any) {
      setFeedback({ type: "error", message: e.message || "Failed to save broadcast settings." });
    } finally {
      setSavingBroadcast(false);
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

  if (loading) return <Spinner />;

  const canEdit = settings?.user_can_edit ?? true;

  const tabs: { id: SettingsTab; label: string; icon: string }[] = [
    { id: "general", label: t("General Settings"), icon: "🌐" },
    { id: "users", label: t("User & Role Management"), icon: "👥" },
    { id: "zones", label: t("Zones & Commands"), icon: "🏛️" },
    { id: "security", label: t("Security & Access Control"), icon: "🛡️" },
    { id: "notifications", label: t("Notifications"), icon: "🔔" },
    { id: "workflow", label: t("Application & Workflow"), icon: "📋" },
    { id: "audit", label: t("Audit & Activity Logs"), icon: "📜" },
  ];

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("NIS Administrative Portal")}
        title={t("Settings & System Administration")}
        subtitle={t("Manage official portal configuration, security access policies, operational workflow SLAs, and public announcements.")}
      />

      {feedback && (
        <Alert tone={feedback.type === "success" ? "success" : "danger"}>
          {feedback.message}
        </Alert>
      )}

      {/* Top Statistical Overview */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-800">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <div>
              <div className="text-xl font-bold text-slate-900">{settings?.stats.total_applicants ?? 0}</div>
              <div className="text-xs text-slate-500">{t("Registered Applicants")}</div>
            </div>
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-800">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <div>
              <div className="text-xl font-bold text-slate-900">{settings?.stats.total_staff ?? 0}</div>
              <div className="text-xs text-slate-500">{t("Active Officers / Staff")}</div>
            </div>
          </div>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-800">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </div>
            <div>
              <div className="text-xl font-bold text-slate-900">{settings?.stats.push_subscribers ?? 0}</div>
              <div className="text-xs text-slate-500">{t("Push Subscribers (Devices)")}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="border-b border-slate-200 bg-white px-2 pt-2 rounded-t-xl">
        <nav className="flex flex-wrap gap-1 sm:gap-2" aria-label="Settings navigation">
          {tabs.map((tab) => {
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => {
                  setActiveTab(tab.id);
                  setFeedback(null);
                }}
                className={`inline-flex items-center gap-2 border-b-2 px-3 py-2.5 text-xs sm:text-sm font-semibold transition-colors ${
                  isActive
                    ? "border-emerald-700 text-emerald-800 bg-emerald-50/50 rounded-t-lg"
                    : "border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900"
                }`}
              >
                <span>{tab.icon}</span>
                <span>{tab.label}</span>
              </button>
            );
          })}
        </nav>
      </div>

      {/* TAB 1: General Settings */}
      {activeTab === "general" && (
        <Panel title={t("Portal Identification & Branding")}>
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Basic system identity, issuing authority attributes, and jurisdictional branding under the Nigeria Immigration Service.")}
            </p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Portal Official Name")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900">
                  {settings?.general?.portal_name || "Nigeria Immigration Service - ECOWAS Residence Card Portal"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Issuing State Authority")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900">
                  {settings?.general?.issuing_country || "FEDERAL REPUBLIC OF NIGERIA"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Lead Directorate")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900">
                  {settings?.general?.directorate || "Directorate of Visa and Residency"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Approving Statutory Authority")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900">
                  {settings?.general?.authority || "COMPTROLLER GENERAL OF IMMIGRATION"}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("System Timezone")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900 font-mono">
                  {settings?.general?.timezone || "Africa/Lagos"} (WAT, UTC+1)
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Official Language Support")}</div>
                <div className="mt-1 text-sm font-semibold text-slate-900">
                  {t("English (Primary) & French (ECOWAS Bilateral)")}
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 text-xs text-emerald-900">
              <span className="flex items-center gap-2">
                <span>🛡️</span>
                <span>{t("Official branding and issuing authority tokens are cryptographically grounded in national executive mandates.")}</span>
              </span>
              <span className="rounded bg-emerald-700 px-2 py-0.5 font-semibold text-white">
                {settings?.general?.environment || "ACTIVE"}
              </span>
            </div>
          </div>
        </Panel>
      )}

      {/* TAB 2: User & Role Management */}
      {activeTab === "users" && (
        <Panel
          title={t("Staff Accounts, Roles & Permissions")}
          actions={
            <Link
              href="/staff/users"
              className="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800"
            >
              <span>{t("Open Staff Accounts & Jurisdictions")}</span>
              <span>→</span>
            </Link>
          }
        >
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Administrative overview of authorized personnel, role-based access control (RBAC), and jurisdictional scoping across Headquarters, Zones, and Commands.")}
            </p>

            <div className="rounded-lg border border-slate-200 bg-white overflow-hidden">
              <table className="w-full text-left text-xs sm:text-sm">
                <thead className="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                  <tr>
                    <th className="px-4 py-2.5">{t("Tier / Functional Cluster")}</th>
                    <th className="px-4 py-2.5">{t("Official Staff Roles")}</th>
                    <th className="px-4 py-2.5">{t("Jurisdictional Scope")}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Executive Leadership")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Super Administrator, Headquarters Administrator")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("National Purview / All Zones & Commands")}</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Regional Administration")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Zonal Administrator, Command Administrator")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("Assigned Zone (8) or State Command (37)")}</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Application Processing")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Vetting Officer, Approving Officer")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("Document verification, query resolution, approvals")}</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Biometrics & Card Production")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Biometrics Officer, Card Production Officer")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("Enrollment desks, centralized Fargo card printing")}</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Distribution & Issuance")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Card Distribution Officer, Receiving Officer, Issuance Officer")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("Batch dispatch, intake verification, handover to applicant")}</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-3 font-semibold text-slate-900">{t("Oversight & Compliance")}</td>
                    <td className="px-4 py-3 text-slate-700">{t("Auditor, System Inspector")}</td>
                    <td className="px-4 py-3 text-slate-500">{t("Read-only audit trail and integrity monitoring")}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50/80 p-3 text-xs text-blue-900">
              <span>{t("To add new officers, reset 2FA, update ranks, or modify Command allocations, use the dedicated Staff Accounts module.")}</span>
              <Link href="/staff/users" className="font-semibold underline hover:text-blue-950">
                {t("Go to Staff Accounts")}
              </Link>
            </div>
          </div>
        </Panel>
      )}

      {/* TAB 3: Zones & Commands */}
      {activeTab === "zones" && (
        <Panel
          title={t("NIS Organisational Structure (Zones & Commands)")}
          actions={
            <Link
              href="/staff/users"
              className="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800"
            >
              <span>{t("Manage Jurisdictions")}</span>
              <span>→</span>
            </Link>
          }
        >
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Authorised hierarchy governing application processing, card distribution dispatches, and enrollment centre management.")}
            </p>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-2xl font-bold text-slate-900">1</div>
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700 mt-1">{t("National Headquarters")}</div>
                <div className="text-xs text-slate-500 mt-0.5">{t("Sauka, Abuja (CGO & National Production)")}</div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-2xl font-bold text-slate-900">8</div>
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700 mt-1">{t("NIS Zonal Directorates")}</div>
                <div className="text-xs text-slate-500 mt-0.5">{t("Zone A (Lagos) through Zone H (Makurdi)")}</div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-2xl font-bold text-slate-900">37</div>
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700 mt-1">{t("State & FCT Commands")}</div>
                <div className="text-xs text-slate-500 mt-0.5">{t("Authorised biometric and card collection centers")}</div>
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">{t("Operational Purview Rules")}</h4>
              <ul className="space-y-2 text-xs text-slate-600 list-disc list-inside">
                <li><strong className="text-slate-900">{t("Headquarters (Abuja):")}</strong> {t("Centralized review, card printing batch queues, and national card register oversight.")}</li>
                <li><strong className="text-slate-900">{t("Zonal Directorates:")}</strong> {t("Cross-state command supervision, intermediate transit hub dispatching, and regional compliance.")}</li>
                <li><strong className="text-slate-900">{t("State Commands:")}</strong> {t("Physical applicant verification, live biometric capture, card receipt confirmation, and secure card issuance.")}</li>
              </ul>
            </div>
          </div>
        </Panel>
      )}

      {/* TAB 4: Security & Access Control */}
      {activeTab === "security" && (
        <Panel title={t("Authentication Policies & Geofencing Boundaries")}>
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Multi-layered security controls enforcing two-factor authentication, NIS Number identification, and GPS boundary verification.")}
            </p>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="rounded-lg border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold uppercase tracking-wider text-slate-800">{t("Two-Factor Authentication (2FA)")}</div>
                  <span className="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">{t("MANDATORY")}</span>
                </div>
                <p className="mt-2 text-xs text-slate-600">
                  {t("Every staff account requires RFC 6238 TOTP verification (Google Authenticator or Microsoft Authenticator) upon signing in.")}
                </p>
                <div className="mt-3 text-[11px] text-slate-500">
                  {t("QR code is rendered once during first-time login and can be reset by a Super Administrator if a device is misplaced.")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold uppercase tracking-wider text-slate-800">{t("Login Identifier Policy")}</div>
                  <span className="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">{t("STRICT NIS NUMBER")}</span>
                </div>
                <p className="mt-2 text-xs text-slate-600">
                  {t("Officers must sign in strictly with their official 5-digit NIS Number (e.g. NIS-10001 or 10001). Username login is disabled.")}
                </p>
                <div className="mt-3 text-[11px] text-slate-500">
                  {t("Ensures auditability and exact attribution of all decisions to the officer's official service record.")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold uppercase tracking-wider text-slate-800">{t("Office Geofencing Verification")}</div>
                  <span className="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-800">{t("ACTIVE (100m Radius)")}</span>
                </div>
                <p className="mt-2 text-xs text-slate-600">
                  {t("Validates that sensitive operations (biometric enrollment, batch card receipt, and card issuance) occur within approved NIS Command perimeter coordinates.")}
                </p>
                <div className="mt-3 text-[11px] text-slate-500">
                  {t("Prevents offsite issuance or unauthorized credential generation outside official premises.")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold uppercase tracking-wider text-slate-800">{t("Session Security & Inactivity")}</div>
                  <span className="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">{t("ENFORCED")}</span>
                </div>
                <p className="mt-2 text-xs text-slate-600">
                  {t("Single active session per officer account with automatic sign-out after inactivity, protected by CSRF tokens and HTTP-only session cookies.")}
                </p>
                <div className="mt-3 text-[11px] text-slate-500">
                  {t("Compliant with Nigeria Data Protection Act (NDPA) and federal security benchmarks.")}
                </div>
              </div>
            </div>
          </div>
        </Panel>
      )}

      {/* TAB 5: Notifications & Announcements */}
      {activeTab === "notifications" && (
        <Panel title={t("Public Announcements & Broadcast Notifications")}>
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
                  disabled={!canEdit}
                  checked={broadcast.is_active}
                  onChange={(e) => setBroadcast({ ...broadcast, is_active: e.target.checked })}
                  className="peer sr-only"
                />
                <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-emerald-700 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
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
                  disabled={!canEdit}
                  maxLength={150}
                  value={broadcast.title}
                  onChange={(e) => setBroadcast({ ...broadcast, title: e.target.value })}
                  placeholder={t("e.g. Scheduled System Maintenance")}
                  className="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700 disabled:bg-slate-100"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    {t("Tone / Urgency")}
                  </label>
                  <select
                    disabled={!canEdit}
                    value={broadcast.tone}
                    onChange={(e) => setBroadcast({ ...broadcast, tone: e.target.value as any })}
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700 disabled:bg-slate-100"
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
                    disabled={!canEdit}
                    value={broadcast.target}
                    onChange={(e) => setBroadcast({ ...broadcast, target: e.target.value as any })}
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700 disabled:bg-slate-100"
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
                disabled={!canEdit}
                rows={3}
                maxLength={1000}
                value={broadcast.message}
                onChange={(e) => setBroadcast({ ...broadcast, message: e.target.value })}
                placeholder={t("Enter the notification text to show to users...")}
                className="mt-1.5 w-full rounded-lg border border-slate-300 p-3 text-sm text-slate-900 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700 disabled:bg-slate-100"
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
            {canEdit ? (
              <div className="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-slate-100">
                <Button type="submit" disabled={savingBroadcast} className="!bg-emerald-800 hover:!bg-emerald-900">
                  {savingBroadcast ? t("Saving...") : t("Save Announcement Settings")}
                </Button>

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
            ) : (
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                ℹ️ {t("Viewing in Read-Only Policy Mode. Administrative permissions required to modify broadcast banners.")}
              </div>
            )}
          </form>
        </Panel>
      )}

      {/* TAB 6: Application & Workflow Settings */}
      {activeTab === "workflow" && (
        <Panel title={t("Application Processing & Card Validity Policies")}>
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Official statutory parameters governing ECOWAS Residence Card validity, temporary credentials, and Service-Level Agreements (SLAs).")}
            </p>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Main Residence Card Validity")}</div>
                <div className="mt-1 text-2xl font-bold text-slate-900">
                  {settings?.workflow?.card_validity_years || 2} {t("Years")}
                </div>
                <div className="mt-1 text-xs text-slate-500">
                  {t("Standard 2-year validity period for approved ECOWAS citizens resident in Nigeria.")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Temporary Residence Card Validity")}</div>
                <div className="mt-1 text-2xl font-bold text-emerald-800">
                  {settings?.workflow?.temporary_card_validity_days || 30} {t("Calendar Days")}
                </div>
                <div className="mt-1 text-xs text-slate-500">
                  {t("Strictly 30 calendar days from confirmed biometrics date (Africa/Lagos timezone).")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Service-Level Target (SLA)")}</div>
                <div className="mt-1 text-2xl font-bold text-slate-900">
                  {settings?.workflow?.sla_working_days || 10} {t("Working Days")}
                </div>
                <div className="mt-1 text-xs text-slate-500">
                  {t("Applications pending beyond 10 working days are highlighted in the queue for expedited action.")}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{t("Statutory Processing Fees")}</div>
                <div className="mt-1 text-2xl font-bold text-slate-900">
                  ₦{(settings?.workflow?.fee_naira || 35000).toLocaleString()}
                </div>
                <div className="mt-1 text-xs text-slate-500">
                  {t("Non-refundable card fee (Change of Data: ₦15,000).")}
                </div>
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">{t("Approved Processing Sequence")}</h4>
              <div className="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-700">
                <span className="rounded bg-slate-100 px-2.5 py-1">1. {t("Submitted")}</span>
                <span>→</span>
                <span className="rounded bg-slate-100 px-2.5 py-1">2. {t("Vetting")}</span>
                <span>→</span>
                <span className="rounded bg-slate-100 px-2.5 py-1">3. {t("Biometrics")}</span>
                <span>→</span>
                <span className="rounded bg-slate-100 px-2.5 py-1">4. {t("Approval")}</span>
                <span>→</span>
                <span className="rounded bg-slate-100 px-2.5 py-1">5. {t("Card Production")}</span>
                <span>→</span>
                <span className="rounded bg-slate-100 px-2.5 py-1">6. {t("Card Distribution")}</span>
                <span>→</span>
                <span className="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1">7. {t("Card Issuance")}</span>
              </div>
            </div>
          </div>
        </Panel>
      )}

      {/* TAB 7: Audit & Activity Logs */}
      {activeTab === "audit" && (
        <Panel
          title={t("System Activity Records & Audit Trail")}
          actions={
            <Link
              href="/staff/audit-logs"
              className="inline-flex items-center gap-1 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800"
            >
              <span>{t("View Full Audit Trail")}</span>
              <span>→</span>
            </Link>
          }
        >
          <div className="space-y-4">
            <p className="text-xs text-slate-500">
              {t("Authorised tamper-evident log records capturing every staff sign-in, vetting determination, biometric scan, card production batch, and distribution handover.")}
            </p>

            <div className="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
              <div className="flex items-center gap-3">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-800 text-lg">
                  🛡️
                </span>
                <div>
                  <h4 className="text-sm font-semibold text-slate-900">{t("Tamper-Evident System Audit Trail")}</h4>
                  <p className="text-xs text-slate-500">{t("All events are timestamped and attributed with officer NIS numbers and IP addresses.")}</p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 pt-2">
                <div className="rounded-lg border border-slate-100 bg-slate-50/75 p-3 text-xs">
                  <div className="font-semibold text-slate-800">{t("Authentication & Access Logs")}</div>
                  <div className="text-slate-500 mt-1">{t("Sign-ins, 2FA verifications, failed attempts, and password resets.")}</div>
                </div>
                <div className="rounded-lg border border-slate-100 bg-slate-50/75 p-3 text-xs">
                  <div className="font-semibold text-slate-800">{t("Operational Decisions")}</div>
                  <div className="text-slate-500 mt-1">{t("Vetting queries, biometric captures, approvals, rejections, and revocations.")}</div>
                </div>
                <div className="rounded-lg border border-slate-100 bg-slate-50/75 p-3 text-xs">
                  <div className="font-semibold text-slate-800">{t("Card Lifecycle & Custody Handover")}</div>
                  <div className="text-slate-500 mt-1">{t("Print queue batches, waybill dispatch manifests, and applicant collection receipts.")}</div>
                </div>
                <div className="rounded-lg border border-slate-100 bg-slate-50/75 p-3 text-xs">
                  <div className="font-semibold text-slate-800">{t("Compliance & Regulatory Standards")}</div>
                  <div className="text-slate-500 mt-1">{t("Compliant with Nigeria Data Protection Act (NDPA 2023) 72-hour breach standard.")}</div>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 text-xs text-emerald-900">
              <span>{t("Authorised auditors and administrators can inspect the live searchable audit log at any time.")}</span>
              <Link href="/staff/audit-logs" className="font-semibold underline hover:text-emerald-950">
                {t("Open Audit Logs")}
              </Link>
            </div>
          </div>
        </Panel>
      )}
    </div>
  );
}
