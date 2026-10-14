import { ReactNode } from "react";
import Image from "next/image";
import { PublicHeader } from "@/components/public/PublicHeader";
import { FooterForPath } from "@/components/public/FooterForPath";
import { ChatWidget } from "@/components/ChatWidget";
import { CookieConsentNotice } from "@/components/CookieConsentNotice";
import { GlobalAnnouncements } from "@/components/GlobalAnnouncements";

export function PublicLayout({ children, noMain = false }: { children: ReactNode; noMain?: boolean }) {
  return (
    <div className="min-h-screen flex flex-col bg-[#f4f6f5]">
      <a href="#main" className="skip-link">Skip to main content</a>
      <PublicHeader />
      <GlobalAnnouncements />
      {noMain ? children : (
        <main id="main" tabIndex={-1} className="flex-1">
          {children}
        </main>
      )}
      <FooterForPath />
      <ChatWidget scope="public" />
      <CookieConsentNotice />
    </div>
  );
}

export function ContentLayout({ children }: { children: ReactNode }) {
  return (
    <PublicLayout noMain>
      <main id="main" tabIndex={-1} className="mx-auto w-full max-w-6xl flex-1 px-4 py-12">
        {children}
      </main>
    </PublicLayout>
  );
}

export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <PublicLayout noMain>
      <main id="main" tabIndex={-1} className="relative flex flex-1 items-center overflow-hidden px-4 py-12 sm:py-16">
        <Image src="/images/hq-dusk.jpg" alt="" fill priority sizes="100vw" className="object-cover" />
        <div className="absolute inset-0 bg-gradient-to-br from-black/75 via-nis-primary-dark/60 to-black/60" aria-hidden />
        <div className="relative z-10 mx-auto w-full max-w-6xl">{children}</div>
      </main>
    </PublicLayout>
  );
}

export default PublicLayout;

