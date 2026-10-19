"use client";

import { useState, useRef, useEffect, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { useI18n } from "@/components/I18nProvider";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";
import { withBase } from "@/lib/base-path";
import { ECOWAS_NATIONALITIES } from "@/lib/nationalities";

type ReportSummary = {
  from: string;
  to: string;
  total_issued: number;
  new_issued: number;
  renewed_issued: number;
  replacement_issued: number;
  nationalities: number;
  by_nationality: { nationality: string; total: number }[];
  by_month: { month: string; total: number }[];
};

type IssuedCardRecord = {
  id: number;
  card_number: string;
  application_number: string;
  reference_number: string;
  applicant_name: string;
  forenames: string;
  surname: string;
  passport_number: string;
  nationality: string;
  application_category: string;
  card_category: string;
  issuance_status: string;
  issuance_status_label: string;
  issued_on: string;
  expires_on: string;
  issuing_officer: string;
  issuance_location: string;
};

type ReportIndexResponse = {
  data: IssuedCardRecord[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
};

type Filters = {
  from: string;
  to: string;
  status?: string;
  nationality?: string;
  card_category?: string;
  category?: string;
  search?: string;
};

type SavedFilter = { id: number; name: string; filters: Partial<Filters> };

const clean = (f: Record<string, string | number | undefined | null>) =>
  Object.fromEntries(
    Object.entries(f).filter(([, v]) => v !== undefined && v !== null && String(v).trim() !== "")
  ) as Record<string, string>;

export default function ReportsPage() {
  const user = useStaff();
  const { t } = useI18n();

  const [filters, setFilters] = useState<Filters>(() => ({
    from: `${new Date().getFullYear()}-01-01`,
    to: new Date().toISOString().slice(0, 10),
    status: "",
    nationality: "",
    card_category: "",
    search: "",
  }));

  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState("");
  const [formKey, setFormKey] = useState(0);
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7));
  const [message, setMessage] = useState<string | null>(null);

  // Sync search input with filter on mount / reset
  useEffect(() => {
    setSearchInput(filters.search || "");
  }, [filters.search]);

  const summaryParams = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    search: filters.search,
  }));

  const indexParams = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    search: filters.search,
    page,
    per_page: 10,
  }));

  const { data: summary, reload: reloadSummary } = useFetch<ReportSummary>(
    "staff",
    `reports/summary?${summaryParams.toString()}`
  );

  const { data: recordsData, reload: reloadRecords } = useFetch<ReportIndexResponse>(
    "staff",
    `reports?${indexParams.toString()}`
  );

  const saved = useFetch<{ data: SavedFilter[] }>("staff", "reports/filters");

  const canExport = hasRole(user, "SuperAdmin", "Admin", "Supervisor", "Auditor");
  const canManage = hasRole(user, "SuperAdmin", "Admin", "Supervisor", "Auditor");

  function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setPage(1);
    setFilters({
      from: String(f.get("from") || `${new Date().getFullYear()}-01-01`),
      to: String(f.get("to") || new Date().toISOString().slice(0, 10)),
      status: String(f.get("status") ?? ""),
      nationality: String(f.get("nationality") ?? "").trim(),
      card_category: String(f.get("card_category") ?? ""),
      search: searchInput.trim(),
    });
  }

  function handleReset() {
    const defaultFrom = `${new Date().getFullYear()}-01-01`;
    const defaultTo = new Date().toISOString().slice(0, 10);
    setSearchInput("");
    setPage(1);
    setFilters({
      from: defaultFrom,
      to: defaultTo,
      status: "",
      nationality: "",
      card_category: "",
      search: "",
    });
    setFormKey((k) => k + 1);
  }

  function applySaved(id: string) {
    const s = saved.data?.data.find((x) => String(x.id) === id);
    if (!s) return;
    setPage(1);
    setFilters((f) => ({
      ...f,
      status: "",
      nationality: "",
      card_category: "",
      search: "",
      ...s.filters,
    }));
    setFormKey((k) => k + 1);
  }

  async function save() {
    const name = prompt(t("Name for these filters (for example “Ghana – issued this year”)"));
    if (!name?.trim()) return;
    await api("staff", "reports/filters", {
      method: "POST",
      json: { name: name.trim(), filters: clean(filters) },
    });
    setMessage(`Filters saved as “${name.trim()}”.`);
    saved.reload();
  }

  async function remove(s: SavedFilter) {
    if (!confirm(`Delete the saved filters “${s.name}”?`)) return;
    await api("staff", `reports/filters/${s.id}`, { method: "DELETE" });
    saved.reload();
  }

  const exportQuery = new URLSearchParams(clean({
    from: filters.from,
    to: filters.to,
    status: filters.status,
    nationality: filters.nationality,
    card_category: filters.card_category,
    search: filters.search,
  })).toString();

  const maxMonth = Math.max(1, ...(summary?.by_month.map((m) => m.total) ?? [1]));
  const cards = recordsData?.data ?? [];
  const meta = recordsData?.meta ?? { current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 };

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("Issuance reports")}
        subtitle={t("Official registry of confirmed issued residence cards and operational analytics.")}
        actions={
          canExport && (
            <div className="flex flex-wrap items-center gap-2">
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=csv`)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                download
              >
                <svg className="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                {t("Export CSV")}
              </a>
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=xlsx`)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                download
              >
                <svg className="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {t("Export Excel")}
              </a>
              <a
                href={withBase(`/api/bff/staff/reports/export?${exportQuery}&format=pdf`)}
                className="inline-flex items-center gap-1.5 rounded-lg bg-nis-primary px-3.5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-nis-primary-dark transition-colors"
                target="_blank"
                rel="noreferrer"
              >
                <svg className="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                {t("Export PDF")}
              </a>
            </div>
          )
        }
      />

      {message && <Alert tone="success">{message}</Alert>}

      {/* Dynamic Accurate KPI Statistics */}
      {!summary ? (
        <div className="flex h-24 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-xs">
          <Spinner />
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("Total Cards Issued")}
            </div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-nis-primary tabular-nums">
              {summary.total_issued.toLocaleString()}
            </div>
            <div className="mt-0.5 text-[11px] text-slate-500">
              {t("Officially confirmed & issued")}
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("New Cards Issued")}
            </div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">
              {summary.new_issued.toLocaleString()}
            </div>
            <div className="mt-0.5 text-[11px] text-slate-500">
              {t("Initial residence cards")}
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("Renewed Cards Issued")}
            </div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">
              {summary.renewed_issued.toLocaleString()}
            </div>
            <div className="mt-0.5 text-[11px] text-slate-500">
              {t("Extensions & renewals")}
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              {t("Damaged / Replacement Cards Issued")}
            </div>
            <div className="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">
              {summary.replacement_issued.toLocaleString()}
            </div>
            <div className="mt-0.5 text-[11px] text-slate-500">
              {t("Lost or damaged replacements")}
            </div>
          </div>
        </div>
      )}

      {/* Filter and Search Panel */}
      <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <form key={formKey} onSubmit={apply} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <div>
              <Field label={t("From")}>
                <Input type="date" name="from" defaultValue={filters.from} />
              </Field>
            </div>

            <div>
              <Field label={t("To")}>
                <Input type="date" name="to" defaultValue={filters.to} />
              </Field>
            </div>

            <div>
              <Field label={t("Nationality")}>
                <Select name="nationality" defaultValue={filters.nationality ?? ""}>
                  <option value="">{t("All Nationalities")}</option>
                  {ECOWAS_NATIONALITIES.map((n) => (
                    <option key={n.value} value={n.value}>
                      {t(n.label)}
                    </option>
                  ))}
                </Select>
              </Field>
            </div>

            <div>
              <Field label={t("Card Category")}>
                <Select name="card_category" defaultValue={filters.card_category ?? ""}>
                  <option value="">{t("All Card Types")}</option>
                  <option value="NEW">{t("New")}</option>
                  <option value="RENEWAL">{t("Renewal")}</option>
                  <option value="REPLACE">{t("Replacement")}</option>
                </Select>
              </Field>
            </div>

            <div>
              <Field label={t("Search")}>
                <Input
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                  placeholder={t("Card no, App no, Passport, Name")}
                />
              </Field>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
            <div className="flex flex-wrap items-center gap-2">
              <Button type="submit">{t("Apply")}</Button>
              <Button type="button" variant="secondary" onClick={handleReset}>
                {t("Reset")}
              </Button>
              <Button type="button" variant="secondary" onClick={save}>
                {t("Save filters")}
              </Button>
            </div>

            {!!saved.data?.data.length && (
              <div className="flex items-center gap-2 text-xs text-slate-600">
                <span>{t("Saved filters")}:</span>
                <Select
                  defaultValue=""
                  onChange={(e) => applySaved(e.target.value)}
                  className="!py-1 !text-xs"
                >
                  <option value="">{t("Choose…")}</option>
                  {saved.data.data.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Select>
              </div>
            )}
          </div>
        </form>

        {!!saved.data?.data.length && (
          <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
            <span>{t("Delete saved")}:</span>
            {saved.data.data.map((s, i) => (
              <span key={s.id}>
                {i > 0 && ", "}
                <button
                  type="button"
                  className="text-red-600 hover:underline"
                  onClick={() => remove(s)}
                >
                  {s.name}
                </button>
              </span>
            ))}
          </div>
        )}
      </div>

      {/* Main Registry Dossier Table */}
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
        <div className="border-b border-slate-200 bg-slate-50/70 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-slate-900">
              {t("Confirmed Issued Residence Cards")}
            </h3>
            <p className="text-xs text-slate-500">
              {meta.total > 0
                ? `Showing ${meta.from} to ${meta.to} of ${meta.total} issued cards`
                : t("Showing 0 issued cards")}
            </p>
          </div>
        </div>

        {!recordsData ? (
          <div className="flex h-40 items-center justify-center p-8">
            <Spinner />
          </div>
        ) : cards.length === 0 ? (
          <div className="p-12 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <p className="mt-3 text-sm font-semibold text-slate-900">
              {t("No issued residence cards found")}
            </p>
            <p className="mt-1 text-xs text-slate-500 max-w-md mx-auto">
              {filters.search
                ? t("No issued cards match your current search or date filters.")
                : t("Residence cards will appear here once officially handed over and issued at the Card Issuance desk.")}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="w-12 px-3 py-3 text-center">S/N</th>
                  <th className="px-3 py-3">{t("Card Number")}</th>
                  <th className="px-3 py-3">{t("Application Ref")}</th>
                  <th className="px-3 py-3">{t("Applicant Name")}</th>
                  <th className="px-3 py-3">{t("Passport Number")}</th>
                  <th className="px-3 py-3">{t("Nationality")}</th>
                  <th className="px-3 py-3">{t("Category")}</th>
                  <th className="px-3 py-3">{t("Card Type")}</th>
                  <th className="px-3 py-3">{t("Date of Issuance")}</th>
                  <th className="px-3 py-3">{t("Issuing Officer")}</th>
                  <th className="px-3 py-3">{t("Issuance Location")}</th>
                  <th className="px-3 py-3 text-center">{t("Status")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-xs">
                {cards.map((card, idx) => {
                  const continuousSn = (meta.current_page - 1) * meta.per_page + idx + 1;
                  return (
                    <tr key={card.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-3 py-3 text-center font-medium text-slate-400">
                        {continuousSn}
                      </td>
                      <td className="px-3 py-3 font-mono font-semibold text-nis-primary">
                        {card.card_number}
                      </td>
                      <td className="px-3 py-3 font-mono text-slate-600">
                        {card.application_number}
                      </td>
                      <td className="px-3 py-3 font-medium text-slate-900">
                        {card.applicant_name}
                      </td>
                      <td className="px-3 py-3 font-mono text-slate-600">
                        {card.passport_number}
                      </td>
                      <td className="px-3 py-3 text-slate-700">
                        {card.nationality}
                      </td>
                      <td className="px-3 py-3 text-slate-600">
                        {card.application_category}
                      </td>
                      <td className="px-3 py-3 text-slate-600">
                        <span className="inline-block rounded px-2 py-0.5 text-[10px] font-medium bg-slate-100 text-slate-700">
                          {card.card_category}
                        </span>
                      </td>
                      <td className="px-3 py-3 tabular-nums text-slate-600">
                        {card.issued_on}
                      </td>
                      <td className="px-3 py-3 text-slate-600">
                        {card.issuing_officer}
                      </td>
                      <td className="px-3 py-3 text-slate-600">
                        {card.issuance_location}
                      </td>
                      <td className="px-3 py-3 text-center">
                        <span className="inline-block rounded px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                          {card.issuance_status_label}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* 10 per page Pagination Controls */}
        {meta.last_page > 1 && (
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/50 px-5 py-3 text-xs text-slate-600">
            <div>
              Showing {meta.from} to {meta.to} of {meta.total} records
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                disabled={meta.current_page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {t("Previous")}
              </button>

              {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                .filter((p) => p === 1 || p === meta.last_page || Math.abs(p - meta.current_page) <= 2)
                .map((p, idx, arr) => (
                  <span key={p} className="flex items-center">
                    {idx > 0 && arr[idx - 1] !== p - 1 && (
                      <span className="px-1 text-slate-400">…</span>
                    )}
                    <button
                      type="button"
                      onClick={() => setPage(p)}
                      className={`h-7 w-7 rounded-md text-xs font-semibold transition-colors ${
                        p === meta.current_page
                          ? "bg-nis-primary text-white"
                          : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                      }`}
                    >
                      {p}
                    </button>
                  </span>
                ))}

              <button
                type="button"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {t("Next")}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Issuance Trends & Distribution Charts */}
      {summary && (
        <div className="grid gap-6 lg:grid-cols-2">
          <Panel title={t("Cards issued per month")}>
            {summary.by_month.length === 0 ? (
              <p className="text-sm text-slate-500 py-6 text-center">{t("No data available.")}</p>
            ) : (
              <div className="flex h-48 items-end gap-2 pt-4">
                {summary.by_month.map((m) => (
                  <div key={m.month} className="flex flex-1 flex-col items-center gap-1">
                    <span className="text-xs tabular-nums text-slate-600 font-semibold">{m.total}</span>
                    <div
                      className="w-full rounded-t bg-nis-green transition-all"
                      style={{ height: `${(m.total / maxMonth) * 140}px` }}
                      title={`${m.month}: ${m.total}`}
                    />
                    <span className="text-[10px] text-slate-500">{m.month.slice(2)}</span>
                  </div>
                ))}
              </div>
            )}
          </Panel>

          <Panel title={t("By nationality")}>
            {summary.by_nationality.length === 0 ? (
              <p className="text-sm text-slate-500 py-6 text-center">{t("No data available.")}</p>
            ) : (
              <div className="max-h-56 overflow-y-auto">
                <table className="w-full text-sm">
                  <tbody>
                    {summary.by_nationality.map((n) => (
                      <tr key={n.nationality} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/50">
                        <td className="py-2 text-slate-800 font-medium">{t(n.nationality)}</td>
                        <td className="text-right tabular-nums font-semibold text-slate-900">{n.total}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </div>
      )}

      {/* Monthly Management Report Section */}
      {canManage && (
        <Panel title={t("Monthly management report")}>
          <div className="flex flex-wrap items-end gap-3">
            <Field label={t("Month")}>
              <Input
                type="month"
                value={month}
                max={new Date().toISOString().slice(0, 7)}
                onChange={(e) => setMonth(e.target.value)}
              />
            </Field>
            <a
              href={withBase(`/api/bff/staff/reports/management?month=${month}&format=pdf`)}
              className="rounded-md bg-nis-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-nis-primary-dark transition-colors shadow-xs"
              target="_blank"
              rel="noreferrer"
            >
              {t("Download PDF")}
            </a>
            <a
              href={withBase(`/api/bff/staff/reports/management?month=${month}&format=xlsx`)}
              className="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:border-nis-primary hover:text-nis-primary transition-colors shadow-xs"
              download
            >
              {t("Download Excel")}
            </a>
            <p className="basis-full text-xs text-slate-500">
              Applications, service-level performance, cards, fees, card production and centre queues for the selected month.
            </p>
          </div>
        </Panel>
      )}
    </div>
  );
}
