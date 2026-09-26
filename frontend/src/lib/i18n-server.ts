import { cookies } from "next/headers";
import { LANG_COOKIE, parseLang, translator, type Lang, type Translate } from "./i18n";

/** Language and translator for server components (from the `nis_lang` cookie). */
export async function getI18n(): Promise<{ lang: Lang; t: Translate }> {
  const lang = parseLang((await cookies()).get(LANG_COOKIE)?.value);
  return { lang, t: translator(lang) };
}
