"use client";

import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Input, Panel } from "@/components/ui";
import { api } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";
import { dateTime, nisDate } from "@/lib/format";

type Verification = {
  status: string;
  card_number: string;
  holder: string;
  nationality: string;
  expires_on: string;
  issued_on: string;
  photo_url: string | null;
  checked_at: string;
};

const explain: Record<string, [string, "success" | "warning" | "danger" | "info"]> = {
  VALID: ["This residence card is genuine and currently valid.", "success"],
  EXPIRED: ["This residence card has expired. The holder must regularise their status.", "warning"],
  REVOKED: ["This residence card has been REVOKED and is no longer valid.", "danger"],
  REPORTED_LOST: ["This card has been reported LOST by its holder and is not valid. Do not accept it.", "danger"],
  REPORTED_STOLEN: ["This card has been reported STOLEN and is not valid. Do not accept it; refer the bearer to the police or the nearest NIS office.", "danger"],
  REFER_TO_NIS: ["Do not accept this card. Refer the holder to the nearest NIS office.", "danger"],
  NOT_ISSUED: ["This card has not been issued yet.", "warning"],
};

function Verify() {
  const token = useSearchParams().get("token");
  const byToken = useFetch<Verification>("public", token ? `verify-card?${new URLSearchParams({ token })}` : null);
  const [manual, setManual] = useState<Verification | null>(null);
  const [manualError, setManualError] = useState<string | null>(null);

  const result = token ? byToken.data : manual;
  const error = token ? byToken.error : manualError;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setManual(null);
    setManualError(null);
    const query = new URLSearchParams(Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>);
    try {
      setManual(await api<Verification>("public", `verify-card?${query}`));
    } catch (e) {
      setManualError((e as Error).message);
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="text-xl font-semibold">Verify a residence card</h1>
      {!token && (
        <form onSubmit={submit} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <Field label="Card number"><Input name="card_number" required /></Field>
          <Field label="Passport number"><Input name="passport_number" required /></Field>
          <Button type="submit">Verify</Button>
        </form>
      )}
      {error && <Alert tone="danger">{error}</Alert>}
      {result && (
        <Panel title={<>Card No. {result.card_number} <StatusBadge status={result.status} /></>}>
          <div className="space-y-5">
            <Alert tone={explain[result.status]?.[1] ?? "info"}>{explain[result.status]?.[0] ?? result.status}</Alert>
            <div className="flex flex-col gap-5 sm:flex-row">
              {result.photo_url && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={result.photo_url} alt="Card holder" className="h-40 w-32 rounded-lg border object-cover" />
              )}
              <Dl
                items={[
                  ["Holder", result.holder],
                  ["Nationality", result.nationality],
                  ["Issued", nisDate(result.issued_on)],
                  ["Expires", nisDate(result.expires_on)],
                  ["Checked at", dateTime(result.checked_at)],
                ]}
              />
            </div>
          </div>
        </Panel>
      )}
    </div>
  );
}

export default function VerifyPage() {
  return (
    <Suspense>
      <Verify />
    </Suspense>
  );
}
