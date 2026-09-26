import type { Metadata } from "next";
import { I18nProvider } from "@/components/I18nProvider";
import { getI18n } from "@/lib/i18n-server";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "NIS Residence Card Portal", template: "%s · NIS-RCIS" },
  description: "Nigeria Immigration Service — Residence Card Issuance System",
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const { lang, t } = await getI18n();

  return (
    // Browser extensions (Grammarly, screen recorders, …) add attributes to
    // <html> and <body> before React loads; don't report those as errors.
    <html lang={lang} className="h-full antialiased" suppressHydrationWarning>
      <body className="flex min-h-full flex-col" suppressHydrationWarning>
        <a href="#main" className="skip-link">{t("Skip to main content")}</a>
        <I18nProvider lang={lang}>{children}</I18nProvider>
      </body>
    </html>
  );
}
