"use client";

import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import { LanguageSwitcher, useI18n } from "@/components/I18nProvider";

export function SiteHeader({ right, languages = false }: { right?: ReactNode; languages?: boolean }) {
  const { t } = useI18n();
  return (
    <header className="no-print sticky top-0 z-40 border-b border-slate-200 bg-[#f6f6f6]">
      <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-[5%] text-sm xl:px-8">
        <Link href="/" className="flex items-center gap-3">
          <Image src="/images/nis-logo.png" alt="Nigeria Immigration Service" width={48} height={48} priority />
          <span className="leading-tight">
            <span className="block text-[13px] font-bold tracking-wide text-nis-primary-dark">{t("NIGERIA IMMIGRATION SERVICE")}</span>
            <span className="block text-xs text-slate-500">{t("Residence Card Portal")}</span>
          </span>
        </Link>
        <div className="flex items-center gap-4">
          {languages && <LanguageSwitcher className="hidden sm:inline-flex" />}
          {right}
        </div>
      </div>
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

export function LogoutButton({ portal }: { portal: "staff" | "applicant" }) {
  const { t } = useI18n();
  return (
    <form action={`/api/auth/logout/${portal}`} method="post">
      <button className="rounded-md bg-nis-primary px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-nis-primary-dark">{t("Sign out")}</button>
    </form>
  );
}
