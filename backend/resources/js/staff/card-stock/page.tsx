"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import type { Stock } from "@/components/PrintableCard";
import { Alert, Button, Field, Input, Panel, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, nisDate } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type StockData = {
  stock: Stock;
  batches: { id: number; batch_number: string; quantity: number; serial_from: string | null; serial_to: string | null; received_on: string; received_by: string | null; notes: string | null; printed: number; spoiled: number; remaining: number }[];
  jobs: { id: number; outcome: "PRINTED" | "SPOILED"; spoil_reason: string | null; card: { id: number; card_number: string; holder: string } | null; batch: string | null; printed_by: string | null; at: string }[];
};

/** Blank card stock and the record of every card printed or spoiled. */
export default function CardStockPage() {
  const { data, reload } = useFetch<StockData>("staff", "card-stock");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function receive(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setErrors({});
    try {
      const body = Object.fromEntries([...new FormData(form).entries()].filter(([, v]) => v !== ""));
      await api("staff", "card-stock/batches", { method: "POST", json: body });
      form.reset();
      setNotice("Blank card stock recorded.");
      reload();
    } catch (e) {
      setErrors(e instanceof ApiError ? { ...e.fieldErrors(), _: e.message } : { _: String(e) });
    } finally {
      setBusy(false);
    }
  }

  if (!data) return <Spinner />;
  const s = data.stock;

  return (
    <div className="space-y-6">
      <PageTitle
        title="Card stock and printing"
        subtitle={`${s.remaining} blank cards left · ${s.received} received · ${s.printed} printed · ${s.spoiled} spoiled`}
        actions={<Link href="/staff/cards?unprinted=1" className="rounded-lg bg-nis-primary px-3 py-2 text-sm font-medium text-white">Print waiting cards</Link>}
      />
      {s.low && <Alert tone="danger">Low stock: fewer than {s.threshold} blank cards left. Order more card stock.</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}

      <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
        <Panel title="Record blank cards received">
          <form onSubmit={receive} className="space-y-3">
            {errors._ && !Object.keys(errors).some((k) => k !== "_") && <Alert tone="danger">{errors._}</Alert>}
            <Field label="Batch / delivery number" required error={errors.batch_number}><Input name="batch_number" required maxLength={50} /></Field>
            <Field label="Number of blank cards" required error={errors.quantity}><Input name="quantity" type="number" min={1} max={100000} required /></Field>
            <div className="grid grid-cols-2 gap-2">
              <Field label="First serial" error={errors.serial_from}><Input name="serial_from" maxLength={30} /></Field>
              <Field label="Last serial" error={errors.serial_to}><Input name="serial_to" maxLength={30} /></Field>
            </div>
            <Field label="Date received" required error={errors.received_on}><Input name="received_on" type="date" required defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
            <Field label="Notes" error={errors.notes}><Input name="notes" maxLength={500} /></Field>
            <Button type="submit" disabled={busy}>Record stock</Button>
          </form>
        </Panel>

        <Panel title="Batches">
          {data.batches.length === 0 ? <p className="text-sm text-slate-600">No blank card stock recorded yet. Cards cannot be printed until stock is recorded.</p> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr><th className="py-2">Batch</th><th>Received</th><th className="text-right">Quantity</th><th className="text-right">Printed</th><th className="text-right">Spoiled</th><th className="text-right">Left</th></tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {data.batches.map((b) => (
                    <tr key={b.id}>
                      <td className="py-2 font-medium">{b.batch_number}<div className="text-xs text-slate-500">{[b.serial_from, b.serial_to].filter(Boolean).join(" – ")}{b.notes ? ` · ${b.notes}` : ""}</div></td>
                      <td>{nisDate(b.received_on)}<div className="text-xs text-slate-500">{b.received_by}</div></td>
                      <td className="text-right tabular-nums">{b.quantity}</td>
                      <td className="text-right tabular-nums">{b.printed}</td>
                      <td className="text-right tabular-nums">{b.spoiled}</td>
                      <td className="text-right font-semibold tabular-nums">{b.remaining}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      </div>

      <Panel title="Print log (latest 100)">
        {data.jobs.length === 0 ? <p className="text-sm text-slate-600">No cards printed yet.</p> : (
          <ul className="divide-y divide-slate-100 text-sm">
            {data.jobs.map((j) => (
              <li key={j.id} className="flex flex-wrap justify-between gap-2 py-2">
                <span>
                  {j.card && <Link href={`/staff/cards/${j.card.id}`} className="font-medium text-nis-primary hover:underline">{j.card.card_number}</Link>} {j.card?.holder} ·{" "}
                  <span className={j.outcome === "PRINTED" ? "text-nis-primary" : "text-red-700"}>{j.outcome === "PRINTED" ? "printed" : `spoiled: ${j.spoil_reason}`}</span>
                </span>
                <span className="text-slate-500">{j.printed_by} · batch {j.batch ?? "—"} · {dateTime(j.at)}</span>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
