"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Button, Input, Select, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";
import { nisDate } from "@/lib/format";
import type { Card, Paginated } from "@/lib/types";

function CardRegister() {
  const router = useRouter();
  const params = useSearchParams();
  const { data: result } = useFetch<Paginated<Card>>("staff", `cards?${params.toString()}`);

  function filter(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const entries = [...new FormData(event.currentTarget).entries()].filter(([, v]) => v !== "") as [string, string][];
    router.push(`/staff/cards?${new URLSearchParams(entries)}`);
  }

  return (
    <div className="space-y-5">
      <PageTitle title="Residence card register" />
      <form onSubmit={filter} className="grid gap-2 sm:grid-cols-[1fr_200px_auto]">
        <Input name="search" defaultValue={params.get("search") ?? ""} placeholder="Card no., booklet no., passport, name" />
        <Select name="status" defaultValue={params.get("status") ?? ""}>
          <option value="">All statuses</option>
          <option value="APPROVED">Awaiting approval</option>
          <option value="QUERIED">Queried</option>
          <option value="ISSUED">Issued</option>
          <option value="RENEWED">Renewed</option>
          <option value="REVOKED">Revoked</option>
        </Select>
        <Button type="submit">Filter</Button>
      </form>

      {!result ? (
        <Spinner />
      ) : result.data.length === 0 ? (
        <p className="py-8 text-center text-sm text-slate-600">No cards found.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr><th className="px-4 py-3">Card</th><th className="px-4 py-3">Holder</th><th className="px-4 py-3">Nationality</th><th className="px-4 py-3">Expires</th><th className="px-4 py-3">Status</th></tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {result.data.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3"><Link href={`/staff/cards/${c.id}`} className="font-semibold text-nis-green hover:underline">{c.card_number}</Link><div className="text-xs text-slate-500">{c.booklet_number}</div></td>
                  <td className="px-4 py-3">{c.surname}, {c.forenames}<div className="text-xs text-slate-500">{c.passport_number}</div></td>
                  <td className="px-4 py-3">{c.nationality}</td>
                  <td className="px-4 py-3">{nisDate(c.expires_on)}</td>
                  <td className="space-x-1 px-4 py-3"><StatusBadge status={c.status} />{c.is_watchlisted && <StatusBadge status="WATCHLISTED" />}{c.verification_status === "EXPIRED" && <StatusBadge status="EXPIRED" />}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default function CardsPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <CardRegister />
    </Suspense>
  );
}
