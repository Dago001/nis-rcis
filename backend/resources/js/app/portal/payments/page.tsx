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
import { withBase } from "@/lib/base-path";

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
  const { data, error, loading } = useFetch<{ data: PaymentRecord[] }>("applicant", "payment-records");
  const { t } = useI18n();

  return (
    <div className="space-y-6">
      <PageTitle eyebrow={t("Applicant console")} title={t("Payments and receipts")} subtitle={t("Download official receipts for your payments.")} />
      {error && <Alert tone="danger">{error}</Alert>}
      <Panel title={t("My payments")}>
        {loading || !data ? (
          <Spinner />
        ) : data.data.length === 0 ? (
          <p className="text-sm text-slate-600">{t("No payment records found.")}</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((p) => (
              <li key={p.reference} className="space-y-3 py-4 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div className="font-medium">{naira(p.amount_naira)} · <span className="font-mono">{p.reference}</span></div>
                    <div className="text-xs text-slate-500">
                      {t("Paid")} {dateTime(p.paid_at)} ·{" "}
                      {p.application ? (
                        <Link href={`/portal/applications/${p.application.id}`} className="underline">
                          {(p.application as any).reference_number || p.application.application_number}
                        </Link>
                      ) : t("not used for an application")}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    {p.status === "REFUNDED" ? (
                      <StatusBadge status="REFUNDED" label="Refunded" />
                    ) : (
                      <StatusBadge status={p.status} label={p.status === "SUCCESS" ? "Successful" : p.status} />
                    )}
                    {/* Plain link: a PDF download through the BFF. */}
                    {(p.status === "SUCCESS" || p.status === "REFUNDED") && (
                      <a
                        href={withBase(`/api/bff/applicant/payment-records/${encodeURIComponent(p.reference)}/receipt`)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="rounded-md border border-slate-300 px-3 py-2 font-medium hover:border-nis-primary hover:text-nis-primary"
                      >
                        {t("Download receipt (PDF)")}
                      </a>
                    )}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
