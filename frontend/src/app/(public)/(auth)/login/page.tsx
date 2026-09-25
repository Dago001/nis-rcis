import Image from "next/image";
import Link from "next/link";
import { Alert } from "@/components/ui";

export const metadata = { title: "Login" };

/* Full-page navigations into the OAuth2 route handlers (not pages), so plain <a>. */
/* eslint-disable @next/next/no-html-link-for-pages */

export default async function LoginPage({ searchParams }: PageProps<"/login">) {
  const { verified } = await searchParams;

  return (
    <div className="mx-auto max-w-4xl">
      <div className="text-center">
        <h1 className="text-3xl font-bold text-white">Login</h1>
        <p className="mt-2 text-white/85">Choose how you want to sign in. You will be taken to the secure NIS sign-in page.</p>
      </div>

      <div className="mx-auto mt-6 max-w-xl space-y-3">
        {verified === "1" && <Alert tone="success">Your e-mail address has been verified. You can now sign in.</Alert>}
        {verified === "invalid" && <Alert tone="danger">That verification link is invalid or has expired. <Link href="/register?resend=1" className="underline">Request a new one</Link>.</Alert>}
      </div>

      <div className="mt-8 grid gap-6 md:grid-cols-2">
        <a href="/api/auth/login/applicant" className="group overflow-hidden rounded-2xl bg-white shadow-2xl transition hover:-translate-y-0.5 hover:shadow-black/40">
          <div className="relative h-40">
            <Image src="/images/hq-entrance.jpg" alt="" fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover transition duration-500 group-hover:scale-105" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
            <span className="absolute bottom-3 left-4 text-lg font-semibold text-white">Applicant</span>
          </div>
          <div className="p-5">
            <p className="text-sm text-slate-600">Apply for, renew or track your residence card, respond to queries and print your slips.</p>
            <span className="mt-4 inline-block rounded-md bg-nis-primary px-5 py-2 text-sm font-medium text-white group-hover:bg-nis-primary-dark">Applicant sign in</span>
          </div>
        </a>

        <a href="/api/auth/login/staff" className="group overflow-hidden rounded-2xl bg-white shadow-2xl transition hover:-translate-y-0.5 hover:shadow-black/40">
          <div className="relative h-40">
            <Image src="/images/hq-reception.jpg" alt="" fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover transition duration-500 group-hover:scale-105" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
            <span className="absolute bottom-3 left-4 text-lg font-semibold text-white">NIS Staff</span>
          </div>
          <div className="p-5">
            <p className="text-sm text-slate-600">Authorised officers: approval queue, biometrics desk, card register and reports. All access is logged.</p>
            <span className="mt-4 inline-block rounded-md bg-nis-primary-dark px-5 py-2 text-sm font-medium text-white">Staff sign in</span>
          </div>
        </a>
      </div>

      <p className="mt-8 text-center text-sm text-white/90">
        New applicant? <Link href="/register" className="font-medium text-white underline">Create an account</Link> ·{" "}
        <Link href="/forgot-password" className="font-medium text-white underline">Forgot password?</Link>
      </p>
    </div>
  );
}
