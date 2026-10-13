"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Input, Spinner } from "@/components/ui";
import { dateTime, naira } from "@/lib/format";
import type { StaffPayment } from "@/lib/types";
import { useFetch } from "@/lib/use-fetch";

const TABS: [string, string][] = [
  ["awaiting_submission", "Paid – not yet submitted"],
  ["submitted", "Paid – submitted"],
  ["failed", "Failed"],
  ["all", "All"],
];

type Result = { data: StaffPayment[]; meta: { current_page: number; last_page: number; total: number } };

function Payments() {
  const router = useRouter();
  const params = useSearchParams();
  const filter = params.get("filter") ?? "awaiting_submission";
  const search = params.get("search") ?? "";
  const page = params.get("page") ?? "1";
  const { data: result, error } = useFetch<Result>("staff", `payments?${new URLSearchParams({ filter, page, ...(search ? { search } : {}) })}`);

  function onSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const q = String(new FormData(event.currentTarget).get("search") ?? "").trim();
    router.push(`/staff/payments?${new URLSearchParams({ filter: "all", ...(q ? { search: q } : {}) })}`);
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Fee payments" subtitle="Residence card fees confirmed by Paystack. Applicants appear here as soon as they pay, before they finish the application." />

      <form onSubmit={onSearch} className="flex gap-2">
        <Input name="search" defaultValue={search} placeholder="Payment reference, applicant name or e-mail" />
        <Button type="submit">Search</Button>
      </form>

      <div className="flex gap-1 overflow-x-auto border-b border-slate-200">
        {TABS.map(([value, label]) => (
          <Link
            key={value}
            href={`/staff/payments?filter=${value}`}
            className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${filter === value && !search ? "border-nis-primary text-nis-primary" : "border-transparent text-slate-600 hover:text-slate-900"}`}
          >
            {label}
          </Link>
        ))}
      </div>

      {error ? (
        <p className="text-sm text-red-700">{error}</p>
      ) : !result ? (
        <Spinner />
      ) : result.data.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-600">No payments found.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="text-left">
              <tr>
                <th className="px-4 py-3">Payment</th>
                <th className="px-4 py-3">Applicant</th>
                <th className="px-4 py-3">Amount</th>
                <th className="px-4 py-3">Paid</th>
                <th className="px-4 py-3">Application</th>
              </tr>
            </thead>
            <tbody>
              {result.data.map((p) => (
                <tr key={p.id}>
                  <td className="px-4 py-3">
                    <Link href={`/staff/payments/${p.id}`} className="font-medium text-nis-primary hover:underline">{p.reference}</Link>
                    <div className="text-xs text-slate-500">{p.channel === "fake" ? "Test simulation" : p.channel ?? "—"}</div>
                  </td>
                  <td className="px-4 py-3">{p.applicant?.name ?? "—"}<div className="text-xs text-slate-500">{p.applicant?.email}</div></td>
                  <td className="px-4 py-3 tabular-nums">{naira(p.amount_naira)}</td>
                  <td className="px-4 py-3">{p.status === "PAID" ? dateTime(p.paid_at) : <StatusBadge status="REJECTED" label={p.status} />}</td>
                  <td className="px-4 py-3">
                    {p.application ? (
                      <Link href={`/staff/applications/${p.application.id}`} className="font-medium text-nis-primary hover:underline">{p.application.application_number}</Link>
                    ) : p.progress ? (
                      <span className="text-slate-700">In progress: step {p.progress.current_step} of 7<div className="text-xs text-slate-500">{p.progress.step_label}</div></span>
                    ) : (
                      <span className="text-slate-500">—</span>
                    )}
                    {p.application && <div className="mt-1"><StatusBadge status={p.application.status} label={p.application.status_label} /></div>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {result && result.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-600">Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} payments</span>
          <div className="flex gap-2">
            {result.meta.current_page > 1 && <Link className="underline" href={`?${new URLSearchParams({ filter, search, page: String(result.meta.current_page - 1) })}`}>Previous</Link>}
            {result.meta.current_page < result.meta.last_page && <Link className="underline" href={`?${new URLSearchParams({ filter, search, page: String(result.meta.current_page + 1) })}`}>Next</Link>}
          </div>
        </div>
      )}
    </div>
  );
}

export default function PaymentsPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <Payments />
    </Suspense>
  );
}
