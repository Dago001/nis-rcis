"use client";

import { useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type Check = { name: string; status: "ok" | "warn" | "fail"; detail: string };
type ErrorRow = { id: number; exception: string; message: string; where: string; request: string; occurrences: number; first_seen_at: string; last_seen_at: string; resolved_at: string | null };
type Health = { status: Check["status"]; checks: Check[]; environment: string; alert_emails: number; errors: ErrorRow[] };

const BADGE: Record<Check["status"], [string, string]> = { ok: ["VALID", "OK"], warn: ["PENDING_APPROVAL", "Warning"], fail: ["REJECTED", "Failing"] };

/** Monitoring: health checks and tracked server errors (Super Administrators). */
export default function SystemPage() {
  const { data, reload } = useFetch<Health>("staff", "system/health");
  const [showResolved, setShowResolved] = useState(false);

  async function resolve(id: number) {
    await api("staff", `system/errors/${id}/resolve`, { method: "POST" });
    reload();
  }

  if (!data) return <Spinner />;
  const errors = data.errors.filter((e) => showResolved || !e.resolved_at);

  return (
    <div className="space-y-6">
      <PageTitle
        title="System health"
        subtitle={`Environment: ${data.environment} · checked automatically every 5 minutes`}
        actions={<Button variant="secondary" onClick={reload}>Check now</Button>}
      />
      {data.alert_emails === 0 && <Alert tone="warning">No alert e-mail addresses are set (ALERT_EMAILS in backend\.env), so nobody is told when something fails.</Alert>}
      <Alert tone={data.status === "ok" ? "success" : data.status === "warn" ? "warning" : "danger"}>
        Overall: <strong>{BADGE[data.status][1]}</strong>. External uptime monitors should check <code>/api/v1/health</code> on the API and <code>/api/health</code> on the website.
      </Alert>

      <Panel title="Checks">
        <ul className="divide-y divide-slate-100 text-sm">
          {data.checks.map((c) => (
            <li key={c.name} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span className="w-44 font-medium">{c.name}</span>
              <span className="flex-1 text-slate-600">{c.detail}</span>
              <StatusBadge status={BADGE[c.status][0]} label={BADGE[c.status][1]} />
            </li>
          ))}
        </ul>
      </Panel>

      <Panel title="Server errors" actions={<label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={showResolved} onChange={(e) => setShowResolved(e.target.checked)} /> Show resolved</label>}>
        {errors.length === 0 ? <p className="text-sm text-slate-600">No server errors.</p> : (
          <ul className="divide-y divide-slate-100 text-sm">
            {errors.map((e) => (
              <li key={e.id} className="space-y-1 py-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="font-medium">{e.exception.split("\\").pop()} <span className="text-slate-500">× {e.occurrences}</span></div>
                    <div className="break-words text-slate-700">{e.message}</div>
                    <div className="text-xs text-slate-500">{e.where} · {e.request} · first {dateTime(e.first_seen_at)} · last {dateTime(e.last_seen_at)}</div>
                  </div>
                  {e.resolved_at ? <span className="text-xs text-nis-primary">Resolved {dateTime(e.resolved_at)}</span>
                    : <Button variant="secondary" className="!py-1.5" onClick={() => resolve(e.id)}>Mark resolved</Button>}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
