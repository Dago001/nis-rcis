import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";

// Official NIS social media accounts
const socials: { href: string; label: string; icon: ReactNode }[] = [
  { href: "https://x.com/nigimmigration", label: "X (Twitter)", icon: <path d="M18.9 2H22l-7.1 8.1L23.3 22h-6.6l-5.2-6.8L5.6 22H2.5l7.6-8.7L2 2h6.8l4.7 6.2L18.9 2zm-1.1 18h1.7L7.3 3.9H5.5L17.8 20z" /> },
  { href: "https://www.instagram.com/nigimmigration/", label: "Instagram", icon: <path d="M12 2.2c3.2 0 3.6 0 4.8.1 3.3.1 4.8 1.7 4.9 4.9.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 3.9 3.9 2.4 7.2 2.3 8.4 2.2 8.8 2.2 12 2.2zm0 4.7a5.1 5.1 0 1 0 0 10.2 5.1 5.1 0 0 0 0-10.2zm0 8.4a3.3 3.3 0 1 1 0-6.6 3.3 3.3 0 0 1 0 6.6zm5.3-9.8a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z" /> },
  { href: "https://facebook.com/nigerianimmigrationservice", label: "Facebook", icon: <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z" /> },
  { href: "https://www.youtube.com/@nigimmigration", label: "YouTube", icon: <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z" /> },
];

export function PublicFooter() {
  return (
    <footer className="no-print mt-auto bg-white">
      <div className="mx-auto flex max-w-7xl flex-wrap items-start justify-between gap-10 px-[5%] py-12 text-sm xl:px-8">
        <div className="max-w-sm space-y-4">
          <Link href="/" className="flex items-center gap-3">
            <Image src="/images/nis-logo.png" alt="" width={44} height={44} />
            <span className="text-[13px] font-bold leading-tight text-nis-primary-dark">NIGERIA<br />IMMIGRATION SERVICE</span>
          </Link>
          <div>
            <h2 className="font-semibold text-slate-900">NIS Headquarters</h2>
            <p className="mt-1 text-slate-600">Umar Musa Yar&apos;Adua Express Way, Airport Road, Sauka, Abuja, FCT, Nigeria.</p>
          </div>
          <p className="flex items-center gap-2 text-slate-600">
            <svg className="h-4 w-4 text-nis-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
            Monday – Friday, 08:00 – 16:00 (WAT)
          </p>
        </div>

        <div className="grid grid-cols-2 gap-10">
          <div>
            <h2 className="font-semibold text-slate-900">Services</h2>
            <ul className="mt-3 space-y-2 text-slate-600">
              <li><Link href="/portal/apply" className="hover:text-nis-primary">Apply for a residence card</Link></li>
              <li><Link href="/portal/apply?type=renewal" className="hover:text-nis-primary">Renew a residence card</Link></li>
              <li><Link href="/track" className="hover:text-nis-primary">Track an application</Link></li>
              <li><Link href="/verify" className="hover:text-nis-primary">Verify a residence card</Link></li>
            </ul>
          </div>
          <div>
            <h2 className="font-semibold text-slate-900">Complaints and Enquiries</h2>
            <ul className="mt-3 space-y-2 text-slate-600">
              <li><Link href="/about" className="hover:text-nis-primary">About Us</Link></li>
              <li><Link href="/contact" className="hover:text-nis-primary">Contact</Link></li>
              <li><Link href="/faq" className="hover:text-nis-primary">FAQs</Link></li>
              <li><Link href="/privacy" className="hover:text-nis-primary">Privacy notice</Link></li>
              <li><Link href="/staff" className="hover:text-nis-primary">NIS staff sign in</Link></li>
            </ul>
          </div>
        </div>
      </div>

      <div className="bg-nis-mint">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-[5%] py-4 text-xs text-slate-600 xl:px-8">
          <p>Nigeria Immigration Service · Directorate of Visa and Residency. All rights reserved © {new Date().getFullYear()}</p>
          <ul className="flex items-center gap-4">
            {socials.map((s) => (
              <li key={s.href}>
                <a href={s.href} target="_blank" rel="noopener noreferrer" aria-label={s.label} className="text-slate-800 hover:text-nis-primary">
                  <svg className="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden>{s.icon}</svg>
                </a>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </footer>
  );
}
