"use client";

import { useState, type FormEvent } from "react";
import { Alert, Button, Field, Input } from "@/components/ui";

export default function ForgotPasswordPage() {
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const response = await fetch("/api/public-account/password/forgot", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
    });
    const data = await response.json().catch(() => ({}));
    setMessage(data.message ?? "If the account exists, a reset link has been sent.");
    setBusy(false);
  }

  return (
    <div className="mx-auto max-w-md space-y-5">
      <h1 className="text-2xl font-bold">Reset your password</h1>
      {message ? (
        <Alert tone="success">{message}</Alert>
      ) : (
        <form onSubmit={submit} className="space-y-4">
          <Field label="E-mail address" required><Input name="email" type="email" required autoComplete="email" /></Field>
          <Button type="submit" disabled={busy} className="w-full">Send reset link</Button>
        </form>
      )}
    </div>
  );
}
