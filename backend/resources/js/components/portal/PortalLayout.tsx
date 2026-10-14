import { ReactNode } from "react";
import { ChatWidget } from "@/components/ChatWidget";
import { CookieConsentNotice } from "@/components/CookieConsentNotice";
import { GlobalAnnouncements } from "@/components/GlobalAnnouncements";
import { SiteFooter, SiteHeader } from "@/components/SiteHeader";
import { useI18n } from "@/components/I18nProvider";

export function PortalLayout({ children }: { children: ReactNode }) {
  const { t } = useI18n();
  return (
    <div className="min-h-screen flex flex-col bg-[#f4f6f5]">
      <SiteHeader
        languages
        portal="applicant"
        links={[
          { href: "/portal", label: t("My applications") },
          { href: "/portal/cards", label: t("My cards") },
          { href: "/portal/payments", label: t("Payments") },
        ]}
      />
      <GlobalAnnouncements />
      <main id="main" tabIndex={-1} className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:py-8">{children}</main>
      <SiteFooter />
      <ChatWidget scope="applicant" />
      <CookieConsentNotice />
    </div>
  );
}

export default PortalLayout;
