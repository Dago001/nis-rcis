"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";
import { PrintableCard, type PrintData, type Stock } from "@/components/PrintableCard";
import { PrintResult } from "@/components/PrintResult";
import { Alert, Button, Spinner } from "@/components/ui";
import { useFetch } from "@/lib/use-fetch";

function Batch() {
  const ids = useSearchParams().get("ids") ?? "";
  const { data, error } = useFetch<{ data: PrintData[]; stock: Stock }>("staff", ids ? `cards/print-batch?ids=${encodeURIComponent(ids)}` : null);

  if (!ids) return <Alert tone="info">Choose cards to print from the <Link href="/staff/cards?unprinted=1" className="underline">card register</Link>.</Alert>;
  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!data) return <Spinner />;

  return (
    <div className="space-y-6">
      <div className="no-print flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-600">
          Batch of <strong>{data.data.length}</strong> card(s). Blank cards in stock: <strong>{data.stock.remaining}</strong>. Print at 100% scale on CR80 card stock.
        </p>
        <Button onClick={() => window.print()} disabled={data.stock.remaining < data.data.length}>Print all</Button>
      </div>
      {data.stock.remaining < data.data.length && <Alert tone="danger">Not enough blank cards in stock for this batch. Record the cards received under Card stock.</Alert>}
      <div className="space-y-6">
        {data.data.map((p) => <PrintableCard key={p.data.id} p={p} />)}
      </div>
      <div className="no-print max-w-2xl">
        <PrintResult cards={data.data.map((p) => ({ id: p.data.id, card_number: p.data.card_number }))} />
      </div>
    </div>
  );
}

export default function BatchPrintPage() {
  return <Suspense fallback={<Spinner />}><Batch /></Suspense>;
}
