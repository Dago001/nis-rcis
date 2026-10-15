"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Field, Input, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type CardIssuanceItem = {
  id: number;
  card_number: string;
  booklet_number: string;
  status: string;
  verification_status: string;
  surname: string;
  forenames: string;
  holder: string;
  passport_number: string;
  nationality: string;
  issued_on: string | null;
  expires_on: string | null;
  printed_count: number;
  photo_url: string | null;
  application: {
    id: number;
    application_number: string;
    reference_number: string;
    status: string;
    status_label: string;
    ready_at: string | null;
    collected_at: string | null;
    enrollment_center?: {
      id: number;
      name: string;
      code: string;
      state: string;
    } | null;
  } | null;
};

type CardIssuanceResponse = {
  data: CardIssuanceItem[];
  stats: {
    awaiting_collection: number;
    ready_to_notify: number;
    collected_total: number;
    total_produced: number;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

function CardIssuanceContent() {
  const router = useRouter();
  const params = useSearchParams();
  const currentStage = params.get("stage") || "awaiting_collection";
  const searchQuery = params.get("search") || "";
  const page = params.get("page") || "1";

  const { data: response, reload, loading } = useFetch<CardIssuanceResponse>(
    "staff",
    `card-issuance?stage=${encodeURIComponent(currentStage)}&search=${encodeURIComponent(searchQuery)}&page=${page}`
  );

  const [selected, setSelected] = useState<number[]>([]);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Modal state for issuing out a card
  const [issuingCard, setIssuingCard] = useState<CardIssuanceItem | null>(null);
  const [confirmedDocs, setConfirmedDocs] = useState(false);
  const [issueNotes, setIssueNotes] = useState("");

  const toggleSelect = (id: number) => {
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const s = (fd.get("search") as string)?.trim() || "";
    const p = new URLSearchParams();
    if (currentStage) p.set("stage", currentStage);
    if (s) p.set("search", s);
    router.push(`/staff/card-issuance?${p.toString()}`);
  };

  const changeStage = (newStage: string) => {
    setSelected([]);
    const p = new URLSearchParams();
    p.set("stage", newStage);
    if (searchQuery) p.set("search", searchQuery);
    router.push(`/staff/card-issuance?${p.toString()}`);
  };

  const handleMarkReady = async (card: CardIssuanceItem) => {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const res = await api<{ message: string }>("staff", `card-issuance/${card.id}/ready`, {
        method: "POST",
      });
      setNotice(res.message);
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const handleIssueOutSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!issuingCard || !confirmedDocs) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const res = await api<{ message: string }>("staff", `card-issuance/${issuingCard.id}/issue-out`, {
        method: "POST",
        json: { notes: issueNotes.trim() },
      });
      setNotice(res.message);
      setIssuingCard(null);
      setConfirmedDocs(false);
      setIssueNotes("");
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const handleBatchReady = async () => {
    if (selected.length === 0) return;
    if (!confirm(`Mark ${selected.length} card(s) ready for collection and notify applicants?`)) return;
    setBusy(true);
    setError(null);
    try {
      const res = await api<{ message: string }>("staff", "card-issuance/batch-ready", {
        method: "POST",
        json: { card_ids: selected },
      });
      setNotice(res.message);
      setSelected([]);
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const handleBatchIssue = async () => {
    if (selected.length === 0) return;
    if (!confirm(`Record handover / collection for ${selected.length} card(s) and notify applicants?`)) return;
    setBusy(true);
    setError(null);
    try {
      const res = await api<{ message: string }>("staff", "card-issuance/batch-issue", {
        method: "POST",
        json: { card_ids: selected },
      });
      setNotice(res.message);
      setSelected([]);
      reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const stats = response?.stats ?? {
    awaiting_collection: 0,
    ready_to_notify: 0,
    collected_total: 0,
    total_produced: 0,
  };

  const cards = response?.data ?? [];

  return (
    <div className="space-y-6">
      <PageTitle
        title="Card Issuance & Collection Desk"
        subtitle="Issue out produced residence cards, record physical collection, and update applicant tracking via 'Ready / collected'."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link
              href="/staff/cards"
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              All residence cards
            </Link>
            <Link
              href="/staff/card-stock"
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Card stock &amp; printing
            </Link>
          </div>
        }
      />

      {/* KPI Summary Tiles */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <button
          type="button"
          onClick={() => changeStage("awaiting_collection")}
          className={`flex flex-col rounded-xl border p-4 text-left transition-all ${
            currentStage === "awaiting_collection"
              ? "border-emerald-500 bg-emerald-50/80 ring-2 ring-emerald-500/20"
              : "border-slate-200 bg-white hover:border-emerald-300"
          }`}
        >
          <span className="text-xs font-semibold uppercase tracking-wider text-emerald-800">
            Awaiting Collection
          </span>
          <span className="mt-1 text-2xl font-bold text-slate-900">{stats.awaiting_collection}</span>
          <span className="mt-0.5 text-xs text-slate-500">Ready to issue out</span>
        </button>

        <button
          type="button"
          onClick={() => changeStage("ready_to_notify")}
          className={`flex flex-col rounded-xl border p-4 text-left transition-all ${
            currentStage === "ready_to_notify"
              ? "border-amber-500 bg-amber-50/80 ring-2 ring-amber-500/20"
              : "border-slate-200 bg-white hover:border-amber-300"
          }`}
        >
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">
            Produced (Notify Ready)
          </span>
          <span className="mt-1 text-2xl font-bold text-slate-900">{stats.ready_to_notify}</span>
          <span className="mt-0.5 text-xs text-slate-500">Cards printed</span>
        </button>

        <button
          type="button"
          onClick={() => changeStage("collected")}
          className={`flex flex-col rounded-xl border p-4 text-left transition-all ${
            currentStage === "collected"
              ? "border-nis-primary bg-nis-mint/60 ring-2 ring-nis-primary/20"
              : "border-slate-200 bg-white hover:border-nis-primary/40"
          }`}
        >
          <span className="text-xs font-semibold uppercase tracking-wider text-nis-primary-dark">
            Issued &amp; Collected
          </span>
          <span className="mt-1 text-2xl font-bold text-slate-900">{stats.collected_total}</span>
          <span className="mt-0.5 text-xs text-slate-500">Handed over to holders</span>
        </button>

        <button
          type="button"
          onClick={() => changeStage("all")}
          className={`flex flex-col rounded-xl border p-4 text-left transition-all ${
            currentStage === "all"
              ? "border-slate-500 bg-slate-100 ring-2 ring-slate-400/20"
              : "border-slate-200 bg-white hover:border-slate-300"
          }`}
        >
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-600">
            Total Produced Cards
          </span>
          <span className="mt-1 text-2xl font-bold text-slate-900">{stats.total_produced}</span>
          <span className="mt-0.5 text-xs text-slate-500">All stages</span>
        </button>
      </div>

      {notice && <Alert tone="success">{notice}</Alert>}
      {error && <Alert tone="danger">{error}</Alert>}

      {/* Search and Filter Form */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form onSubmit={handleSearch} className="flex flex-1 max-w-lg gap-2">
          <Input
            name="search"
            defaultValue={searchQuery}
            placeholder="Search Card No, Booklet, Passport, Name, or Application Ref..."
          />
          <Button type="submit" disabled={busy}>
            Search
          </Button>
        </form>

        {/* Batch Action Buttons */}
        {selected.length > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-slate-600">
              {selected.length} selected
            </span>
            {currentStage === "ready_to_notify" && (
              <Button variant="gold" onClick={handleBatchReady} disabled={busy}>
                Notify Selected ({selected.length})
              </Button>
            )}
            {(currentStage === "awaiting_collection" || currentStage === "ready_to_notify") && (
              <Button variant="primary" onClick={handleBatchIssue} disabled={busy}>
                Issue Selected ({selected.length})
              </Button>
            )}
          </div>
        )}
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200">
        <nav className="-mb-px flex space-x-6" aria-label="Issuance stages">
          {[
            { id: "awaiting_collection", label: "Awaiting Collection", count: stats.awaiting_collection },
            { id: "ready_to_notify", label: "Produced (Notify Ready)", count: stats.ready_to_notify },
            { id: "collected", label: "Issued & Collected", count: stats.collected_total },
            { id: "all", label: "All Produced Cards", count: stats.total_produced },
          ].map((tab) => {
            const active = currentStage === tab.id;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => changeStage(tab.id)}
                className={`flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors ${
                  active
                    ? "border-nis-primary text-nis-primary"
                    : "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700"
                }`}
              >
                <span>{tab.label}</span>
                <span
                  className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                    active ? "bg-nis-primary text-white" : "bg-slate-100 text-slate-600"
                  }`}
                >
                  {tab.count}
                </span>
              </button>
            );
          })}
        </nav>
      </div>

      {/* Cards Table */}
      {loading ? (
        <Spinner />
      ) : cards.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white p-12 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM7 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 9h6M12 13h6M5 16h6" />
            </svg>
          </div>
          <p className="mt-3 text-sm font-semibold text-slate-800">No cards in this category</p>
          <p className="mt-1 text-xs text-slate-500">
            {searchQuery
              ? `No results matching "${searchQuery}". Try clearing search.`
              : currentStage === "awaiting_collection"
              ? "There are currently no cards marked ready for collection."
              : currentStage === "ready_to_notify"
              ? "All printed cards have been notified."
              : "No records found."}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
              <tr>
                <th className="w-10 px-4 py-3">
                  <input
                    type="checkbox"
                    aria-label="Select all"
                    checked={cards.length > 0 && cards.every((c) => selected.includes(c.id))}
                    onChange={(e) =>
                      setSelected(e.target.checked ? cards.map((c) => c.id) : [])
                    }
                  />
                </th>
                <th className="px-4 py-3">Card &amp; Booklet</th>
                <th className="px-4 py-3">Cardholder</th>
                <th className="px-4 py-3">Application Ref</th>
                <th className="px-4 py-3">Collection Centre</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {cards.map((card) => {
                const app = card.application;
                const isReady = app?.status === "READY_FOR_COLLECTION";
                const isCollected = app?.status === "ISSUED";
                const isProduced = app?.status === "BIOMETRICS_CAPTURED";

                return (
                  <tr key={card.id} className="hover:bg-slate-50/70 transition-colors">
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selected.includes(card.id)}
                        onChange={() => toggleSelect(card.id)}
                        aria-label={`Select card ${card.card_number}`}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        href={`/staff/cards/${card.id}`}
                        className="font-bold text-nis-primary hover:underline"
                      >
                        {card.card_number}
                      </Link>
                      <div className="text-xs text-slate-500 font-mono">
                        {card.booklet_number}
                      </div>
                      <div className="text-[11px] text-slate-400 mt-0.5">
                        Expires: {card.expires_on ? nisDate(card.expires_on) : "—"}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        {card.photo_url ? (
                          <img
                            src={card.photo_url}
                            alt=""
                            className="h-9 w-9 rounded-full object-cover border border-slate-200"
                          />
                        ) : (
                          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                            {card.surname?.[0]}
                            {card.forenames?.[0]}
                          </div>
                        )}
                        <div>
                          <div className="font-semibold text-slate-900">{card.holder}</div>
                          <div className="text-xs text-slate-500">
                            Passport: {card.passport_number} · {card.nationality}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      {app ? (
                        <>
                          <Link
                            href={`/staff/applications/${app.id}`}
                            className="font-semibold text-slate-800 hover:text-nis-primary hover:underline"
                          >
                            {app.application_number}
                          </Link>
                          <div className="text-xs text-slate-500 font-mono">
                            Ref: {app.reference_number}
                          </div>
                        </>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-800">
                        {app?.enrollment_center?.name || "Main Centre"}
                      </div>
                      <div className="text-xs text-slate-500">
                        {app?.enrollment_center?.state || "State Command"}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="space-y-1">
                        {isReady && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-900">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse" />
                            Ready for Collection
                          </span>
                        )}
                        {isCollected && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-nis-green-light px-2.5 py-0.5 text-xs font-bold text-nis-green-dark">
                            ✓ Collected
                          </span>
                        )}
                        {isProduced && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-900">
                            Produced (Printed)
                          </span>
                        )}
                        {app?.collected_at && (
                          <div className="text-[11px] text-slate-500">
                            {dateTime(app.collected_at)}
                          </div>
                        )}
                        {app?.ready_at && !isCollected && (
                          <div className="text-[11px] text-slate-500">
                            Notified: {dateTime(app.ready_at)}
                          </div>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {isReady && (
                          <Button
                            variant="primary"
                            className="text-xs px-3 py-1.5"
                            onClick={() => {
                              setIssuingCard(card);
                              setConfirmedDocs(false);
                              setIssueNotes("");
                            }}
                            disabled={busy}
                          >
                            ✓ Issue Out Card
                          </Button>
                        )}
                        {isProduced && (
                          <>
                            <Button
                              variant="gold"
                              className="text-xs px-3 py-1.5"
                              onClick={() => handleMarkReady(card)}
                              disabled={busy}
                            >
                              Notify Ready
                            </Button>
                            <Button
                              variant="primary"
                              className="text-xs px-3 py-1.5"
                              onClick={() => {
                                setIssuingCard(card);
                                setConfirmedDocs(false);
                                setIssueNotes("");
                              }}
                              disabled={busy}
                            >
                              Issue Directly
                            </Button>
                          </>
                        )}
                        {isCollected && (
                          <Link
                            href={`/staff/cards/${card.id}`}
                            className="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                          >
                            View Card
                          </Link>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Handover & Issuance Confirmation Modal */}
      {issuingCard && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 animate-in fade-in duration-150">
          <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl space-y-5">
            <div className="border-b border-slate-100 pb-3">
              <h3 className="text-lg font-bold text-slate-900">
                Confirm Residence Card Handover &amp; Issuance
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Record physical card collection and notify applicant via &quot;Ready / collected&quot;.
              </p>
            </div>

            {/* Applicant Summary */}
            <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-2">
              <div className="flex items-center gap-3">
                {issuingCard.photo_url && (
                  <img
                    src={issuingCard.photo_url}
                    alt=""
                    className="h-12 w-12 rounded-lg object-cover border border-slate-200"
                  />
                )}
                <div>
                  <div className="font-bold text-slate-900 text-base">{issuingCard.holder}</div>
                  <div className="text-xs text-slate-600">
                    Passport: <strong>{issuingCard.passport_number}</strong> · {issuingCard.nationality}
                  </div>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 text-xs">
                <div>
                  <span className="text-slate-500">Card Number:</span>{" "}
                  <strong className="text-nis-primary">{issuingCard.card_number}</strong>
                </div>
                <div>
                  <span className="text-slate-500">Booklet No:</span>{" "}
                  <strong>{issuingCard.booklet_number}</strong>
                </div>
                <div>
                  <span className="text-slate-500">Application No:</span>{" "}
                  <strong>{issuingCard.application?.application_number}</strong>
                </div>
                <div>
                  <span className="text-slate-500">Collection Centre:</span>{" "}
                  <strong>{issuingCard.application?.enrollment_center?.name || "Main Centre"}</strong>
                </div>
              </div>
            </div>

            <form onSubmit={handleIssueOutSubmit} className="space-y-4">
              <label className="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 cursor-pointer">
                <input
                  type="checkbox"
                  required
                  checked={confirmedDocs}
                  onChange={(e) => setConfirmedDocs(e.target.checked)}
                  className="mt-0.5 h-4 w-4 rounded border-slate-300 text-nis-primary focus:ring-nis-primary"
                />
                <span className="text-xs font-medium text-emerald-950">
                  I confirm that the applicant has physically appeared, presented their valid original travel document (passport) and collection slip, and received their Residence Card.
                </span>
              </label>

              <Field label="Handover Notes / Verification Remarks (Optional)">
                <Textarea
                  value={issueNotes}
                  onChange={(e) => setIssueNotes(e.target.value)}
                  placeholder="e.g. Identity verified via passport and collection slip. Physical card handed over to holder."
                  rows={2}
                />
              </Field>

              <div className="flex justify-end gap-2 pt-2">
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  onClick={() => {
                    setIssuingCard(null);
                    setConfirmedDocs(false);
                    setIssueNotes("");
                  }}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  disabled={busy || !confirmedDocs}
                >
                  {busy ? "Processing..." : "✓ Confirm Handover & Issue Out"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export default function CardIssuancePage() {
  return (
    <Suspense fallback={<Spinner />}>
      <CardIssuanceContent />
    </Suspense>
  );
}
