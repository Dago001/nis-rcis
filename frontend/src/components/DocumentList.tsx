"use client";

import { api, type ApiScope } from "@/lib/api-client";
import type { ApplicationDocument } from "@/lib/types";

/** Documents open through short-lived signed URLs issued by the API. */
export function DocumentList({ scope, applicationId, documents }: { scope: ApiScope; applicationId: number; documents: ApplicationDocument[] }) {
  async function open(id: number) {
    const path = scope === "staff" ? `applications/${applicationId}/documents/${id}` : `applications/${applicationId}/documents/${id}`;
    const { url } = await api<{ url: string }>(scope, path);
    window.open(url, "_blank", "noopener,noreferrer");
  }

  if (documents.length === 0) return <p className="text-sm text-slate-600">No documents.</p>;

  return (
    <ul className="divide-y divide-slate-100">
      {documents.map((d) => (
        <li key={d.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
          <span>
            {d.label}
            {d.version > 1 && <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">v{d.version}</span>}
          </span>
          <button onClick={() => open(d.id)} className="font-medium text-nis-green hover:underline">View</button>
        </li>
      ))}
    </ul>
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
