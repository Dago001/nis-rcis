import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import { getI18n } from "@/lib/i18n-server";

const steps: { title: string; body: string; icon: ReactNode }[] = [
  {
    title: "Create an account.",
    body: "Register with your e-mail address and phone number, then sign in to the applicant portal.",
    icon: <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" />,
  },
  {
    title: "Complete the form.",
    body: "Enter your personal and passport details and upload your supporting documents. Save and continue later at any time.",
    icon: <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5" />,
  },
  {
    title: "Pay and book biometrics.",
    body: "Pay the residence card fee securely online and choose an enrollment center, date and time for your biometrics.",
    icon: <path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM2 10h20M6 15h4" />,
  },
  {
    title: "Capture and collect.",
    body: "Attend your appointment with your original passport. You are notified by e-mail when your card is ready to collect.",
    icon: <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" />,
  },
];

const requirements: { title: string; body: string; icon: ReactNode }[] = [
  {
    title: "Valid International Passport",
    body: "A clear copy of your passport data page. The passport must be valid for at least six more months.",
    icon: <path d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM8 17h8" />,
  },
  {
    title: "Residence Visa (STR)",
    body: "Your Subject-to-Regularization or residence visa, and your expatriate quota approval where it applies.",
    icon: <path d="M9 12l2 2 4-4M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z" />,
  },
  {
    title: "Passport Photograph",
    body: "A recent colour photograph on a white background. A live photo and signature are also taken at biometrics.",
    icon: <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" />,
  },
  {
    title: "Address & Contact Details",
    body: "Your residential address in Nigeria, an emergency contact, and a valid e-mail and phone number for status updates.",
    icon: <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />,
  },
];

const eligibility = [
  { image: "/images/hq-building.jpg", alt: "NIS Headquarters building", text: "Foreign nationals resident in Nigeria for employment, business or study." },
  { image: "/images/hq-reception.jpg", alt: "NIS Headquarters reception", text: "Expatriates with a valid residence visa (STR) and, for employees, an approved expatriate quota." },
  { image: "/images/card-template.jpg", alt: "Residence document", text: "Current residence card holders renewing a card that is expiring or has expired." },
];

function Icon({ children, className = "h-6 w-6" }: { children: ReactNode; className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      {children}
    </svg>
  );
}

