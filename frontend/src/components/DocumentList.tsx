"use client";

import { useState } from "react";
import { api, type ApiScope } from "@/lib/api-client";
import type { ApplicationDocument, ApplicationDocumentQuery } from "@/lib/types";

function formatDate(isoDate?: string): string {
  if (!isoDate) return "—";
  try {
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return "—";
    return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
  } catch {
    return "—";
  }
}

const COMMON_QUERY_REASONS = [
  "Illegible or blurry scan — please re-upload a clear, high-resolution copy.",
  "Document has expired — please provide a currently valid document.",
  "Details (name/date/number) do not match application particulars.",
  "Incomplete pages — please provide both the front and back pages.",
  "Invalid or uncertified document type for this category.",
];

export interface DocumentListProps {
  scope: ApiScope;
  applicationId: number;
  documents: ApplicationDocument[];
  stage?: "VETTING" | "APPROVAL";
  readOnly?: boolean;
  onQuerySuccess?: () => void;
}

/** Documents open through short-lived signed URLs issued by the API. */
export function DocumentList({
  scope,
  applicationId,
  documents,
  stage = "VETTING",
  readOnly = false,
  onQuerySuccess,
}: DocumentListProps) {
  const [openingId, setOpeningId] = useState<number | null>(null);
  const [queryingDoc, setQueryingDoc] = useState<ApplicationDocument | null>(null);
  const [queryReason, setQueryReason] = useState("");
  const [isSubmittingQuery, setIsSubmittingQuery] = useState(false);
  const [queryError, setQueryError] = useState<string | null>(null);

  async function open(id: number) {
    try {
      setOpeningId(id);
      const path = `applications/${applicationId}/documents/${id}`;
      const { url } = await api<{ url: string }>(scope, path);
      window.open(url, "_blank", "noopener,noreferrer");
    } catch (e) {
      console.error("Failed to open document", e);
    } finally {
      setOpeningId(null);
    }
  }

  function handleStartQuery(doc: ApplicationDocument) {
    setQueryingDoc(doc);
    setQueryReason(doc.active_query?.query_reason || "");
    setQueryError(null);
  }

  async function handleSubmitQuery(e: React.FormEvent) {
    e.preventDefault();
    if (!queryingDoc) return;
    const trimmed = queryReason.trim();
    if (trimmed.length < 3) {
      setQueryError("Please provide a specific query reason of at least 3 characters.");
      return;
    }

    try {
      setIsSubmittingQuery(true);
      setQueryError(null);
      await api(scope, `applications/${applicationId}/documents/${queryingDoc.id}/query`, {
        method: "POST",
        json: {
          reason: trimmed,
          stage,
        },
      });

      setQueryingDoc(null);
      setQueryReason("");
      if (onQuerySuccess) {
        onQuerySuccess();
      }
    } catch (err: any) {
      console.error("Failed to submit document query", err);
      setQueryError(err?.message || "Failed to submit document query. Please check and try again.");
    } finally {
      setIsSubmittingQuery(false);
    }
  }

  if (documents.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center">
        <p className="text-sm font-medium text-slate-600">No supporting documents uploaded.</p>
        <p className="text-xs text-slate-400 mt-1">Required documents must be uploaded before vetting can proceed.</p>
      </div>
    );
  }

  const showStaffQueryAction = scope === "staff" && !readOnly;

  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-xs">
        <table className="w-full text-left text-sm">
          <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200">
            <tr>
              <th className="px-4 py-3">Supporting Document</th>
              <th className="px-4 py-3">Query Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {documents.map((d) => {
              const activeQ = d.active_query;
              return (
                <tr key={d.id} className="hover:bg-slate-50/70 transition-colors">
                  <td className="px-4 py-3 align-top font-medium text-slate-900">
                    <div className="space-y-1.5">
                      <div className="flex items-center gap-2">
                        <svg className="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span className="text-sm font-semibold text-slate-800">{d.label}</span>
                        {d.version > 1 && (
                          <span className="rounded bg-sky-100 px-1.5 py-0.5 text-[11px] font-semibold text-sky-800">
                            v{d.version}
                          </span>
                        )}
                      </div>

                      {/* Display active query note if present */}
                      {activeQ && (
                        <div className="rounded-md border border-amber-200 bg-amber-50/80 p-2.5 text-xs text-amber-900 space-y-1 max-w-xl">
                          <div className="flex flex-wrap items-center justify-between gap-1 font-semibold text-[11px] text-amber-800">
                            <span>
                              Query ({activeQ.stage === "APPROVAL" ? "Approval Review" : "Vetting Review"}):
                            </span>
                            <span className="text-slate-500 font-normal">
                              {formatDate(activeQ.queried_at)}
                              {activeQ.queried_by && ` • ${activeQ.queried_by.fullname}`}
                            </span>
                          </div>
                          <p className="text-slate-800 font-normal leading-relaxed">{activeQ.query_reason}</p>

                          {activeQ.applicant_response && (
                            <div className="mt-1.5 pt-1.5 border-t border-amber-200/60 text-xs">
                              <span className="font-semibold text-blue-900">Applicant Response: </span>
                              <span className="text-slate-800">{activeQ.applicant_response}</span>
                              {activeQ.responded_at && (
                                <span className="block text-[10px] text-slate-500 mt-0.5">
                                  Responded on {formatDate(activeQ.responded_at)}
                                </span>
                              )}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  </td>

                  <td className="px-4 py-3 align-top">
                    {activeQ ? (
                      activeQ.status === "QUERY_RAISED" || activeQ.status === "AWAITING_RESPONSE" ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                          <span className="h-1.5 w-1.5 rounded-full bg-amber-600 animate-pulse" />
                          Query Raised
                        </span>
                      ) : activeQ.status === "RESPONSE_SUBMITTED" ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                          Response Submitted
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                          ✓ Resolved
                        </span>
                      )
                    ) : (
                      <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        Valid / Unqueried
                      </span>
                    )}
                  </td>

                  <td className="px-4 py-3 align-top text-right">
                    <div className="inline-flex items-center gap-2">
                      <button
                        type="button"
                        disabled={openingId === d.id}
                        onClick={() => open(d.id)}
                        className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-nis-green hover:bg-slate-50 hover:border-nis-green transition-colors disabled:opacity-50"
                      >
                        <span>{openingId === d.id ? "Opening..." : "View"}</span>
                        <svg className="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                      </button>

                      {showStaffQueryAction && (
                        <button
                          type="button"
                          onClick={() => handleStartQuery(d)}
                          className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-semibold transition-colors ${
                            activeQ && activeQ.status !== "RESOLVED"
                              ? "border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100"
                              : "border-slate-200 bg-white text-amber-700 hover:border-amber-400 hover:bg-amber-50"
                          }`}
                        >
                          <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                          </svg>
                          <span>{activeQ && activeQ.status !== "RESOLVED" ? "Update Query" : "Query"}</span>
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Query Supporting Document Modal */}
      {queryingDoc && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-200">
          <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-2xl space-y-4">
            <div className="flex items-start justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-base font-bold text-slate-900">
                  Query Supporting Document
                </h3>
                <p className="text-xs text-slate-500 mt-0.5">
                  Review Stage: <strong className="text-amber-700">{stage === "APPROVAL" ? "Approval Review" : "Vetting Review"}</strong>
                </p>
              </div>
              <button
                type="button"
                onClick={() => setQueryingDoc(null)}
                className="text-slate-400 hover:text-slate-600 rounded-md p-1"
                aria-label="Close"
              >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-1">
              <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Document to Query:</span>
              <div className="flex items-center gap-2">
                <svg className="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span className="text-sm font-bold text-slate-800">{queryingDoc.label}</span>
                {queryingDoc.version > 1 && (
                  <span className="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800">
                    Current v{queryingDoc.version}
                  </span>
                )}
              </div>
            </div>

            <form onSubmit={handleSubmitQuery} className="space-y-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Common Query Reasons (Click to insert):
                </label>
                <div className="flex flex-wrap gap-1.5">
                  {COMMON_QUERY_REASONS.map((reason, idx) => (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setQueryReason(reason)}
                      className="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors text-left"
                    >
                      {reason.split("—")[0].trim()}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label htmlFor="queryReasonInput" className="block text-xs font-semibold text-slate-700 mb-1">
                  Specific Query Reason &amp; Required Applicant Action <span className="text-red-500">*</span>
                </label>
                <textarea
                  id="queryReasonInput"
                  rows={3}
                  value={queryReason}
                  onChange={(e) => setQueryReason(e.target.value)}
                  placeholder="State clearly what is wrong with this document and what the applicant must do to correct it..."
                  required
                  className="w-full rounded-md border border-slate-300 p-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-nis-green focus:ring-1 focus:ring-nis-green"
                />
                <span className="text-[11px] text-slate-500">
                  This reason will be included in the applicant's notification email and displayed on their portal.
                </span>
              </div>

              {queryError && (
                <div className="rounded-md bg-red-50 border border-red-200 p-2.5 text-xs text-red-700">
                  {queryError}
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button
                  type="button"
                  disabled={isSubmittingQuery}
                  onClick={() => setQueryingDoc(null)}
                  className="rounded-md border border-slate-200 px-3.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmittingQuery || queryReason.trim().length < 3}
                  className="rounded-md bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-50 inline-flex items-center gap-1.5 shadow-xs"
                >
                  {isSubmittingQuery ? (
                    <>
                      <span className="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent" />
                      Submitting Query...
                    </>
                  ) : (
                    "Submit Document Query"
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export function History({ history }: { history: NonNullable<import("@/lib/types").Application["history"]> }) {
  return (
    <ol className="relative space-y-4 border-l border-slate-200 pl-5">
      {history.map((h, i) => (
        <li key={i}>
          <span className="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full bg-nis-green" />
          <div className="text-sm font-medium">{h.label}</div>
          {h.notes && <div className="text-sm text-slate-600">{h.notes}</div>}
          <div className="text-xs text-slate-400">{new Date(h.at).toLocaleString("en-GB")}</div>
        </li>
      ))}
    </ol>
  );
}
