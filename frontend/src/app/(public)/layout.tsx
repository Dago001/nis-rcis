import Link from "next/link";
import { SiteFooter, SiteHeader } from "@/components/SiteHeader";

export default function PublicLayout({ children }: LayoutProps<"/">) {
  return (
    <>
      <SiteHeader
        right={
          <nav className="flex items-center gap-4 text-sm">
            <Link href="/track" className="hidden hover:underline sm:inline">Track</Link>
            <Link href="/verify" className="hidden hover:underline sm:inline">Verify card</Link>
            <Link href="/portal" className="rounded-lg bg-nis-gold px-3 py-1.5 font-semibold text-slate-900">Applicant sign in</Link>
          </nav>
        }
      />
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10">{children}</main>
      <SiteFooter />
    </>
  );
}
