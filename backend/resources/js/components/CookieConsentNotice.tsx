"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useI18n } from "@/components/I18nProvider";

const COOKIE_CONSENT_KEY = "nis_cookie_consent";

export function CookieConsentNotice() {
  const [mounted, setMounted] = useState(false);
  const [visible, setVisible] = useState(false);
  const { t } = useI18n();

  useEffect(() => {
    setMounted(true);
    try {
      const consent = localStorage.getItem(COOKIE_CONSENT_KEY);
      if (!consent) {
        // Short delay so page loads smoothly before showing banner
        const timer = setTimeout(() => setVisible(true), 600);
        return () => clearTimeout(timer);
      }
    } catch {
      // In case localStorage is blocked in private browsing
      setVisible(true);
    }
  }, []);

  function handleConsent(choice: "all" | "essential") {
    try {
      localStorage.setItem(COOKIE_CONSENT_KEY, choice);
      document.cookie = `${COOKIE_CONSENT_KEY}=${choice}; path=/; max-age=31536000; SameSite=Lax`;
    } catch {}
    setVisible(false);
  }

  if (!mounted || !visible) return null;

  return (
    <aside
      role="region"
      aria-label={t("Cookie Consent Notice")}
      className="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-2xl backdrop-blur-md transition-all duration-300 sm:bottom-6 sm:left-6 sm:right-6 sm:p-6"
    >
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-5">
        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-nis-mint text-nis-primary" aria-hidden>
          <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5" />
            <path d="M8.5 8.5v.01" />
            <path d="M7 15v.01" />
            <path d="M11 13v.01" />
            <path d="M15 16v.01" />
          </svg>
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <h3 className="text-sm font-bold text-slate-900 sm:text-base">
              {t("Cookie Consent Notice")}
            </h3>
            <button
              type="button"
              onClick={() => handleConsent("essential")}
              className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              aria-label={t("Close")}
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 6L6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <p className="mt-1.5 text-xs leading-relaxed text-slate-600 sm:text-sm">
            {t(
              "We use essential cookies and local storage to keep your session secure, verify applicant identity, and deliver the ECOWAS Residence Card services."
            )}{" "}
            <Link
              href="/privacy"
              className="font-medium text-nis-primary underline hover:text-nis-primary-dark"
            >
              {t("Privacy notice")}
            </Link>
          </p>

          <div className="mt-4 flex flex-wrap items-center gap-2.5 pt-1">
            <button
              type="button"
              onClick={() => handleConsent("all")}
              className="rounded-lg bg-nis-primary px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-nis-primary-dark sm:text-sm"
            >
              {t("Accept all cookies")}
            </button>
            <button
              type="button"
              onClick={() => handleConsent("essential")}
              className="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:text-sm"
            >
              {t("Essential only")}
            </button>
          </div>
        </div>
      </div>
    </aside>
  );
}

export default CookieConsentNotice;