export default async function Home() {
  const { t } = await getI18n();
  return (
    <main id="main" tabIndex={-1} className="flex-1">
      {/* Hero */}
      <section className="relative flex min-h-[calc(100vh-80px)] items-center justify-center overflow-hidden px-[5%] py-20 text-center">
        <Image src="/images/hq-dusk.jpg" alt="" fill priority sizes="100vw" className="object-cover" />
        <div className="absolute inset-0 bg-gradient-to-b from-black/65 via-black/55 to-black/75" aria-hidden />

        <div className="relative z-10 max-w-4xl text-white">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-white/80">{t("Directorate of Visa and Residency")}</p>
          <h1 className="mt-4 text-4xl font-semibold leading-tight sm:text-5xl md:text-6xl">
            {t("Obtain your Residence Card")}
            <span className="block text-nis-orange">{t("Apply Now!")}</span>
          </h1>
          <p className="mx-auto mt-5 max-w-xl text-base text-white/85 sm:text-lg">
            {t("Foreign nationals resident in Nigeria can apply for, renew and track their Residence Card online.")}
          </p>

          <div className="mt-10 flex flex-wrap items-end justify-center gap-6 sm:gap-8">
            <div className="flex flex-col items-center gap-2">
              <span className="text-sm text-white/90">{t("New to Nigeria?")}</span>
              <Link prefetch={false} href="/portal/apply" className="rounded-md bg-nis-primary px-6 py-3 font-medium text-white shadow-lg transition-colors hover:bg-nis-primary-dark">
                {t("Apply for Residence Card")}
              </Link>
            </div>
            <div className="flex flex-col items-center gap-2">
              <span className="text-sm text-white/90">{t("Card expiring?")}</span>
              <Link prefetch={false} href="/portal/apply?type=renewal" className="rounded-md bg-white px-6 py-3 font-medium text-nis-orange shadow-lg transition-colors hover:bg-orange-50">
                {t("Renew Residence Card")}
              </Link>
            </div>
          </div>

          <div className="mt-10 flex flex-col items-center gap-2">
            <span className="text-sm text-white/90">{t("Already applied?")}</span>
            <div className="flex flex-wrap justify-center gap-3">
              <Link href="/track" className="inline-flex items-center gap-2 rounded-md border-2 border-white/80 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-white/10">
                {t("Track Application")} <span aria-hidden>›</span>
              </Link>
              <Link href="/verify" className="inline-flex items-center gap-2 rounded-md border-2 border-white/80 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-white/10">
                {t("Verify a Card")} <span aria-hidden>›</span>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Steps to apply */}
      <section className="bg-white px-[5%] py-24" aria-labelledby="steps-heading">
        <div className="mx-auto max-w-7xl">
          <h2 id="steps-heading" className="text-center text-3xl font-bold text-slate-900">{t("Steps to Apply")}</h2>
          <ol className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {steps.map((s, i) => (
              <li key={s.title} className="rounded-xl border border-slate-200 bg-white p-6 transition hover:border-nis-primary/40 hover:shadow-md">
                <div className="flex items-center justify-between">
                  <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-nis-mint text-nis-primary"><Icon>{s.icon}</Icon></span>
                  <span className="text-3xl font-bold text-slate-100">{i + 1}</span>
                </div>
                <h3 className="mt-5 font-semibold text-nis-primary-dark">{t(s.title)}</h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{t(s.body)}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* Requirements */}
      <section className="bg-nis-mint px-[5%] py-24" aria-labelledby="req-heading">
        <div className="mx-auto max-w-7xl">
          <h2 id="req-heading" className="text-center text-3xl font-bold text-slate-900">{t("Requirements")}</h2>
          <p className="mx-auto mt-3 max-w-2xl text-center text-slate-600">{t("Have these ready before you start. Documents can be JPG, PNG or PDF, smaller than 2 MB each.")}</p>
          <ul className="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            {requirements.map((r) => (
              <li key={r.title} className="text-center">
                <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-nis-primary shadow-sm"><Icon className="h-8 w-8">{r.icon}</Icon></span>
                <h3 className="mt-5 font-semibold text-nis-primary-dark">{t(r.title)}</h3>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">{t(r.body)}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Eligibility */}
      <section className="bg-white px-[5%] py-24" aria-labelledby="elig-heading">
        <div className="mx-auto max-w-7xl">
          <h2 id="elig-heading" className="text-center text-3xl font-bold text-slate-900">{t("Eligibility Criteria")}</h2>
          <p className="mt-3 text-center text-slate-600">
            <strong className="text-slate-900">{t("Note:")}</strong> {t("ensure all information provided matches your travel and residence documents to avoid delays.")}
          </p>
          <ul className="mt-12 grid gap-6 md:grid-cols-3">
            {eligibility.map((e) => (
              <li key={e.text} className="overflow-hidden rounded-xl bg-nis-mint p-4">
                <div className="relative aspect-[16/10] overflow-hidden rounded-lg">
                  <Image src={e.image} alt={e.alt} fill sizes="(min-width: 768px) 33vw, 100vw" className="object-cover transition duration-500 hover:scale-105" />
                </div>
                <p className="mt-4 px-1 pb-2 font-semibold leading-snug text-nis-primary-dark">{t(e.text)}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Call to action */}
      <section className="bg-nis-primary-dark px-[5%] py-16 text-white">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-center md:flex-row md:text-left">
          <div>
            <h2 className="text-2xl font-bold">{t("Ready to apply?")}</h2>
            <p className="mt-1 text-white/80">{t("Create your account in minutes and track every step of your application online.")}</p>
          </div>
          <div className="flex flex-wrap justify-center gap-3">
            <Link href="/register" className="rounded-md bg-white px-6 py-3 font-medium text-nis-primary-dark hover:bg-nis-mint">{t("Create an account")}</Link>
            <Link href="/login" className="rounded-md border-2 border-white/80 px-6 py-3 font-medium hover:bg-white/10">{t("Login")}</Link>
          </div>
        </div>
      </section>
    </main>
  );
}
