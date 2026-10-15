"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState, type ReactNode } from "react";
import { LanguageSwitcher, useI18n } from "@/components/I18nProvider";
import { NotificationBell } from "@/components/NotificationBell";
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
        <div className="hidden items-center gap-3.5 md:flex">
          {portal === "applicant" && <NotificationBell portal={portal} />}
          {languages && <LanguageSwitcher />}
          {user ? (
            <ProfileDropdown user={user} portal={portal ?? "staff"} />
          ) : (
            portal && <LogoutButton portal={portal} />
          )}
          {right}
        </div>

        {/* Mobile controls */}
        <div className="flex items-center gap-2 md:hidden">
          {portal === "applicant" && <NotificationBell portal={portal} />}
          {languages && <LanguageSwitcher />}
          {user && <ProfileDropdown user={user} portal={portal ?? "staff"} isMobile />}
          {(links || (!user && portal) || right) && (
            <button
              type="button"
              className="rounded border border-slate-300 p-2 text-slate-600"
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
        </div>
      </nav>

      {/* Mobile drawer */}
      {open && (
        <div id="mobile-portal-nav" className="border-t border-slate-200 bg-white px-[5%] py-4 shadow-lg md:hidden">
          {user && (
            <div className="mb-3 border-b border-slate-100 pb-3">
              <div className="text-xs font-semibold uppercase tracking-wider text-nis-primary">Signed in as</div>
              <div className="mt-0.5 text-sm font-bold text-slate-900">{user.name}</div>
              {(user.role || user.email) && (
                <div className="text-xs text-slate-500">
                  {user.role?.replace(/Super Administrator/g, "Super Admin") || user.email}
                </div>
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
              <LogoutButton portal={portal} fullWidth red={portal === "staff"} />
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

function ProfileDropdown({
  user,
  portal,
  isMobile = false,
}: {
  user: { name: string; role?: string; serviceNumber?: string; email?: string };
  portal: "staff" | "applicant";
  isMobile?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const { t } = useI18n();

  // Normalize role label: replace "Super Administrator" with "Super Admin"
  const rawRole = user.role?.replace(/Super Administrator/g, "Super Admin") || user.email;

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    if (open) {
      document.addEventListener("mousedown", handleClickOutside);
      document.addEventListener("keydown", handleKeyDown);
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [open]);

  return (
    <div className="relative inline-block text-left" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-expanded={open}
        aria-haspopup="true"
        aria-label={t("User profile menu")}
        className="flex items-center gap-2 rounded-xl px-2.5 py-1.5 text-left transition-colors hover:bg-slate-200/60 focus:outline-none focus:ring-2 focus:ring-nis-primary/30"
      >
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-nis-mint text-nis-primary font-bold text-sm border border-nis-primary/20 shadow-sm">
          {user.name.charAt(0).toUpperCase()}
        </div>
        {!isMobile && (
          <div className="text-right text-sm leading-tight max-w-[200px]">
            <div className="truncate font-semibold text-slate-800">{user.name}</div>
            {rawRole && <div className="truncate text-xs text-slate-500">{rawRole}</div>}
          </div>
        )}
        <svg
          className={`h-4 w-4 text-slate-500 transition-transform duration-150 ${open ? "rotate-180" : ""}`}
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fillRule="evenodd"
            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
            clipRule="evenodd"
          />
        </svg>
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-2xl border border-slate-200 bg-white py-2 shadow-xl ring-1 ring-black/5 animate-in fade-in zoom-in-95 duration-100">
          <div className="border-b border-slate-100 px-4 py-2.5">
            <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{t("Signed in as")}</p>
            <p className="mt-0.5 truncate text-sm font-bold text-slate-900">{user.name}</p>
            {rawRole && <p className="truncate text-xs text-slate-500 mt-0.5">{rawRole}</p>}
          </div>
          <div className="p-1.5">
            <LogoutButton portal={portal} fullWidth red onAfterClick={() => setOpen(false)} />
          </div>
        </div>
      )}
    </div>
  );
}

export function LogoutButton({
  portal,
  fullWidth = false,
  red = false,
  onAfterClick,
}: {
  portal: "staff" | "applicant";
  fullWidth?: boolean;
  red?: boolean;
  onAfterClick?: () => void;
}) {
  const { t } = useI18n();
  return (
    <form action={`/api/auth/logout/${portal}`} method="post" className={fullWidth ? "w-full" : undefined} onSubmit={() => onAfterClick?.()}>
      <button
        type="submit"
        className={
          red
            ? `flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors ${
                fullWidth ? "w-full text-left" : ""
              }`
            : `rounded-md bg-nis-primary px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-nis-primary-dark ${
                fullWidth ? "w-full text-center" : ""
              }`
        }
      >
        <svg
          className={`h-4 w-4 shrink-0 ${red ? "text-red-500" : ""}`}
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        <span>{t("Sign out")}</span>
      </button>
    </form>
  );
}
