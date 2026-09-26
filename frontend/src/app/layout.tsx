import type { Metadata, Viewport } from "next";
import { I18nProvider } from "@/components/I18nProvider";
import { ServiceWorker } from "@/components/ServiceWorker";
import { getI18n } from "@/lib/i18n-server";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "NIS Residence Card Portal", template: "%s · NIS-RCIS" },
  description: "Nigeria Immigration Service — Residence Card Issuance System",
  appleWebApp: { capable: true, title: "NIS Residence", statusBarStyle: "default" },
  icons: { apple: "/icons/apple-touch-icon.png" },
};

export const viewport: Viewport = { themeColor: "#1f6b1f" };

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const { lang, t } = await getI18n();
  // Non-live copies (staging, training) say so on every page.
  const environment = process.env.ENVIRONMENT_LABEL?.trim();

  return (
    // Browser extensions (Grammarly, screen recorders, …) add attributes to
    // <html> and <body> before React loads; don't report those as errors.
    <html lang={lang} className="h-full antialiased" suppressHydrationWarning>
      <body className="flex min-h-full flex-col" suppressHydrationWarning>
        <a href="#main" className="skip-link">{t("Skip to main content")}</a>
        {environment && (
          <div role="note" className="no-print bg-nis-orange px-4 py-1.5 text-center text-sm font-semibold tracking-wide text-white">
            {environment.toUpperCase()} — test system, not the live service. Do not enter real personal data.
          </div>
        )}
        <ServiceWorker />
        <I18nProvider lang={lang}>{children}</I18nProvider>
      </body>
    </html>
  );
}
