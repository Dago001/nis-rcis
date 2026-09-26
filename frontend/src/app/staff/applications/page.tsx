"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Input, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";
import { nisDate, applicationType } from "@/lib/format";
import type { Application, Paginated } from "@/lib/types";

const TABS: [string, string][] = [
  ["PENDING_APPROVAL", "Awaiting approval"],
  ["QUERIED", "Queried"],
  ["APPROVED_FOR_BIOMETRICS", "Biometrics"],
  ["BIOMETRICS_CAPTURED", "In production"],
  ["READY_FOR_COLLECTION", "Ready"],
  ["ISSUED", "Collected"],
  ["REJECTED", "Rejected"],
  ["", "All"],
];

function Queue() {
  const router = useRouter();
  const params = useSearchParams();
  const status = params.get("status") ?? "PENDING_APPROVAL";
  const search = params.get("search") ?? "";
  const page = params.get("page") ?? "1";
  const query = new URLSearchParams({ page, ...(status && !search ? { status } : {}), ...(search ? { search } : {}) });
  const { data: result } = useFetch<Paginated<Application>>("staff", `applications?${query}`);

  function onSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const q = String(new FormData(event.currentTarget).get("search") ?? "").trim();
    router.push(`/staff/applications?${new URLSearchParams(q ? { search: q } : { status })}`);
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Approval queue" subtitle="Online and assisted residence card applications" />

      <form onSubmit={onSearch} className="flex gap-2">
        <Input name="search" defaultValue={search} placeholder="Application no., reference, passport, name — or scan the slip QR code" autoFocus />
        <Button type="submit">Search</Button>
      </form>

      <div className="flex gap-1 overflow-x-auto border-b border-slate-200">
        {TABS.map(([value, label]) => (
          <Link
            key={label}
            href={`/staff/applications?status=${value}`}
            className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${!search && status === value ? "border-nis-green text-nis-green" : "border-transparent text-slate-600 hover:text-slate-900"}`}
          >
            {label}
          </Link>
        ))}
      </div>

      {!result ? (
        <Spinner />
      ) : result.data.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-600">No applications found.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Application</th>
                <th className="px-4 py-3">Applicant</th>
                <th className="px-4 py-3">Nationality</th>
                <th className="px-4 py-3">Appointment</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {result.data.map((a) => (
                <tr key={a.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <Link href={`/staff/applications/${a.id}`} className="font-semibold text-nis-green hover:underline">{a.application_number}</Link>
                    {(a.risk_flags?.length ?? 0) > 0 && <span className="ml-2 rounded bg-nis-red px-1.5 text-[11px] font-semibold text-white" title={a.risk_flags!.map((f) => f.message).join("\n")}>⚠ {a.risk_flags!.length}</span>}
                    <div className="text-xs text-slate-500">{a.channel === "ASSISTED" ? "Assisted" : "Online"} · {applicationType(a)}</div>
                  </td>
                  <td className="px-4 py-3">{a.surname}, {a.forenames}<div className="text-xs text-slate-500">{a.passport_number}</div></td>
                  <td className="px-4 py-3">{a.nationality}</td>
                  <td className="px-4 py-3">{nisDate(a.appointment_date)}<div className="text-xs text-slate-500">{a.appointment_time} · {a.enrollment_center?.code}</div></td>
                  <td className="px-4 py-3"><StatusBadge status={a.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {result?.meta && result.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-600">Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} applications</span>
          <div className="flex gap-2">
            {result.meta.current_page > 1 && <Link className="underline" href={`?${new URLSearchParams({ status, search, page: String(result.meta.current_page - 1) })}`}>Previous</Link>}
            {result.meta.current_page < result.meta.last_page && <Link className="underline" href={`?${new URLSearchParams({ status, search, page: String(result.meta.current_page + 1) })}`}>Next</Link>}
          </div>
        </div>
      )}
    </div>
  );
}

export default function QueuePage() {
  return (
    <Suspense fallback={<Spinner />}>
      <Queue />
    </Suspense>
  );
}
