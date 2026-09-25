import Link from "next/link";
import { ChatWidget } from "@/components/ChatWidget";
import { LogoutButton, SiteFooter, SiteHeader } from "@/components/SiteHeader";

export const metadata = { title: "Applicant portal" };

export default function PortalLayout({ children }: LayoutProps<"/portal">) {
  return (
    <>
      <SiteHeader
        right={
          <nav className="flex items-center gap-4 text-sm">
            <Link href="/portal" className="hover:underline">My applications</Link>
            <LogoutButton portal="applicant" />
          </nav>
        }
      />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">{children}</main>
      <SiteFooter />
      <ChatWidget scope="applicant" />
    </>
  );
}
