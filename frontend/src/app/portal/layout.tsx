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
        right={
          <nav aria-label="Applicant portal" className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <Link href="/portal" className="font-medium text-slate-700 transition-colors hover:text-nis-primary">{t("My applications")}</Link>
            <Link href="/portal/cards" className="font-medium text-slate-700 transition-colors hover:text-nis-primary">{t("My cards")}</Link>
            <Link href="/portal/payments" className="font-medium text-slate-700 transition-colors hover:text-nis-primary">{t("Payments")}</Link>
            <LogoutButton portal="applicant" />
          </nav>
        }
      />
      <main id="main" tabIndex={-1} className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">{children}</main>
      <SiteFooter />
      <ChatWidget scope="applicant" />
    </>
  );
}
