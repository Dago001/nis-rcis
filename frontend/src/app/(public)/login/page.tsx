import Link from "next/link";
import { Alert } from "@/components/ui";

export const metadata = { title: "Sign in" };

export default async function LoginPage({ searchParams }: PageProps<"/login">) {
  const { verified } = await searchParams;
  return (
    <div className="mx-auto max-w-md space-y-5">
      <h1 className="text-2xl font-bold">Applicant sign in</h1>
      {verified === "1" && <Alert tone="success">Your e-mail address has been verified. You can now sign in.</Alert>}
      {verified === "invalid" && <Alert tone="danger">That verification link is invalid or has expired. Request a new one below.</Alert>}
      <p className="text-sm text-slate-600">You will be taken to the secure NIS sign-in page.</p>
      {/* Full-page navigation into the OAuth2 route handler (not a page), so no <Link>. */}
      {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
      <a href="/api/auth/login/applicant" className="block rounded-lg bg-nis-green px-4 py-3 text-center font-semibold text-white">Continue to sign in</a>
      <p className="text-sm text-slate-600">
        No account yet? <Link href="/register" className="font-medium text-nis-green underline">Create one</Link>.{" "}
        Didn&apos;t get the verification e-mail? <Link href="/register?resend=1" className="font-medium text-nis-green underline">Resend it</Link>.
      </p>
    </div>
  );
}
