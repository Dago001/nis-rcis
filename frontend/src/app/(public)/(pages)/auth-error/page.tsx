import Link from "next/link";
import { Alert } from "@/components/ui";

export default async function AuthErrorPage({ searchParams }: PageProps<"/auth-error">) {
  const { reason } = await searchParams;
  return (
    <div className="mx-auto max-w-md space-y-4">
      <h1 className="text-2xl font-bold">Sign-in problem</h1>
      <Alert tone="danger">{typeof reason === "string" ? reason : "Sign-in could not be completed."}</Alert>
      <Link href="/" className="text-nis-green underline">Return to the home page</Link>
    </div>
  );
}
