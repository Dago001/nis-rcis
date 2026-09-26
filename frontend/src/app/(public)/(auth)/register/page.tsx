"use client";

import Image from "next/image";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { lettersOnly, NAME_PATTERN, PasswordInput, PHONE_PATTERN, PhoneInput } from "@/components/inputs";
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

type Values = { surname: string; forenames: string; email: string; phone: string; password: string; password_confirmation: string };

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/** Client-side checks, shown as soon as a field is left (the API re-checks everything). */
function check(values: Values, resend: boolean): Record<string, string> {
  const e: Record<string, string> = {};
  if (!resend) {
    if (!values.surname.trim()) e.surname = "Enter your surname.";
    else if (!NAME_PATTERN.test(values.surname.trim())) e.surname = "Use letters only.";
    if (!values.forenames.trim()) e.forenames = "Enter your other names.";
    else if (!NAME_PATTERN.test(values.forenames.trim())) e.forenames = "Use letters only.";
    if (!values.phone) e.phone = "Enter your phone number.";
    else if (!PHONE_PATTERN.test(values.phone)) e.phone = "Enter a valid phone number (digits only).";
    if (values.password.length < 6) e.password = "Use at least 6 characters.";
    else if (!/[A-Za-z]/.test(values.password) || !/[0-9]/.test(values.password)) e.password = "Use both letters and numbers.";
    if (values.password_confirmation !== values.password) e.password_confirmation = "The passwords do not match.";
  }
  if (!EMAIL_PATTERN.test(values.email.trim())) e.email = "Enter a valid e-mail address.";
  return e;
}

function RegisterForm() {
  const resend = useSearchParams().get("resend") === "1";
  const [values, setValues] = useState<Values>({ surname: "", forenames: "", email: "", phone: "", password: "", password_confirmation: "" });
  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const clientErrors = check(values, resend);
  const errorFor = (field: keyof Values) => (touched[field] ? clientErrors[field] : undefined) ?? serverErrors[field];
  const set = (field: keyof Values, value: string) => {
    setValues((v) => ({ ...v, [field]: value }));
    setServerErrors((e) => ({ ...e, [field]: "" }));
  };
  const touch = (field: keyof Values) => () => setTouched((t) => ({ ...t, [field]: true }));

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setTouched({ surname: true, forenames: true, email: true, phone: true, password: true, password_confirmation: true });
    if (Object.keys(clientErrors).length) return;
    setBusy(true);
    setServerErrors({});
    try {
      const body = resend ? { email: values.email.trim() } : { ...values, surname: values.surname.trim(), forenames: values.forenames.trim(), email: values.email.trim() };
      const result = await post(resend ? "email/resend" : "register", body);
      setMessage(result.message);
    } catch (e) {
      if (e instanceof ApiError) setServerErrors({ ...e.fieldErrors(), _: e.message });
    } finally {
      setBusy(false);
    }
  }

  if (message) {
    return (
      <div className="space-y-4">
        <Alert tone="success">{message}</Alert>
        <p className="text-sm text-slate-600">Open the link in the e-mail within 24 hours, then <Link href="/login" className="text-nis-primary underline">sign in</Link>.</p>
      </div>
    );
  }

  const hasVisibleErrors = Object.keys(values).some((k) => errorFor(k as keyof Values));

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {serverErrors._ && !Object.keys(serverErrors).some((k) => k !== "_" && serverErrors[k]) && <Alert tone="danger">{serverErrors._}</Alert>}
      {hasVisibleErrors && <Alert tone="danger">Please correct the highlighted details.</Alert>}
      {!resend && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Surname" required error={errorFor("surname")}>
            <Input value={values.surname} onChange={(e) => set("surname", lettersOnly(e.target.value))} onBlur={touch("surname")} autoComplete="family-name" maxLength={100} />
          </Field>
          <Field label="Other names" required error={errorFor("forenames")}>
            <Input value={values.forenames} onChange={(e) => set("forenames", lettersOnly(e.target.value))} onBlur={touch("forenames")} autoComplete="given-name" maxLength={150} />
          </Field>
        </div>
      )}
      <Field label="E-mail address" required error={errorFor("email")}>
        <Input type="email" value={values.email} onChange={(e) => set("email", e.target.value)} onBlur={touch("email")} autoComplete="email" maxLength={255} />
      </Field>
      {!resend && (
        <>
          <div onBlur={touch("phone")}>
            <Field label="Phone number" required error={errorFor("phone")} hint="Choose your country code, then type the rest of the number (digits only).">
              <PhoneInput value={values.phone} onChange={(v) => set("phone", v)} />
            </Field>
          </div>
          <Field label="Password" required error={errorFor("password")} hint="At least 6 characters, with letters and numbers. Common passwords that appear in data leaks are refused.">
            <PasswordInput value={values.password} onChange={(e) => set("password", e.target.value)} onBlur={touch("password")} autoComplete="new-password" />
          </Field>
          <Field label="Confirm password" required error={errorFor("password_confirmation")}>
            <PasswordInput value={values.password_confirmation} onChange={(e) => set("password_confirmation", e.target.value)} onBlur={touch("password_confirmation")} autoComplete="new-password" />
          </Field>
        </>
      )}
      <Button type="submit" disabled={busy} className="w-full">{busy ? "Please wait…" : resend ? "Resend verification e-mail" : "Create account"}</Button>
      <p className="text-center text-sm text-slate-600">Already registered? <Link href="/login" className="text-nis-primary underline">Sign in</Link></p>
    </form>
  );
}

export default function RegisterPage() {
  return (
    <div className="mx-auto grid max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl md:grid-cols-2">
      <div className="relative hidden min-h-[560px] md:block">
        <Image src="/images/hq-entrance.jpg" alt="" fill priority sizes="50vw" className="object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-nis-primary-dark/90 via-nis-primary-dark/40 to-transparent" />
        <div className="absolute bottom-0 p-8 text-white">
          <p className="text-sm font-semibold uppercase tracking-widest text-white/80">Residence Card Portal</p>
          <p className="mt-2 text-2xl font-semibold leading-snug">One account to apply, pay, book biometrics and track your residence card.</p>
        </div>
      </div>
      <div className="p-8 sm:p-10">
        <h1 className="mb-1 text-xl font-semibold">Create your applicant account</h1>
        <p className="mb-6 text-sm text-slate-600">Use an e-mail address you can access: status updates are sent there.</p>
        <Suspense><RegisterForm /></Suspense>
      </div>
    </div>
  );
}
