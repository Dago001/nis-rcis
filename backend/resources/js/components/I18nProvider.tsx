"use client";

import { createContext, useContext, useMemo, useState, useCallback, useEffect, type ReactNode } from "react";
import { LANG_COOKIE, LANGUAGES, parseLang, translator, type Lang, type Translate } from "@/lib/i18n";

interface I18nContextType {
  lang: Lang;
  setLang: (newLang: Lang) => void;
  t: Translate;
}

const I18nContext = createContext<I18nContextType>({
  lang: "en",
  setLang: () => {},
  t: translator("en"),
});

export function I18nProvider({ lang: initialLang, children }: { lang?: Lang; children: ReactNode }) {
  const [currentLang, setCurrentLang] = useState<Lang>(() => {
    if (initialLang) return parseLang(initialLang);
    if (typeof document !== "undefined") {
      const match = document.cookie.match(/(?:^|;\s*)nis_lang=([^;]+)/);
      if (match) return parseLang(match[1]);
      try {
        const stored = localStorage.getItem(LANG_COOKIE);
        if (stored) return parseLang(stored);
      } catch {}
    }
    return "en";
  });

  const changeLang = useCallback((newLang: Lang) => {
    const valid = parseLang(newLang);
    setCurrentLang(valid);
    if (typeof document !== "undefined") {
      document.cookie = `${LANG_COOKIE}=${valid}; path=/; max-age=31536000; samesite=lax`;
      try {
        localStorage.setItem(LANG_COOKIE, valid);
      } catch {}
      document.documentElement.lang = valid;
    }
  }, []);

  useEffect(() => {
    if (typeof document !== "undefined") {
      document.documentElement.lang = currentLang;
    }
  }, [currentLang]);

  const value = useMemo(
    () => ({
      lang: currentLang,
      setLang: changeLang,
      t: translator(currentLang),
    }),
    [currentLang, changeLang]
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  return useContext(I18nContext);
}

/** English / Français switch; the choice is remembered for a year across the entire system. */
export function LanguageSwitcher({ className = "inline-flex" }: { className?: string }) {
  const { lang, setLang, t } = useI18n();

  return (
    <label className={`items-center gap-1.5 text-sm text-slate-700 ${className.includes("flex") ? className : `inline-flex ${className}`}`}>
      <svg className="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
        <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
      </svg>
      <span className="sr-only">{t("Language")}</span>
      <select
        value={lang}
        onChange={(e) => {
          setLang(e.target.value as Lang);
        }}
        className="rounded border border-slate-300 bg-white px-2 py-1 text-sm font-medium text-slate-700 shadow-sm transition hover:border-nis-primary focus:border-nis-primary focus:outline-none focus:ring-1 focus:ring-nis-primary cursor-pointer"
      >
        {LANGUAGES.map((l) => (
          <option key={l.code} value={l.code} lang={l.code}>
            {l.code.toUpperCase()} · {l.label}
          </option>
        ))}
      </select>
    </label>
  );
}
