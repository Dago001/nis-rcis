"use client";

import { useState, useRef, type FormEvent } from "react";
import { Alert, Button, Field, Input } from "@/components/ui";
import { withBase } from "@/lib/base-path";
import { Turnstile, type TurnstileRef } from "@/components/Turnstile";

export default function ForgotPasswordPage() {
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState("");
  const turnstileRef = useRef<TurnstileRef>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!turnstileToken) {
      setError("Please complete the security challenge below.");
      return;
    }
    setBusy(true);
    setError(null);

    const formData = Object.fromEntries(new FormData(event.currentTarget));
    if (turnstileToken) {
      formData["cf-turnstile-response"] = turnstileToken;
    }

    try {
      const response = await fetch(withBase("/api/public-account/password/forgot"), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(formData),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        setError(data.errors?.["cf-turnstile-response"]?.[0] || data.message || "Failed to send reset link.");
        turnstileRef.current?.reset();
        setTurnstileToken("");
      } else {
        setMessage(data.message ?? "If the account exists, a reset link has been sent.");
      }
    } catch {
      setError("An unexpected error occurred. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-md space-y-5 rounded-2xl bg-white p-8 shadow-2xl">
      <h1 className="text-xl font-semibold">Reset your password</h1>
      <p className="text-sm text-slate-600">Enter the e-mail address of your applicant account. We will send you a link to choose a new password.</p>
      {error && <Alert tone="danger">{error}</Alert>}
      {message ? (
        <Alert tone="success">{message}</Alert>
      ) : (
        <form onSubmit={submit} className="space-y-4">
          <Field label="E-mail address" required><Input name="email" type="email" required autoComplete="email" /></Field>
          <Turnstile
            ref={turnstileRef}
            action="forgot-password"
            onVerify={setTurnstileToken}
            onExpire={() => setTurnstileToken("")}
          />
          <Button type="submit" disabled={busy} className="w-full">Send reset link</Button>
        </form>
      )}
    </div>
  );
}
