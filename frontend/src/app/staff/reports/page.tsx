"use client";

import { useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";

type Report = {
  from: string; to: string; total_issued: number; active: number; revoked: number; renewals: number; nationalities: number;
  by_nationality: { nationality: string; total: number }[]; by_month: { month: string; total: number }[];
};
type Filters = { from: string; to: string; status?: string; nationality?: string };
type SavedFilter = { id: number; name: string; filters: Partial<Filters> };

const clean = (f: Filters) => Object.fromEntries(Object.entries(f).filter(([, v]) => v)) as Record<string, string>;

export default function ReportsPage() {
  const user = useStaff();
  const [filters, setFilters] = useState<Filters>(() => ({ from: `${new Date().getFullYear()}-01-01`, to: new Date().toISOString().slice(0, 10) }));
  const [formKey, setFormKey] = useState(0);
  const { data: report } = useFetch<Report>("staff", `reports/summary?${new URLSearchParams(clean(filters))}`);
  const saved = useFetch<{ data: SavedFilter[] }>("staff", "reports/filters");
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7));
  const [message, setMessage] = useState<string | null>(null);
  const canExport = hasRole(user, "SuperAdmin");
  const canManage = hasRole(user, "SuperAdmin", "Auditor");

  function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setFilters({ from: String(f.get("from")), to: String(f.get("to")), status: String(f.get("status") ?? ""), nationality: String(f.get("nationality") ?? "").trim() });
  }

  function applySaved(id: string) {
    const s = saved.data?.data.find((x) => String(x.id) === id);
    if (!s) return;
    setFilters((f) => ({ ...f, status: "", nationality: "", ...s.filters }));
    setFormKey((k) => k + 1); // re-render the form with the saved values
  }

  async function save() {
    const name = prompt("Name for these filters (for example “Ghana – issued this year”)");
    if (!name?.trim()) return;
    await api("staff", "reports/filters", { method: "POST", json: { name: name.trim(), filters: clean(filters) } });
    setMessage(`Filters saved as “${name.trim()}”.`);
    saved.reload();
  }

  async function remove(s: SavedFilter) {
    if (!confirm(`Delete the saved filters “${s.name}”?`)) return;
    await api("staff", `reports/filters/${s.id}`, { method: "DELETE" });
    saved.reload();
  }

  const max = Math.max(1, ...(report?.by_month.map((m) => m.total) ?? [1]));
  const query = new URLSearchParams(clean(filters));

  return (
    <div className="space-y-6">
      <PageTitle
        title="Issuance reports"
        actions={canExport && (
          <div className="flex gap-2">
            <a href={`/api/bff/staff/reports/export?${query}&format=csv`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Export CSV</a>
            <a href={`/api/bff/staff/reports/export?${query}&format=xlsx`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Export Excel</a>
          </div>
        )}
      />
      {message && <Alert tone="success">{message}</Alert>}

      {canManage && (
        <Panel title="Monthly management report">
          <div className="flex flex-wrap items-end gap-3">
            <Field label="Month"><Input type="month" value={month} max={new Date().toISOString().slice(0, 7)} onChange={(e) => setMonth(e.target.value)} /></Field>
            <a href={`/api/bff/staff/reports/management?month=${month}&format=pdf`} className="rounded-md bg-nis-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-nis-primary-dark">Download PDF</a>
            <a href={`/api/bff/staff/reports/management?month=${month}&format=xlsx`} className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:border-nis-primary hover:text-nis-primary">Download Excel</a>
            <p className="basis-full text-xs text-slate-500">Applications, service-level performance, cards, fees and refunds, card production and the centre queue for the month.</p>
          </div>
        </Panel>
      )}

      <form key={formKey} onSubmit={apply} className="flex flex-wrap items-end gap-3">
        <Field label="From"><Input type="date" name="from" defaultValue={filters.from} /></Field>
        <Field label="To"><Input type="date" name="to" defaultValue={filters.to} /></Field>
        <Field label="Card status">
          <Select name="status" defaultValue={filters.status ?? ""}>
            <option value="">All</option>
            <option value="ISSUED">Issued</option>
            <option value="RENEWED">Renewed</option>
            <option value="REVOKED">Revoked</option>
            <option value="APPROVED">Awaiting approval</option>
          </Select>
        </Field>
        <Field label="Nationality"><Input name="nationality" defaultValue={filters.nationality ?? ""} placeholder="e.g. GHANA" /></Field>
        <Button type="submit">Apply</Button>
        <Button type="button" variant="secondary" onClick={save}>Save filters</Button>
        {!!saved.data?.data.length && (
          <Field label="Saved filters">
            <Select defaultValue="" onChange={(e) => applySaved(e.target.value)}>
              <option value="">Choose…</option>
              {saved.data.data.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>
          </Field>
        )}
      </form>
      {!!saved.data?.data.length && (
        <p className="-mt-3 text-xs text-slate-500">
          Delete: {saved.data.data.map((s, i) => <span key={s.id}>{i > 0 && ", "}<button className="underline" onClick={() => remove(s)}>{s.name}</button></span>)}
        </p>
      )}

      {!report ? <Spinner /> : (
        <>
          <dl className="flex flex-wrap gap-x-8 gap-y-2 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm">
            {([["Cards issued", report.total_issued], ["Active", report.active], ["Revoked", report.revoked], ["Renewals", report.renewals], ["Nationalities", report.nationalities]] as const).map(([k, v]) => (
              <div key={k}><dt className="text-xs uppercase tracking-wide text-slate-500">{k}</dt><dd className="text-lg font-medium tabular-nums">{v}</dd></div>
            ))}
          </dl>
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
