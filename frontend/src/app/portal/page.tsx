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

type Me = { forenames: string; surname: string; email: string; has_draft: boolean };

export default function PortalHome() {
  const [me, setMe] = useState<Me | null>(null);
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const { t } = useI18n();

  useEffect(() => {
    api<Me>("applicant", "me").then(setMe);
    api<{ data: Application[] }>("applicant", "applications").then((r) => setApplications(r.data));
    api<{ data: AppNotification[] }>("applicant", "notifications").then((r) => setNotifications(r.data));
  }, []);

  if (!me || !applications) return <Spinner label={t("Loading…")} />;

  // Only the account holder's own application blocks a new one; dependants' applications do not.
  const open = applications.find((a) => !a.dependant_relationship && !["REJECTED", "ISSUED"].includes(a.status));
  const queried = applications.filter((a) => a.status === "QUERIED");

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant console")}
        title={t("Welcome, {name}", { name: me.forenames })}
        subtitle={me.email}
        actions={
          !open && (
            <div className="flex gap-2">
              <Link href="/portal/apply" className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white">
                {me.has_draft ? t("Continue saved application") : t("New application")}
              </Link>
              <Link href="/portal/apply?type=renewal" className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">{t("Renew a card")}</Link>
            </div>
          )
        }
      />

      {queried.map((a) => (
        <Alert key={a.id} tone="warning">
          Application <strong>{a.application_number}</strong> has been queried: {a.decision_notes}{" "}
          <Link href={`/portal/applications/${a.id}`} className="font-semibold underline">{t("Respond now")}</Link>
        </Alert>
      ))}

      <Panel title={t("My applications")}>
        {applications.length === 0 ? (
          <p className="text-sm text-slate-600">{t("You have not submitted any applications yet.")}</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {applications.map((a) => (
              <li key={a.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <Link href={`/portal/applications/${a.id}`} className="font-semibold text-nis-green hover:underline">{a.application_number}</Link>
                  <div className="text-xs text-slate-500">
                    {applicationType(a)} · submitted {nisDate(a.submitted_at)}
                    {a.status === "APPROVED_FOR_BIOMETRICS" && ` · biometrics ${nisDate(a.appointment_date)} ${a.appointment_time}`}
                  </div>
                </div>
                <StatusBadge status={a.status} label={a.status_label} />
              </li>
            ))}
          </ul>
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
        {/* Plain link: a file download through the BFF, not a page navigation. */}
        <a href="/api/bff/applicant/my-data" download="my-nis-rcis-data.json" className="mt-3 inline-block rounded-md border border-slate-300 px-4 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">{t("Download my data")}</a>
      </section>
    </div>
  );
}
