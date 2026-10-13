import { LANG_COOKIE, parseLang, translator, type Lang, type Translate } from "./i18n";

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? match[2] : null;
}

/** Language and translator for components (from the `nis_lang` cookie). */
export async function getI18n(): Promise<{ lang: Lang; t: Translate }> {
  const lang = parseLang(getCookie(LANG_COOKIE));
  return { lang, t: translator(lang) };
}
