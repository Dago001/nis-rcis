"use client";

import { useCallback, useEffect, useState } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Input, Panel, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { useI18n } from "@/components/I18nProvider";

interface ActivityLog {
  id: number;
  actor_label: string;
  action: string;
  description: string;
  context: Record<string, any> | null;
  ip_address: string | null;
  created_at: string;
}

interface ActivityResponse {
  data: ActivityLog[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export default function MyActivityPage() {
  const me = useStaff();
  const { t } = useI18n();
  const [logs, setLogs] = useState<ActivityLog[] | null>(null);
  const [total, setTotal] = useState<number>(0);
  const [page, setPage] = useState<number>(1);
  const [lastPage, setLastPage] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState<string>("");

  const loadLogs = useCallback(
    (targetPage = 1, searchQuery = "") => {
      setLoading(true);
      const params = new URLSearchParams({
        page: String(targetPage),
        ...(searchQuery ? { search: searchQuery } : {}),
      });

      api<ActivityResponse>("staff", `my-activity?${params.toString()}`)
        .then((res) => {
          setLogs(res.data || []);
          setTotal(res.total || 0);
          setPage(res.current_page || 1);
          setLastPage(res.last_page || 1);
          setError(null);
        })
        .catch((err) => {
          setError(err.message || "Failed to load activity logs.");
        })
        .finally(() => setLoading(false));
    },
    []
  );

  useEffect(() => {
    loadLogs(page, search);
  }, [loadLogs, page]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadLogs(1, search);
  };

  return (
    <div className="space-y-6">
      <PageTitle
        title={t("My Activity")}
        subtitle={t("Personal operational audit trail for {name}", {
          name: me.rank ? `${me.rank} ${me.fullname}` : me.fullname,
        })}
      />

      <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-900">
        <span className="font-bold uppercase tracking-wider">{t("Audit Integrity Notice")}:</span>{" "}
        {t("This log displays authenticated operational actions initiated under your NIS service credentials ({service_no}). All entries are cryptographically signed and stored in immutable database tables for accountability.", {
          service_no: me.service_number || "Verified Officer",
        })}
      </div>

      {error && <Alert tone="danger">{error}</Alert>}

      <Panel>
        <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
          <div>
            <h2 className="text-sm font-semibold text-slate-900">{t("Recent Operational Actions")}</h2>
            <p className="text-xs text-slate-500">
              {t("Showing {count} logged events", { count: total })}
            </p>
          </div>
          <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
            <Input
              type="search"
              placeholder={t("Filter actions or description…")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="!w-full !py-1.5 sm:!w-64 text-xs"
            />
            <Button type="submit" variant="outline" className="!py-1.5 text-xs">
              {t("Filter")}
            </Button>
          </form>
        </header>

        {loading ? (
          <div className="py-12 text-center">
            <Spinner />
          </div>
        ) : !logs || logs.length === 0 ? (
          <div className="py-12 text-center text-sm text-slate-500">
            {t("No activity records found.")}
          </div>
        ) : (
          <div className="overflow-x-auto -mx-5">
            <table className="w-full text-left text-xs sm:text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="pl-5 py-3 w-12">#</th>
                  <th className="py-3">{t("Timestamp")}</th>
                  <th className="py-3">{t("Action")}</th>
                  <th className="py-3">{t("Description")}</th>
                  <th className="pr-5 py-3 text-right">{t("Client IP")}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {logs.map((item, idx) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 transition">
                    <td className="pl-5 py-3 text-slate-400 font-mono">
                      {(page - 1) * 25 + idx + 1}
                    </td>
                    <td className="py-3 font-mono text-xs text-slate-600 whitespace-nowrap">
                      {dateTime(item.created_at)}
                    </td>
                    <td className="py-3">
                      <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">
                        {item.action}
                      </span>
                    </td>
                    <td className="py-3 text-slate-800 font-medium">
                      {item.description}
                    </td>
                    <td className="pr-5 py-3 text-right font-mono text-xs text-slate-500">
                      {item.ip_address || "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {lastPage > 1 && (
          <footer className="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-600">
            <div>
              {t("Page {current} of {last}", { current: page, last: lastPage })}
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="outline"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="!py-1 !px-2.5 text-xs"
              >
                {t("Previous")}
              </Button>
              <Button
                variant="outline"
                disabled={page >= lastPage}
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                className="!py-1 !px-2.5 text-xs"
              >
                {t("Next")}
              </Button>
            </div>
          </footer>
        )}
      </Panel>
    </div>
  );
}
