"use client";

import { useStaff } from "@/components/StaffShell";
import { PageTitle } from "@/components/PageTitle";
import { Panel } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";

export default function StaffProfilePage() {
  const me = useStaff();
  const { t } = useI18n();

  const formattedName = me.rank ? `${me.rank} ${me.fullname}` : me.fullname;
  const roleDisplay = me.role_label || (me.role === "CardIssuanceOfficer" ? "Issuance Officer" : me.role);

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("My Profile")}
        subtitle={t("Official NIS personnel credentials and operational posting")}
      />

      {/* Official NIS Staff Profile Header Banner */}
      <div className="rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-emerald-50/50 p-6 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex items-center gap-4">
            <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-nis-primary font-bold text-xl text-white shadow-sm">
              {me.fullname
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((p) => p[0]?.toUpperCase())
                .join("")}
            </span>
            <div>
              {/* Display: Rank + Full Name on top line */}
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                {formattedName}
              </h1>
              {/* Display: Assigned Role underneath */}
              <div className="mt-1 flex items-center gap-2">
                <span className="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                  {roleDisplay}
                </span>
                <span className="text-xs text-slate-500">•</span>
                <span className="text-xs font-medium text-slate-600">
                  {me.headquarters_unit
                    ? `HQ Sauka: ${me.headquarters_unit}`
                    : me.command_name || me.command}
                </span>
              </div>
            </div>
          </div>

          <div className="flex sm:flex-col items-start sm:items-end justify-between border-t border-slate-200/80 pt-3 sm:border-0 sm:pt-0">
            <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
              {t("Account Status")}
            </div>
            <div className="mt-0.5 inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
              <span className="h-2 w-2 rounded-full bg-emerald-500" />
              {t("Active / Authorized")}
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Personnel & Jurisdiction Details */}
        <Panel title={t("Official Posting & Particulars")}>
          <dl className="divide-y divide-slate-100 text-sm">
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Official Rank")}</dt>
              <dd className="font-semibold text-slate-900">{me.rank || t("— (Civilian / Unassigned)")}</dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Full Legal Name")}</dt>
              <dd className="font-semibold text-slate-900">{me.fullname}</dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Assigned Role")}</dt>
              <dd className="font-semibold text-nis-primary">{roleDisplay}</dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("NIS Number")}</dt>
              <dd className="font-mono font-bold text-slate-900">{me.service_number || "—"}</dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Official Email")}</dt>
              <dd className="font-mono text-slate-800">{me.email}</dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Assigned Command")}</dt>
              <dd className="font-medium text-slate-900">{me.command_name || me.command}</dd>
            </div>
            {me.zone_name && (
              <div className="flex justify-between py-3">
                <dt className="text-slate-500 font-medium">{t("NIS Zone")}</dt>
                <dd className="font-medium text-slate-900">{me.zone_name}</dd>
              </div>
            )}
          </dl>
        </Panel>

        {/* Security, Compliance & Geofencing */}
        <Panel title={t("Security Policy & Access Restrictions")}>
          <dl className="divide-y divide-slate-100 text-sm">
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Two-Factor Authentication (2FA)")}</dt>
              <dd className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/></svg>
                {t("Enforced (Authenticator App)")}
              </dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Physical Office Geofence")}</dt>
              <dd>
                {me.geofence_exempt ? (
                  <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                    {t("Exempt (Supervisory Scope)")}
                  </span>
                ) : (
                  <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    {t("Enforced within Perimeter")}
                  </span>
                )}
              </dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Row-Level Security (RLS)")}</dt>
              <dd className="font-semibold text-emerald-800">
                {me.is_headquarters ? t("National Purview") : t("Jurisdictional Scope")}
              </dd>
            </div>
            <div className="flex justify-between py-3">
              <dt className="text-slate-500 font-medium">{t("Statutory Duty Separation")}</dt>
              <dd className="font-medium text-slate-700">
                {t("Strict RBAC Boundaries Enforced")}
              </dd>
            </div>
          </dl>

          <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
            {t("To request rank adjustment, jurisdictional transfer, or role re-assignment, please contact your NIS Command Administrator or Service Headquarters ICT Directorate.")}
          </div>
        </Panel>
      </div>
    </div>
  );
}
