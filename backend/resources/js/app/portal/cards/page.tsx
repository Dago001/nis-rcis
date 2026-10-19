"use client";

import Link from "next/link";
import { useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Field, Input, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";
import { useI18n } from "@/components/I18nProvider";

type MyCard = {
  id: number;
  card_number: string;
  holder: string;
  status: string;
  verification_status: string;
  issued_on: string | null;
  expires_on: string | null;
  reported_lost_at: string | null;
  lost_report_type: "LOST" | "STOLEN" | null;
  lost_report_reference?: string | null;
  lost_report_status?: string | null;
  incident_date?: string | null;
  incident_location?: string | null;
  can_report: boolean;
  can_replace: boolean;
};

export default function MyCardsPage() {
  const { data, error, loading, reload } = useFetch<{ data: MyCard[] }>("applicant", "cards");
  const [reporting, setReporting] = useState<MyCard | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const { t } = useI18n();

  return (
    <div className="space-y-6">
      <PageTitle eyebrow={t("Applicant console")} title={t("My ECOWAS residence cards")} subtitle={t("Report a lost or stolen card and apply for a replacement.")} />
      {notice && <Alert tone="success">{notice}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}
      {loading || !data ? (
        <Spinner />
      ) : data.data.length === 0 ? (
        <Panel><p className="text-sm text-slate-600">No residence card has been issued to this account yet.</p></Panel>
      ) : (
        <div className="space-y-4">
          {data.data.map((c) => (
            <Panel key={c.id} title={<>Card {c.card_number}</>} actions={<StatusBadge status={c.verification_status} />}>
              <div className="flex flex-wrap items-start justify-between gap-4 text-sm">
                <dl className="grid gap-x-8 gap-y-2 sm:grid-cols-3">
                  <div><dt className="text-xs uppercase tracking-wide text-slate-500">Holder</dt><dd>{c.holder}</dd></div>
                  <div><dt className="text-xs uppercase tracking-wide text-slate-500">Issued</dt><dd>{nisDate(c.issued_on)}</dd></div>
                  <div><dt className="text-xs uppercase tracking-wide text-slate-500">Expires</dt><dd>{nisDate(c.expires_on)}</dd></div>
                </dl>
                <div className="flex flex-wrap gap-2">
                  {c.can_report && <Button variant="danger" onClick={() => setReporting(c)}>{t("Report lost or stolen")}</Button>}
                  {c.can_replace && (
                    <Link href={`/portal/apply?type=replace&card=${c.card_number}`} className="rounded-md bg-nis-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-nis-primary-dark">
                      {t("Apply for a replacement")}
                    </Link>
                  )}
                </div>
              </div>
              {c.reported_lost_at && (
                <div className="mt-3 rounded-lg border border-red-200 bg-red-50/70 p-3.5 text-sm text-red-900">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-semibold">
                      {c.lost_report_type === "STOLEN" ? t("Reported Stolen") : t("Reported Lost")} · {dateTime(c.reported_lost_at)}
                    </span>
                    {c.lost_report_reference && (
                      <span className="rounded bg-white px-2 py-0.5 text-xs font-mono font-bold text-red-800 border border-red-300">
                        {t("Ref")}: {c.lost_report_reference}
                      </span>
                    )}
                  </div>
                  <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-red-700">
                    <span>
                      {t("Status")}: <span className="font-semibold uppercase">{c.lost_report_status || "SUBMITTED"}</span>
                    </span>
                    {c.incident_location && <span>{t("Location")}: {c.incident_location}</span>}
                    {c.incident_date && <span>{t("Incident Date")}: {nisDate(c.incident_date)}</span>}
                  </div>
                  <p className="mt-1 text-xs text-red-600">
                    {t("Anyone who verifies this card is informed it is invalid. You may apply for a replacement card.")}
                  </p>
                </div>
              )}
            </Panel>
          ))}
        </div>
      )}
      {reporting && (
        <ReportDialog
          card={reporting}
          onClose={() => setReporting(null)}
          onDone={(message) => {
            setReporting(null);
            setNotice(message);
            reload();
          }}
        />
      )}
    </div>
  );
}

function ReportDialog({ card, onClose, onDone }: { card: MyCard; onClose: () => void; onDone: (message: string) => void }) {
  const [type, setType] = useState<"LOST" | "STOLEN">("LOST");
  const [incidentDate, setIncidentDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [incidentLocation, setIncidentLocation] = useState("");
  const [details, setDetails] = useState("");
  const [police, setPolice] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  async function submit() {
    setBusy(true);
    setErrors({});
    try {
      const r = await api<{ message: string }>("applicant", `cards/${card.id}/report-lost`, {
        method: "POST",
        json: {
          type,
          incident_date: incidentDate || null,
          incident_location: incidentLocation.trim() || null,
          details,
          police_report_number: police.trim() || null,
        },
      });
      onDone(r.message);
    } catch (e) {
      setErrors(e instanceof ApiError ? { ...e.fieldErrors(), _: e.message } : { _: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="report-title">
      <div className="w-full max-w-lg space-y-4 rounded-xl bg-white p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
        <h2 id="report-title" className="text-lg font-semibold">Report card {card.card_number}</h2>
        <p className="text-sm text-slate-600">Once reported, the card is immediately shown as invalid to anyone who checks it. A unique tracking reference will be generated for your report.</p>
        {errors._ && <Alert tone="danger">{errors._}</Alert>}
        <Field label="Report Type" required>
          <Select value={type} onChange={(e) => setType(e.target.value as "LOST" | "STOLEN")}>
            <option value="LOST">Card Lost</option>
            <option value="STOLEN">Card Stolen</option>
          </Select>
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Incident Date" required error={errors.incident_date}>
            <Input type="date" value={incidentDate} onChange={(e) => setIncidentDate(e.target.value)} max={new Date().toISOString().slice(0, 10)} />
          </Field>
          <Field label="Location of Incident" error={errors.incident_location} hint="City / State / Area">
            <Input placeholder="e.g. Wuse, Abuja" value={incidentLocation} onChange={(e) => setIncidentLocation(e.target.value)} maxLength={100} />
          </Field>
        </div>
        <Field label="Incident Details / Description" required error={errors.details} hint="Minimum 10 characters describing circumstances of loss or theft.">
          <Textarea value={details} onChange={(e) => setDetails(e.target.value)} maxLength={1000} rows={3} />
        </Field>
        {type === "STOLEN" && (
          <Field label="Police Report (Extract) Number" required error={errors.police_report_number} hint="Official extract reference from Nigerian Police Force">
            <Input value={police} onChange={(e) => setPolice(e.target.value)} maxLength={60} placeholder="e.g. DPO/WUSE/2026/..." />
          </Field>
        )}
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" onClick={onClose} disabled={busy}>Cancel</Button>
          <Button variant="danger" onClick={submit} disabled={busy || details.trim().length < 10}>{busy ? "Submitting Report…" : "Report Card"}</Button>
        </div>
      </div>
    </div>
  );
}
