"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { Alert, Button, Field, Input } from "@/components/ui";
import { ApiError } from "@/lib/api-client";

async function post(path: string, body: unknown) {
  const response = await fetch(`/api/public-account/${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(response.status, data.message ?? "Request failed", data.errors ?? {});
  return data as { message: string };
}

function RegisterForm() {
  const resend = useSearchParams().get("resend") === "1";
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = Object.fromEntries(new FormData(event.currentTarget));
    setBusy(true);
    setErrors({});
    try {
      const result = await post(resend ? "email/resend" : "register", form);
      setMessage(result.message);
    } catch (e) {
      if (e instanceof ApiError) setErrors({ ...e.fieldErrors(), _: e.message });
    } finally {
      setBusy(false);
    }
  }

  if (message) {
    return (
      <div className="space-y-4">
        <Alert tone="success">{message}</Alert>
        <p className="text-sm text-slate-600">Open the link in the e-mail within 24 hours, then <Link href="/login" className="text-nis-green underline">sign in</Link>.</p>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {errors._ && !Object.keys(errors).some((k) => k !== "_") && <Alert tone="danger">{errors._}</Alert>}
      {!resend && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Surname" required error={errors.surname}><Input name="surname" required autoComplete="family-name" /></Field>
          <Field label="Other names" required error={errors.forenames}><Input name="forenames" required autoComplete="given-name" /></Field>
        </div>
      )}
      <Field label="E-mail address" required error={errors.email}><Input name="email" type="email" required autoComplete="email" /></Field>
      {!resend && (
        <>
          <Field label="Phone number" required error={errors.phone} hint="Include the country code, e.g. +234…"><Input name="phone" type="tel" required autoComplete="tel" /></Field>
          <Field label="Password" required error={errors.password} hint="At least 10 characters with letters and numbers."><Input name="password" type="password" required autoComplete="new-password" /></Field>
          <Field label="Confirm password" required><Input name="password_confirmation" type="password" required autoComplete="new-password" /></Field>
        </>
      )}
      <Button type="submit" disabled={busy} className="w-full">{busy ? "Please wait…" : resend ? "Resend verification e-mail" : "Create account"}</Button>
      <p className="text-center text-sm text-slate-600">Already registered? <Link href="/login" className="text-nis-green underline">Sign in</Link></p>
    </form>
  );
}

export default function RegisterPage() {
  return (
    <div className="mx-auto max-w-lg">
      <h1 className="mb-1 text-2xl font-bold">Create your applicant account</h1>
      <p className="mb-6 text-sm text-slate-600">We will e-mail you a link to verify your address before you can sign in.</p>
      <Suspense><RegisterForm /></Suspense>
    </div>
  );
}
