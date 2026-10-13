"use client";

import { useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { Button, Input, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";
import { dateTime } from "@/lib/format";

type Log = { id: number; actor_label: string; action: string; description: string | null; ip_address: string | null; created_at: string };
type Page = { data: Log[]; current_page: number; last_page: number; total: number };

export default function AuditLogsPage() {
  const [query, setQuery] = useState({ search: "", page: "1" });
  const { data: page } = useFetch<Page>("staff", `audit-logs?${new URLSearchParams(query)}`);

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setQuery({ search: String(new FormData(event.currentTarget).get("search") ?? ""), page: "1" });
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Audit trail" subtitle="Append-only record of every sign-in, decision and change." />
      <form onSubmit={search} className="flex gap-2">
        <Input name="search" placeholder="Search officer or description" defaultValue={query.search} />
        <Button type="submit">Search</Button>
      </form>
      {!page ? <Spinner /> : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th className="px-4 py-3">When</th><th className="px-4 py-3">Who</th><th className="px-4 py-3">Action</th><th className="px-4 py-3">Details</th><th className="px-4 py-3">IP</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {page.data.map((l) => (
                <tr key={l.id}>
                  <td className="whitespace-nowrap px-4 py-2.5">{dateTime(l.created_at)}</td>
                  <td className="px-4 py-2.5">{l.actor_label}</td>
                  <td className="px-4 py-2.5 font-mono text-xs">{l.action}</td>
                  <td className="px-4 py-2.5">{l.description}</td>
                  <td className="px-4 py-2.5 font-mono text-xs">{l.ip_address}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {page && page.last_page > 1 && (
        <div className="flex justify-between text-sm">
          <span>Page {page.current_page} of {page.last_page}</span>
          <div className="flex gap-3">
            {page.current_page > 1 && <button className="underline" onClick={() => setQuery({ ...query, page: String(page.current_page - 1) })}>Previous</button>}
            {page.current_page < page.last_page && <button className="underline" onClick={() => setQuery({ ...query, page: String(page.current_page + 1) })}>Next</button>}
          </div>
        </div>
      )}
    </div>
  );
}
