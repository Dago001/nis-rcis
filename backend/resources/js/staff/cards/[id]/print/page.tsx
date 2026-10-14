"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { CardPrintPages, PrintableCard, type PrintData } from "@/components/PrintableCard";
import { PrintResult } from "@/components/PrintResult";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";

/** Print one card, then record whether the blank card was printed or spoiled. */
export default function PrintCardPage() {
  const { id } = useParams<{ id: string }>();
  const [p, setP] = useState<PrintData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<PrintData>("staff", `cards/${id}/print`).then(setP).catch((e) => setError(e.message));
  }, [id]);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!p) return <Spinner />;

  return (
    <div className="space-y-6">
      <div className="no-print flex items-center justify-end gap-3">
        <Button onClick={() => window.print()}>Print</Button>
      </div>
      <PrintableCard p={p} />
      <CardPrintPages cards={[p]} />
      <div className="no-print max-w-2xl">
        <PrintResult cards={[{ id: p.data.id, card_number: p.data.card_number }]} />
      </div>
    </div>
  );
}
