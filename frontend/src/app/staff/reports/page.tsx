"use client";

import { useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Button, Field, Input, Panel, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";

type Report = {
  from: string; to: string; total_issued: number; active: number; revoked: number; renewals: number; nationalities: number;
  by_nationality: { nationality: string; total: number }[]; by_month: { month: string; total: number }[];
};

export default function ReportsPage() {
  const user = useStaff();
  const [range, setRange] = useState(() => ({ from: `${new Date().getFullYear()}-01-01`, to: new Date().toISOString().slice(0, 10) }));
  const { data: report } = useFetch<Report>("staff", `reports/summary?${new URLSearchParams(range)}`);

  function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setRange({ from: String(f.get("from")), to: String(f.get("to")) });
  }

  const max = Math.max(1, ...(report?.by_month.map((m) => m.total) ?? [1]));

  return (
    <div className="space-y-6">
      <PageTitle
        title="Issuance reports"
        actions={hasRole(user, "SuperAdmin") && (
          <a href={`/api/bff/staff/reports/export?${new URLSearchParams(range)}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Export CSV</a>
        )}
      />
      <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
        <Field label="From"><Input type="date" name="from" defaultValue={range.from} /></Field>
        <Field label="To"><Input type="date" name="to" defaultValue={range.to} /></Field>
        <Button type="submit">Apply</Button>
      </form>
      {!report ? <Spinner /> : (
        <>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
            {([["Cards issued", report.total_issued], ["Active", report.active], ["Revoked", report.revoked], ["Renewals", report.renewals], ["Nationalities", report.nationalities]] as const).map(([k, v]) => (
              <div key={k} className="rounded-xl border border-slate-200 bg-white p-4"><div className="text-xs uppercase text-slate-500">{k}</div><div className="text-2xl font-bold tabular-nums">{v}</div></div>
            ))}
          </div>
          <div className="grid gap-6 lg:grid-cols-2">
            <Panel title="Cards issued per month">
              {report.by_month.length === 0 ? <p className="text-sm text-slate-600">No data.</p> : (
                <div className="flex h-48 items-end gap-2">
                  {report.by_month.map((m) => (
                    <div key={m.month} className="flex flex-1 flex-col items-center gap-1">
                      <span className="text-xs tabular-nums text-slate-600">{m.total}</span>
                      <div className="w-full rounded-t bg-nis-green" style={{ height: `${(m.total / max) * 150}px` }} title={`${m.month}: ${m.total}`} />
                      <span className="text-[10px] text-slate-500">{m.month.slice(2)}</span>
                    </div>
                  ))}
                </div>
              )}
            </Panel>
            <Panel title="By nationality">
              <table className="w-full text-sm"><tbody>
                {report.by_nationality.map((n) => <tr key={n.nationality} className="border-b border-slate-100"><td className="py-1.5">{n.nationality}</td><td className="text-right tabular-nums">{n.total}</td></tr>)}
              </tbody></table>
            </Panel>
          </div>
        </>
      )}
    </div>
  );
}
