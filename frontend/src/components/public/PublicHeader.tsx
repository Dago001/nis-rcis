"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { LanguageSwitcher, useI18n } from "@/components/I18nProvider";
import { withBase } from "@/lib/base-path";

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
  const { t } = useI18n();

  useEffect(() => {
    setOpen(false);
  }, [pathname]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };
    if (open) {
      document.addEventListener("keydown", onKey);
    }
    return () => {
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <header className="no-print sticky top-0 z-40 border-b border-slate-200 bg-[#f6f6f6]">
      <nav className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-3 px-4 sm:px-[5%] text-sm xl:px-8" aria-label="Main">
        <Link href="/" className="flex min-w-0 items-center gap-2.5 sm:gap-3" onClick={() => setOpen(false)}>
          <Image
            src={withBase("/images/nis-logo.png")}
            alt="Nigeria Immigration Service"
            width={44}
            height={44}
            priority
            className="h-9 w-9 shrink-0 sm:h-11 sm:w-11"
          />
          <span className="min-w-0 leading-tight">
            <span className="block truncate text-xs font-bold tracking-wide text-nis-primary-dark sm:text-[13px]">{t("NIGERIA IMMIGRATION SERVICE")}</span>
            <span className="block truncate text-[11px] text-slate-500 sm:text-xs">{t("ECOWAS Residence Card Portal")}</span>
          </span>
        </Link>

        <ul className="hidden items-center gap-5 whitespace-nowrap text-slate-700 lg:flex xl:gap-6">
          {links.map((l) => (
            <li key={l.href}>
              <Link href={l.href} aria-current={pathname === l.href ? "page" : undefined} className={`transition-colors hover:text-nis-primary ${pathname === l.href ? "font-semibold text-nis-primary" : ""}`}>
                {t(l.label)}
              </Link>
            </li>
          ))}
        </ul>

        <div className="hidden items-center gap-3 lg:flex">
          <LanguageSwitcher />
          <Link href="/contact" className="inline-flex min-h-[40px] items-center gap-2 rounded-md bg-nis-red px-4 py-2 font-medium text-white transition-colors hover:bg-red-700">
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
            <span className="hidden xl:inline">{t("Contact Support")}</span>
            <span className="xl:hidden">{t("Contact")}</span>
          </Link>
          <Link href="/login" className="inline-flex min-h-[40px] items-center justify-center rounded-md bg-nis-primary px-6 py-2 font-medium text-white transition-colors hover:bg-nis-primary-dark">
            {t("Login")}
          </Link>
        </div>

        <button
          type="button"
          className="flex min-h-[40px] min-w-[40px] items-center justify-center rounded-lg border border-slate-300 p-2 text-slate-600 transition hover:bg-slate-100 lg:hidden"
          aria-expanded={open}
          aria-controls="mobile-nav"
          aria-label={open ? t("Close menu") : t("Open menu")}
          onClick={() => setOpen(!open)}
        >
          <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
            {open ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
          </svg>
        </button>
      </nav>

      {open && (
        <div id="mobile-nav" className="border-t border-slate-200 bg-white px-4 py-4 sm:px-[5%] shadow-lg lg:hidden">
          <ul className="space-y-1">
            {links.map((l) => (
              <li key={l.href}>
                <Link
                  href={l.href}
                  onClick={() => setOpen(false)}
                  className={`flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-nis-mint hover:text-nis-primary ${pathname === l.href ? "bg-nis-mint font-semibold text-nis-primary" : ""}`}
                >
                  {t(l.label)}
                </Link>
              </li>
            ))}
          </ul>
          <div className="mt-3 border-t border-slate-100 pt-3">
            <LanguageSwitcher />
          </div>
          <div className="mt-3 flex flex-col gap-2 pt-1 sm:flex-row">
            <Link href="/contact" onClick={() => setOpen(false)} className="flex min-h-[40px] items-center justify-center rounded-lg bg-nis-red px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-red-700">
              {t("Contact Support")}
            </Link>
            <Link href="/login" onClick={() => setOpen(false)} className="flex min-h-[40px] items-center justify-center rounded-lg bg-nis-primary px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-nis-primary-dark">
              {t("Login")}
            </Link>
          </div>
        </div>
      )}
    </header>
  );
}
