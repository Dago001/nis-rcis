"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";

const links = [
  { href: "/", label: "Home" },
  { href: "/about", label: "About" },
  { href: "/faq", label: "FAQ" },
  { href: "/track", label: "Track" },
  { href: "/verify", label: "Verify card" },
  { href: "/contact", label: "Contact" },
];

export function PublicHeader() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  return (
    <header className="no-print sticky top-0 z-40 border-b border-slate-200 bg-[#f6f6f6]">
      <nav className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-[5%] text-sm xl:px-8" aria-label="Main">
        <Link href="/" className="flex items-center gap-3" onClick={() => setOpen(false)}>
          <Image src="/images/nis-logo.png" alt="Nigeria Immigration Service" width={48} height={48} priority />
          <span className="leading-tight">
            <span className="block text-[13px] font-bold tracking-wide text-nis-primary-dark">NIGERIA IMMIGRATION SERVICE</span>
            <span className="block text-xs text-slate-500">Residence Card Portal</span>
          </span>
        </Link>

        <ul className="hidden items-center gap-7 text-slate-700 lg:flex">
          {links.map((l) => (
            <li key={l.href}>
              <Link href={l.href} className={`transition-colors hover:text-nis-primary ${pathname === l.href ? "font-semibold text-nis-primary" : ""}`}>
                {l.label}
              </Link>
            </li>
          ))}
        </ul>

        <div className="hidden items-center gap-3 lg:flex">
          <Link href="/contact" className="inline-flex items-center gap-2 rounded-md bg-nis-red px-4 py-2 font-medium text-white transition-colors hover:bg-red-700">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
            Contact Support
          </Link>
          <Link href="/login" className="rounded-md bg-nis-primary px-6 py-2 font-medium text-white transition-colors hover:bg-nis-primary-dark">
            Login
          </Link>
        </div>

        <button
          type="button"
          className="rounded border border-slate-300 p-2 text-slate-600 lg:hidden"
          aria-expanded={open}
          aria-controls="mobile-nav"
          aria-label={open ? "Close menu" : "Open menu"}
          onClick={() => setOpen(!open)}
        >
          <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
            {open ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
          </svg>
        </button>
      </nav>

      {open && (
        <div id="mobile-nav" className="border-t border-slate-200 bg-white px-[5%] py-4 lg:hidden">
          <ul className="space-y-1">
            {links.map((l) => (
              <li key={l.href}>
                <Link href={l.href} onClick={() => setOpen(false)} className="block rounded px-2 py-2 text-slate-700 hover:bg-nis-mint">{l.label}</Link>
              </li>
            ))}
          </ul>
          <div className="mt-3 flex gap-2">
            <Link href="/contact" onClick={() => setOpen(false)} className="flex-1 rounded-md bg-nis-red py-2 text-center font-medium text-white">Contact Support</Link>
            <Link href="/login" onClick={() => setOpen(false)} className="flex-1 rounded-md bg-nis-primary py-2 text-center font-medium text-white">Login</Link>
          </div>
        </div>
      )}
    </header>
  );
}
