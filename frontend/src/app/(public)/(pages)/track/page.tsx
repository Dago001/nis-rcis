"use client";

import { useState, type FormEvent } from "react";
import { StatusBadge } from "@/components/StatusBadge";
import { Tracker } from "@/components/Tracker";
import { Alert, Button, Dl, Field, Input, Panel } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { nisDate } from "@/lib/format";

type TrackResult = {
  application_number: string;
  holder: string;
  type: string;
  status: string;
  status_label: string;
  tracker_step: number;
  submitted_at: string | null;
  appointment_date: string | null;
  appointment_time: string | null;
  enrollment_center: string | null;
};

export default function TrackPage() {
  const [result, setResult] = useState<TrackResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const query = new URLSearchParams(Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>);
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      setResult(await api<TrackResult>("public", `track?${query}`));
    } catch (e) {
      setError(e instanceof ApiError && e.status === 429 ? "Too many attempts. Please wait a minute." : (e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="text-xl font-semibold">Track your application</h1>
      <form onSubmit={submit} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <Field label="Application or reference number"><Input name="application_number" required placeholder="RC-2026-100001" /></Field>
        <Field label="Passport number"><Input name="passport_number" required /></Field>
        <Button type="submit" disabled={busy}>Track</Button>
      </form>
      {error && <Alert tone="danger">{error}</Alert>}
      {result && (
        <Panel title={<>Application {result.application_number} <StatusBadge status={result.status} label={result.status_label} /></>}>
          <div className="space-y-6">
            <Tracker step={result.tracker_step} status={result.status} />
            <Dl
              items={[
                ["Holder", result.holder],
                ["Type", result.type === "RENEWAL" ? "Renewal" : "New application"],
                ["Submitted", nisDate(result.submitted_at)],
                ["Biometrics appointment", result.appointment_date ? `${nisDate(result.appointment_date)} at ${result.appointment_time}` : "—"],
                ["Enrollment center", result.enrollment_center],
              ]}
            />
            {result.status === "QUERIED" && <Alert tone="warning">Action required: sign in to the applicant portal to respond to the query.</Alert>}
          </div>
        </Panel>
      )}
    </div>
  );
}
