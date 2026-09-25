import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";

export function SiteHeader({ right }: { right?: ReactNode }) {
  return (
    <header className="no-print border-b-4 border-nis-gold bg-nis-green-dark text-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Link href="/" className="flex items-center gap-3">
          <Image src="/nis-crest.png" alt="" width={44} height={44} priority />
          <span>
            <span className="block text-sm font-bold tracking-wide sm:text-base">NIGERIA IMMIGRATION SERVICE</span>
            <span className="block text-xs text-white/80">Residence Card Issuance System</span>
          </span>
        </Link>
        {right}
      </div>
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="no-print mt-auto border-t border-slate-200 bg-white py-5 text-center text-xs text-slate-500">
      © {new Date().getFullYear()} Nigeria Immigration Service · Directorate of Visa and Residency
    </footer>
  );
}

export function LogoutButton({ portal }: { portal: "staff" | "applicant" }) {
  return (
    <form action={`/api/auth/logout/${portal}`} method="post">
      <button className="rounded-lg border border-white/30 px-3 py-1.5 text-sm hover:bg-white/10">Sign out</button>
    </form>
  );
}
