"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useI18n } from "@/components/I18nProvider";

const COOKIE_CONSENT_KEY = "nis_cookie_consent";

export function CookieConsentNotice() {
  const [mounted, setMounted] = useState(false);
  const [visible, setVisible] = useState(false);
  const pathname = usePathname();
  const { t } = useI18n();

  useEffect(() => {
    setMounted(true);
    // Strictly landing page only
    const path = typeof window !== "undefined" ? window.location.pathname : "";
    if (path !== "/" && path !== "/home" && path !== "") {
      setVisible(false);
      return;
    }
    try {
      const consent = localStorage.getItem(COOKIE_CONSENT_KEY);
      if (!consent) {
        // Short delay so page loads smoothly before showing banner
        const timer = setTimeout(() => {
          const currentPath = typeof window !== "undefined" ? window.location.pathname : "";
          if (currentPath === "/" || currentPath === "/home" || currentPath === "") {
            setVisible(true);
          }
        }, 600);
        return () => clearTimeout(timer);
      }
    } catch {
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

  // Ensure notice ONLY renders on the public landing page, NEVER in dashboards or other pages
  if (!mounted || !visible) return null;
  if (pathname && pathname !== "/" && pathname !== "/home") return null;

  return (
    <aside
      role="region"
      aria-label={t("Cookie Consent Notice")}
      className="fixed bottom-4 right-4 z-50 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-slate-200/90 bg-white/95 p-4 shadow-xl backdrop-blur-sm transition-all duration-300 sm:bottom-6 sm:right-6 sm:p-5"
    >
      <div className="flex items-start gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-nis-mint text-nis-primary mt-0.5" aria-hidden>
          <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5" />
            <path d="M8.5 8.5v.01" />
            <path d="M7 15v.01" />
            <path d="M11 13v.01" />
            <path d="M15 16v.01" />
          </svg>
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <h3 className="text-xs sm:text-sm font-semibold text-slate-900">
              {t("Cookie & Privacy Consent")}
            </h3>
            <button
              type="button"
              onClick={() => handleConsent("essential")}
              className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
              aria-label={t("Close")}
            >
              <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 6L6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <p className="mt-1 text-xs leading-relaxed text-slate-600">
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

          <div className="mt-3 flex items-center gap-2 pt-0.5">
            <button
              type="button"
              onClick={() => handleConsent("all")}
              className="rounded-lg bg-nis-primary px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-nis-primary-dark"
            >
              {t("Accept all cookies")}
            </button>
            <button
              type="button"
              onClick={() => handleConsent("essential")}
              className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
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
