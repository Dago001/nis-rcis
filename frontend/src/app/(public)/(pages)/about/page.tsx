import Image from "next/image";
import Link from "next/link";

export const metadata = { title: "About" };

export default function AboutPage() {
  return (
    <div className="space-y-12">
      <div className="grid items-center gap-10 md:grid-cols-2">
        <div>
          <p className="text-sm font-semibold uppercase tracking-widest text-nis-orange">About the portal</p>
          <h1 className="mt-2 text-2xl font-semibold text-slate-900">NIS Residence Card Issuance System</h1>
          <p className="mt-4 leading-relaxed text-slate-600">
            The Residence Card Portal is the Nigeria Immigration Service&apos;s online service for foreign nationals who live in Nigeria.
            It replaces paper forms with a single secure process: apply online, upload documents, pay, book a biometrics appointment,
            and follow your application until your card is ready for collection.
          </p>
          <p className="mt-4 leading-relaxed text-slate-600">
            The service is managed by the Directorate of Visa and Residency. Applications are reviewed by approving officers, and
            biometrics are captured at NIS enrollment centers across the country.
          </p>
        </div>
        <div className="relative aspect-[16/10] overflow-hidden rounded-2xl shadow-lg">
          <Image src="/images/hq-entrance.jpg" alt="NIS Headquarters, Abuja" fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover" />
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {[
          ["Secure", "Your data is protected with modern encryption, OAuth2 sign-in and a full audit trail of every action by officers."],
          ["Transparent", "See the status of your application at every step, and get an e-mail each time it changes."],
          ["Verifiable", "Every card carries a QR code that officers, employers and partner agencies can use to check it is genuine."],
        ].map(([title, body]) => (
          <div key={title} className="rounded-xl bg-nis-mint p-6">
            <h2 className="font-semibold text-nis-primary-dark">{title}</h2>
            <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
          </div>
        ))}
      </div>

      <p className="text-center">
        <Link prefetch={false} href="/portal/apply" className="rounded-md bg-nis-primary px-6 py-3 font-medium text-white hover:bg-nis-primary-dark">Start an application</Link>
      </p>
    </div>
  );
}
