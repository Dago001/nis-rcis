"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState, type ReactNode } from "react";
import { LanguageSwitcher, useI18n } from "@/components/I18nProvider";
import { withBase } from "@/lib/base-path";

export type SiteHeaderLink = {
  href: string;
  label: string;
  icon?: ReactNode;
};

export function SiteHeader({
  links,
  user,
  portal,
  right,
  languages = false,
}: {
  links?: SiteHeaderLink[];
  user?: { name: string; role?: string; serviceNumber?: string; email?: string };
  portal?: "staff" | "applicant";
  right?: ReactNode;
  languages?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const { t } = useI18n();

  return (
    <header className="no-print sticky top-0 z-40 border-b border-slate-200 bg-[#f6f6f6]">
      <nav
        className={`mx-auto flex h-20 items-center justify-between gap-3 text-sm ${
          portal === "staff" ? "w-full px-4 sm:px-6 lg:px-6 xl:px-8" : "max-w-7xl px-[5%] xl:px-8"
        }`}
        aria-label="Portal Navigation"
      >
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
            <span className="block truncate text-xs font-bold tracking-wide text-nis-primary-dark sm:text-[13px]">
              {t("NIGERIA IMMIGRATION SERVICE")}
            </span>
            <span className="block truncate text-[11px] text-slate-500 sm:text-xs">
              {t("ECOWAS Residence Card Portal")}
            </span>
          </span>
        </Link>

        {/* Desktop navigation links (hidden for staff console since staff uses the sidebar) */}
        {portal !== "staff" && links && links.length > 0 && (
          <ul className="hidden items-center gap-5 whitespace-nowrap text-slate-700 md:flex lg:gap-6">
            {links.map((l) => (
              <li key={l.href}>
                <Link
                  href={l.href}
                  aria-current={pathname === l.href ? "page" : undefined}
                  className={`flex items-center gap-1.5 transition-colors hover:text-nis-primary ${
                    pathname === l.href ? "font-semibold text-nis-primary" : ""
                  }`}
                >
                  {l.icon}
                  <span>{t(l.label)}</span>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {/* Desktop right section */}
        <div className="hidden items-center gap-4 md:flex">
          {user && (
            <div className="text-right text-sm">
              <div className="font-medium text-slate-800">{user.name}</div>
              {(user.role || user.email) && (
                <div className="text-xs text-slate-500">{user.role || user.email}</div>
              )}
            </div>
          )}
          {languages && <LanguageSwitcher />}
          {portal && <LogoutButton portal={portal} />}
          {right}
        </div>

        {/* Mobile hamburger button */}
        {(links || user || portal || languages || right) && (
          <button
            type="button"
            className="rounded border border-slate-300 p-2 text-slate-600 md:hidden"
            aria-expanded={open}
            aria-controls="mobile-portal-nav"
            aria-label={open ? t("Close menu") : t("Open menu")}
            onClick={() => setOpen(!open)}
          >
            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
              {open ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
            </svg>
          </button>
        )}
      </nav>

      {/* Mobile drawer */}
      {open && (
        <div id="mobile-portal-nav" className="border-t border-slate-200 bg-white px-[5%] py-4 shadow-lg md:hidden">
          {user && (
            <div className="mb-3 border-b border-slate-100 pb-3">
              <div className="text-xs font-semibold uppercase tracking-wider text-nis-primary">Signed in as</div>
              <div className="mt-0.5 text-sm font-bold text-slate-900">{user.name}</div>
              {(user.role || user.email) && (
                <div className="text-xs text-slate-500">{user.role || user.email}</div>
              )}
            </div>
          )}

          {links && links.length > 0 && (
            <ul className="space-y-1">
              {links.map((l) => (
                <li key={l.href}>
                  <Link
                    href={l.href}
                    onClick={() => setOpen(false)}
                    className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-nis-mint hover:text-nis-primary ${
                      pathname === l.href ? "bg-nis-mint font-semibold text-nis-primary" : ""
                    }`}
                  >
                    {l.icon}
                    <span>{t(l.label)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          )}

          {right && <div className="mt-3 border-t border-slate-100 pt-3">{right}</div>}

          {languages && (
            <div className="mt-3 border-t border-slate-100 pt-3">
              <LanguageSwitcher />
            </div>
          )}

          {portal && (
            <div className="mt-4 border-t border-slate-100 pt-3">
              <LogoutButton portal={portal} fullWidth />
            </div>
          )}
        </div>
      )}
    </header>
  );
}

/** One-line footer used on every page except the landing page. */
export function SiteFooter() {
  const { t } = useI18n();
  return (
    <footer className="no-print mt-auto border-t border-slate-200 bg-white py-5 text-center text-sm text-slate-600">
      {t("Nigeria Immigration Service · All rights reserved © {year}", { year: new Date().getFullYear() })}
    </footer>
  );
}

export function LogoutButton({ portal, fullWidth = false }: { portal: "staff" | "applicant"; fullWidth?: boolean }) {
  const { t } = useI18n();
  return (
    <form action={`/api/auth/logout/${portal}`} method="post" className={fullWidth ? "w-full" : undefined}>
      <button className={`rounded-md bg-nis-primary px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-nis-primary-dark ${fullWidth ? "w-full text-center" : ""}`}>
        {t("Sign out")}
      </button>
    </form>
  );
}
