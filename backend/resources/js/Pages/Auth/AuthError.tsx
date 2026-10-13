import React from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui";
import { AuthLayout } from "@/components/public/PublicLayout";

export default function AuthError() {
  const searchParams = useSearchParams();
  const reason = searchParams.get("reason");

  return (
    <AuthLayout>
      <div className="mx-auto max-w-md space-y-4 rounded-2xl bg-white p-8 shadow-2xl">
        <h1 className="text-xl font-semibold">Sign-in problem</h1>
        <Alert tone="danger">{typeof reason === "string" ? reason : "Sign-in could not be completed."}</Alert>
        <Link href="/" className="text-nis-primary underline">Return to the home page</Link>
      </div>
    </AuthLayout>
  );
}
