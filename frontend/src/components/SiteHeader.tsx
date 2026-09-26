import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import { PublicFooter } from "./public/PublicFooter";

export function SiteHeader({ right }: { right?: ReactNode }) {
  return (
    <header className="no-print sticky top-0 z-40 border-b border-slate-200 bg-[#f6f6f6]">
      <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-[5%] text-sm xl:px-8">
        <Link href="/" className="flex items-center gap-3">
          <Image src="/images/nis-logo.png" alt="Nigeria Immigration Service" width={48} height={48} priority />
          <span className="leading-tight">
            <span className="block text-[13px] font-bold tracking-wide text-nis-primary-dark">NIGERIA IMMIGRATION SERVICE</span>
            <span className="block text-xs text-slate-500">Residence Card Portal</span>
          </span>
        </Link>
        {right}
      </div>
    </header>
  );
}

/** The landing page footer, used on every page of the application. */
export function SiteFooter() {
  return <PublicFooter />;
}

export function LogoutButton({ portal }: { portal: "staff" | "applicant" }) {
  return (
    <form action={`/api/auth/logout/${portal}`} method="post">
      <button className="rounded-md bg-nis-primary px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-nis-primary-dark">Sign out</button>
    </form>
  );
}
