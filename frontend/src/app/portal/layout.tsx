import Link from "next/link";
import { ChatWidget } from "@/components/ChatWidget";
import { LogoutButton, SiteFooter, SiteHeader } from "@/components/SiteHeader";
import { getI18n } from "@/lib/i18n-server";

export const metadata = { title: "Applicant portal" };

export default async function PortalLayout({ children }: LayoutProps<"/portal">) {
  const { t } = await getI18n();
  return (
    <>
      <SiteHeader
        languages
        portal="applicant"
        links={[
          { href: "/portal", label: t("My applications") },
          { href: "/portal/cart", label: t("My Cart") },
          { href: "/portal/payments", label: t("Payment") },
          { href: "/portal/cards", label: t("My cards") },
        ]}
      />
      <main id="main" tabIndex={-1} className="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:py-8">{children}</main>
      <SiteFooter />
      <ChatWidget scope="applicant" />
    </>
  );
}
