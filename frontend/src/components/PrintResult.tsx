"use client";

import { useState } from "react";
import { Alert, Button, Input, Panel } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";

type Outcome = "PRINTED" | "SPOILED";

/**
 * After printing, the officer records what happened to each blank card:
 * printed correctly, or spoiled (with a reason). Every blank card is accounted for.
 */
export function PrintResult({ cards }: { cards: { id: number; card_number: string }[] }) {
  const [done, setDone] = useState<Record<number, Outcome>>({});
  const [reasons, setReasons] = useState<Record<number, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [remaining, setRemaining] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);

  async function record(id: number, outcome: Outcome) {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ stock: { remaining: number } }>("staff", `cards/${id}/print-jobs`, { method: "POST", json: { outcome, reason: reasons[id] || undefined } });
      setDone((d) => ({ ...d, [id]: outcome }));
      setRemaining(r.stock.remaining);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Panel title="Record the print result" actions={remaining !== null && <span className="text-sm text-slate-600">Blank cards left: <strong>{remaining}</strong></span>}>
      <div className="space-y-3 text-sm">
        <p className="text-slate-600">Every blank card used must be recorded. If a card came out wrong, record it as <strong>spoiled</strong> with the reason, then print again.</p>
        {error && <Alert tone="danger">{error}</Alert>}
        <ul className="divide-y divide-slate-100">
          {cards.map((c) => (
            <li key={c.id} className="flex flex-wrap items-center gap-2 py-2">
              <span className="w-28 font-medium">Card {c.card_number}</span>
              {done[c.id] ? (
                <span className={done[c.id] === "PRINTED" ? "text-nis-primary" : "text-red-700"}>
                  {done[c.id] === "PRINTED" ? "✓ Recorded as printed" : "Recorded as spoiled — print it again"}
                  {done[c.id] === "SPOILED" && <button className="ml-2 underline" onClick={() => setDone((d) => Object.fromEntries(Object.entries(d).filter(([k]) => k !== String(c.id))))}>Record the reprint</button>}
                </span>
              ) : (
                <>
                  <Button disabled={busy} onClick={() => record(c.id, "PRINTED")}>Printed correctly</Button>
                  <Input className="!w-56" placeholder="Reason if spoiled" value={reasons[c.id] ?? ""} onChange={(e) => setReasons((r) => ({ ...r, [c.id]: e.target.value }))} aria-label={`Spoil reason for card ${c.card_number}`} />
                  <Button variant="danger" disabled={busy || !(reasons[c.id] ?? "").trim()} onClick={() => record(c.id, "SPOILED")}>Spoiled</Button>
                </>
              )}
            </li>
          ))}
        </ul>
      </div>
    </Panel>
  );
}
