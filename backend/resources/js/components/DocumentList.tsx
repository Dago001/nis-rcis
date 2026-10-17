"use client";

import { useState } from "react";
import { api, type ApiScope } from "@/lib/api-client";
import type { ApplicationDocument } from "@/lib/types";

function formatBytes(bytes?: number): string {
  if (!bytes || bytes <= 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(1)} KB`;
  const mb = kb / 1024;
  return `${mb.toFixed(1)} MB`;
}

function formatDate(isoDate?: string): string {
  if (!isoDate) return "—";
  try {
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return "—";
    return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
  } catch {
    return "—";
  }
}

/** Documents open through short-lived signed URLs issued by the API. */
export function DocumentList({ scope, applicationId, documents }: { scope: ApiScope; applicationId: number; documents: ApplicationDocument[] }) {
  const [openingId, setOpeningId] = useState<number | null>(null);

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

  if (documents.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center">
        <p className="text-sm font-medium text-slate-600">No supporting documents uploaded.</p>
        <p className="text-xs text-slate-400 mt-1">Required documents must be uploaded before vetting can proceed.</p>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-xs">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200">
          <tr>
            <th className="px-4 py-3">Document Name</th>
            <th className="px-4 py-3">File Type</th>
            <th className="px-4 py-3">Size</th>
            <th className="px-4 py-3">Upload Date</th>
            <th className="px-4 py-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {documents.map((d) => (
            <tr key={d.id} className="hover:bg-slate-50/70 transition-colors">
              <td className="px-4 py-3 font-medium text-slate-900">
                <div className="flex items-center gap-2">
                  <svg className="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <span>{d.label}</span>
                  {d.version > 1 && (
                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">
                      v{d.version}
                    </span>
                  )}
                </div>
              </td>
              <td className="px-4 py-3 text-xs text-slate-600 uppercase font-mono">
                {d.mime_type ? d.mime_type.split("/")[1] || d.mime_type : "File"}
              </td>
              <td className="px-4 py-3 text-xs text-slate-600">
                {formatBytes(d.size_bytes)}
              </td>
              <td className="px-4 py-3 text-xs text-slate-600">
                {formatDate(d.uploaded_at)}
              </td>
              <td className="px-4 py-3 text-right">
                <button
                  type="button"
                  disabled={openingId === d.id}
                  onClick={() => open(d.id)}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-nis-green hover:underline disabled:opacity-50"
                >
                  <span>{openingId === d.id ? "Opening..." : "View"}</span>
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
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
