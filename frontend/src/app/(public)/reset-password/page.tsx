"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { Alert, Button, Field, Input } from "@/components/ui";

function ResetForm() {
  const params = useSearchParams();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [done, setDone] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const body = { ...Object.fromEntries(new FormData(event.currentTarget)), token: params.get("token"), email: params.get("email") };
    const response = await fetch("/api/public-account/password/reset", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (response.ok) setDone(data.message);
    else setErrors(data.errors ?? { email: [data.message ?? "Reset failed"] });
  }

  if (done) {
    return <Alert tone="success">{done} <Link href="/login" className="underline">Sign in</Link></Alert>;
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      {errors.email && <Alert tone="danger">{errors.email[0]}</Alert>}
      <Field label="New password" required error={errors.password?.[0]} hint="At least 10 characters with letters and numbers.">
        <Input name="password" type="password" required autoComplete="new-password" />
      </Field>
      <Field label="Confirm new password" required><Input name="password_confirmation" type="password" required autoComplete="new-password" /></Field>
      <Button type="submit" className="w-full">Set new password</Button>
    </form>
  );
}

export default function ResetPasswordPage() {
  return (
    <div className="mx-auto max-w-md space-y-5">
      <h1 className="text-2xl font-bold">Choose a new password</h1>
      <Suspense><ResetForm /></Suspense>
    </div>
  );
}
