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
      <PageTitle eyebrow={t("Applicant console")} title={t("My residence cards")} subtitle={t("Report a lost or stolen card and apply for a replacement.")} />
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
                <p className="mt-3 text-sm text-red-800">
                  Reported {c.lost_report_type?.toLowerCase()} on {dateTime(c.reported_lost_at)}. Anyone who verifies this card is told it is not valid.
                </p>
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
        json: { type, details, police_report_number: police || null },
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
      <div className="w-full max-w-lg space-y-4 rounded-xl bg-white p-5 shadow-2xl">
        <h2 id="report-title" className="text-lg font-semibold">Report card {card.card_number}</h2>
        <p className="text-sm text-slate-600">Once reported, the card is shown as invalid to anyone who checks it. This cannot be undone online: if you find the card, take it to an NIS office.</p>
        {errors._ && <Alert tone="danger">{errors._}</Alert>}
        <Field label="What happened?" required>
          <Select value={type} onChange={(e) => setType(e.target.value as "LOST" | "STOLEN")}>
            <option value="LOST">The card is lost</option>
            <option value="STOLEN">The card was stolen</option>
          </Select>
        </Field>
        <Field label="Where and when" required error={errors.details} hint="For example: lost at Wuse market on 3 October, around 2 pm.">
          <Textarea value={details} onChange={(e) => setDetails(e.target.value)} maxLength={1000} />
        </Field>
        {type === "STOLEN" && (
          <Field label="Police report (extract) number" required error={errors.police_report_number}>
            <Input value={police} onChange={(e) => setPolice(e.target.value)} maxLength={60} />
          </Field>
        )}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={busy}>Cancel</Button>
          <Button variant="danger" onClick={submit} disabled={busy || details.trim().length < 10}>{busy ? "Reporting…" : "Report card"}</Button>
        </div>
      </div>
    </div>
  );
}
