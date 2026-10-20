"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { CardPrintPages, PrintableCard, type PrintData } from "@/components/PrintableCard";
import { Alert, Button, Input, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";

export default function PrintCardPage() {
  const { id } = useParams<{ id: string }>();
  const [p, setP] = useState<PrintData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [showSpoilModal, setShowSpoilModal] = useState(false);
  const [spoilReason, setSpoilReason] = useState("");

  const load = useCallback(() => {
    return api<PrintData>("staff", `cards/${id}/print`).then((res) => {
      setP(res);
      return res;
    });
  }, [id]);

  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  const handlePrint = () => {
    window.print();
  };

  const handleConfirmPrinted = async () => {
    if (!p) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await api<{ stock?: { remaining: number }; message?: string }>("staff", `cards/${id}/print-jobs`, {
        method: "POST",
        json: { outcome: "PRINTED" },
      });
      setNotice(`Card ${p.data.card_number} has been confirmed as Printed. It is now available under Card Issuance.`);
      await load();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const handleRecordSpoiled = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!spoilReason.trim()) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await api<{ stock?: { remaining: number } }>("staff", `cards/${id}/print-jobs`, {
        method: "POST",
        json: { outcome: "SPOILED", reason: spoilReason.trim() },
      });
      setNotice(`Card marked as spoiled. You may reprint when ready.`);
      setShowSpoilModal(false);
      setSpoilReason("");
      await load();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  if (error && !p) return <Alert tone="danger">{error}</Alert>;
  if (!p) return <Spinner />;

  const card = p.data;
  const isPrinted = card.production_status === "PRINTED" || card.production_status === "Printed" || (card.printed_count ?? 0) > 0;
  const latestPrint = card.print_jobs && card.print_jobs.length > 0 ? card.print_jobs[0] : null;

  return (
    <div className="space-y-6">
      {/* Top Header & Navigation */}
      <div className="no-print space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <Link
              href={`/staff/cards/${id}`}
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-nis-primary hover:text-nis-primary-dark hover:underline mb-1"
            >
              ← Back to Card Details
            </Link>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              Residence Card Preview
            </h1>
            <p className="text-sm text-slate-500">
              Card Number: <span className="font-semibold text-slate-800">{card.card_number}</span> · 
              App Ref: <span className="font-semibold text-slate-800">{card.application?.application_number ?? card.application_reference ?? "—"}</span> · 
              Holder: <span className="font-semibold text-slate-800">{card.surname}, {card.forenames}</span>
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="secondary"
              onClick={handlePrint}
              className="inline-flex items-center gap-2 bg-white"
            >
              <svg className="w-4 h-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Print Card
            </Button>

            {!isPrinted && (
              <Button
                variant="primary"
                onClick={handleConfirmPrinted}
                disabled={busy}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                {busy ? "Confirming..." : "Confirm Printed"}
              </Button>
            )}

            {isPrinted && (
              <Link
                href="/staff/card-issuance"
                className="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 transition-colors"
              >
                Go to Card Issuance →
              </Link>
            )}

            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowSpoilModal(true)}
              className="border-slate-300 bg-white text-slate-700 hover:border-red-300 hover:text-red-700 hover:bg-red-50/50"
            >
              Report Spoiled Card
            </Button>
          </div>
        </div>

        {notice && <Alert tone="success">{notice}</Alert>}
        {error && <Alert tone="danger">{error}</Alert>}

        {/* Workflow Guidance & Status Box */}
        {isPrinted && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 sm:p-5 shadow-xs">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white text-sm font-bold">
                ✓
              </div>
              <div className="space-y-1">
                <h3 className="text-base font-semibold text-emerald-950">
                  Card Production Confirmed — Status: Printed
                </h3>
                <p className="text-sm text-emerald-800">
                  Physical printing for this card has been confirmed. This card is now ready and available in the{" "}
                  <Link href="/staff/card-issuance" className="font-semibold underline hover:text-emerald-950">
                    Card Issuance
                  </Link>{" "}
                  queue for collection and handover to the applicant.
                </p>
                {latestPrint && (
                  <p className="mt-2 text-xs text-emerald-700">
                    Confirmed by: <span className="font-semibold">{latestPrint.printed_by ?? "Authorized Officer"}</span>
                    {latestPrint.at && <> · Date & Time: <span className="font-semibold">{dateTime(latestPrint.at)}</span></>}
                  </p>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Modal for Spoiled Card Reporting */}
        {showSpoilModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold text-slate-900">Record Spoiled Blank Card</h3>
              <p className="mt-1 text-xs text-slate-500">
                If the physical card was damaged, misfed or printed incorrectly, record it as spoiled to account for the blank stock.
              </p>
              <form onSubmit={handleRecordSpoiled} className="mt-4 space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Reason for Spoilage *
                  </label>
                  <Input
                    value={spoilReason}
                    onChange={(e) => setSpoilReason(e.target.value)}
                    placeholder="e.g. Ribbon smudge, print misaligned, card jammed"
                    required
                  />
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => setShowSpoilModal(false)}
                    disabled={busy}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    variant="danger"
                    disabled={busy || !spoilReason.trim()}
                  >
                    Record Spoiled
                  </Button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>

      {/* Residence Card Visual Preview (Screen) */}
      <div className="flex flex-col items-center justify-center rounded-2xl border border-slate-200 bg-slate-100/60 p-6 shadow-inner">
        <PrintableCard p={p} />
      </div>

      {/* Fargo / Duplex Printer Output (Print media only) */}
      <CardPrintPages cards={[p]} />
    </div>
  );
}
