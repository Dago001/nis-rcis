"use client";

import Link from "next/link";
import { useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Field, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";
import { useI18n } from "@/components/I18nProvider";

type PaymentRecord = {
  reference: string;
  amount_naira: number;
  status: "SUCCESS" | "REFUNDED";
  paid_at: string | null;
  application: { id: number; application_number: string; status: string } | null;
  refundable: boolean;
  refund: { status: string; reason: string; decision_notes: string | null; requested_at: string } | null;
};

export default function PaymentsPage() {
  const { data, error, loading, reload } = useFetch<{ data: PaymentRecord[] }>("applicant", "payment-records");
  const [refunding, setRefunding] = useState<string | null>(null);
  const [reason, setReason] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const { t } = useI18n();

  async function requestRefund(reference: string) {
    setBusy(true);
    setFormError(null);
    try {
      const r = await api<{ message: string }>("applicant", "refunds", { method: "POST", json: { payment_reference: reference, reason } });
      setNotice(r.message);
      setRefunding(null);
      setReason("");
      reload();
    } catch (e) {
      setFormError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : (e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageTitle eyebrow={t("Applicant console")} title={t("Payments and receipts")} subtitle={t("Download official receipts, and ask for a refund of a fee you could not use.")} />
      {notice && <Alert tone="success">{notice}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}
      <Panel title="My payments">
        {loading || !data ? (
          <Spinner />
        ) : data.data.length === 0 ? (
          <p className="text-sm text-slate-600">You have not made any payments yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((p) => (
              <li key={p.reference} className="space-y-3 py-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div className="font-medium">{naira(p.amount_naira)} · <span className="font-mono">{p.reference}</span></div>
                    <div className="text-xs text-slate-500">
                      Paid {dateTime(p.paid_at)} ·{" "}
                      {p.application ? <Link href={`/portal/applications/${p.application.id}`} className="underline">{p.application.application_number}</Link> : "not used for an application"}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    {p.status === "REFUNDED" && <StatusBadge status="REFUNDED" label="Refunded" />}
                    {/* Plain link: a PDF download through the BFF. */}
                    <a href={`/api/bff/applicant/payment-records/${encodeURIComponent(p.reference)}/receipt`} className="rounded-md border border-slate-300 px-3 py-2 font-medium hover:border-nis-primary hover:text-nis-primary">
                      {t("Download receipt (PDF)")}
                    </a>
                    {p.refundable && refunding !== p.reference && (
                      <Button variant="secondary" onClick={() => { setRefunding(p.reference); setFormError(null); }}>{t("Request refund")}</Button>
                    )}
                  </div>
                </div>
                {p.refund && (
                  <p className="rounded-md bg-slate-50 px-3 py-2 text-slate-700">
                    Refund {p.refund.status.toLowerCase()} (requested {dateTime(p.refund.requested_at)}){p.refund.decision_notes ? ` — ${p.refund.decision_notes}` : ""}
                  </p>
                )}
                {refunding === p.reference && (
                  <div className="space-y-3 rounded-lg border border-slate-200 bg-nis-mint/50 p-4">
                    {formError && <Alert tone="danger">{formError}</Alert>}
                    <Field label="Why do you want a refund?" required hint="At least 10 characters. The decision is sent to you by e-mail.">
                      <Textarea value={reason} onChange={(e) => setReason(e.target.value)} maxLength={1000} />
                    </Field>
                    <div className="flex gap-2">
                      <Button onClick={() => requestRefund(p.reference)} disabled={busy || reason.trim().length < 10}>{busy ? "Sending…" : "Send refund request"}</Button>
                      <Button variant="ghost" onClick={() => setRefunding(null)} disabled={busy}>Cancel</Button>
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </Panel>
      <p className="text-xs text-slate-500">
        Refunds are available for a fee that was never used for an application, or whose application was rejected. Approved refunds go back to the card or account you paid from through Paystack.
      </p>
    </div>
  );
}
