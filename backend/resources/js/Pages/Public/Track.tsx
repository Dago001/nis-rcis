import React, { useState, useRef, type FormEvent } from "react";
import { ContentLayout } from "@/components/public/PublicLayout";
import { StatusBadge } from "@/components/StatusBadge";
import { Tracker } from "@/components/Tracker";
import { Alert, Button, Dl, Field, Input, Panel } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { nisDate, applicationType } from "@/lib/format";
import { Turnstile, type TurnstileRef } from "@/components/Turnstile";

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

export default function Track() {
  const [result, setResult] = useState<TrackResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState("");
  const turnstileRef = useRef<TurnstileRef>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>;
    if (turnstileToken) {
      formData["cf-turnstile-response"] = turnstileToken;
    }
    const query = new URLSearchParams(formData);

    setBusy(true);
    setError(null);
    setResult(null);

    try {
      setResult(await api<TrackResult>("public", `track?${query}`));
    } catch (e) {
      setError(e instanceof ApiError && e.status === 429 ? "Too many attempts. Please wait a minute." : (e as Error).message);
      turnstileRef.current?.reset();
      setTurnstileToken("");
    } finally {
      setBusy(false);
    }
  }

  return (
    <ContentLayout>
      <div className="mx-auto max-w-2xl space-y-6">
        <h1 className="text-xl font-semibold">Track your application</h1>
        <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6 space-y-4 shadow-sm">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Application or reference number">
              <Input name="application_number" required placeholder="RC-2026-100001" />
            </Field>
            <Field label="Passport number">
              <Input name="passport_number" required />
            </Field>
          </div>
          <Turnstile
            ref={turnstileRef}
            action="track"
            onVerify={setTurnstileToken}
            onExpire={() => setTurnstileToken("")}
          />
          <Button type="submit" disabled={busy} className="w-full sm:w-auto">
            {busy ? "Checking…" : "Track application"}
          </Button>
        </form>
        {error && <Alert tone="danger">{error}</Alert>}
        {result && (
          <Panel title={<>Application {result.application_number} <StatusBadge status={result.status} label={result.status_label} /></>}>
            <div className="space-y-6">
              <Tracker step={result.tracker_step} status={result.status} />
              <Dl
                items={[
                  ["Holder", result.holder],
                  ["Type", applicationType(result)],
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
    </ContentLayout>
  );
}
