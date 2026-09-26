"use client";

import { useRouter } from "next/navigation";
import { createContext, useContext, useMemo, type ReactNode } from "react";
import { LANG_COOKIE, LANGUAGES, translator, type Lang, type Translate } from "@/lib/i18n";

const I18nContext = createContext<{ lang: Lang; t: Translate }>({ lang: "en", t: translator("en") });

export function I18nProvider({ lang, children }: { lang: Lang; children: ReactNode }) {
  const value = useMemo(() => ({ lang, t: translator(lang) }), [lang]);
  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  return useContext(I18nContext);
}

/** English / Français switch; the choice is remembered for a year. */
export function LanguageSwitcher({ className = "" }: { className?: string }) {
  const { lang, t } = useI18n();
  const router = useRouter();

  return (
    <label className={`inline-flex items-center gap-1.5 text-sm text-slate-700 ${className}`}>
      <svg className="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
        <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
      </svg>
      <span className="sr-only">{t("Language")}</span>
      <select
        value={lang}
        onChange={(e) => {
          document.cookie = `${LANG_COOKIE}=${e.target.value}; path=/; max-age=31536000; samesite=lax`;
          router.refresh();
        }}
        className="rounded border border-slate-300 bg-white px-1.5 py-1 text-sm"
      >
        {LANGUAGES.map((l) => <option key={l.code} value={l.code} lang={l.code}>{l.code.toUpperCase()} · {l.label}</option>)}
      </select>
    </label>
  );
}
