import Link from "next/link";

const actions = [
  { href: "/portal/apply", title: "Apply for a residence card", body: "Create an account, complete the online form, upload your documents, pay and book your biometrics appointment." },
  { href: "/portal/apply?type=renewal", title: "Renew your residence card", body: "Renew an expiring card with updated supporting documents." },
  { href: "/track", title: "Track an application", body: "Check the status of an application with your application number and passport number." },
  { href: "/verify", title: "Verify a residence card", body: "Confirm that a residence card is genuine and currently valid." },
];

export default function Home() {
  return (
    <div className="space-y-10">
      <section className="rounded-2xl bg-nis-green-dark px-6 py-10 text-white sm:px-10">
        <p className="text-sm font-semibold uppercase tracking-widest text-nis-gold">Directorate of Visa and Residency</p>
        <h1 className="mt-2 max-w-2xl text-3xl font-bold sm:text-4xl">Residence Card Portal</h1>
        <p className="mt-3 max-w-2xl text-white/85">
          Foreign nationals resident in Nigeria can apply for, track and renew their Residence Card online.
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link href="/portal/apply" className="rounded-lg bg-nis-gold px-5 py-2.5 font-semibold text-slate-900">Start an application</Link>
          <Link href="/register" className="rounded-lg border border-white/40 px-5 py-2.5 font-semibold">Create an account</Link>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        {actions.map((a) => (
          <Link key={a.href} href={a.href} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-nis-green">
            <h2 className="font-semibold text-nis-green-dark">{a.title}</h2>
            <p className="mt-1 text-sm text-slate-600">{a.body}</p>
          </Link>
        ))}
      </section>

      <p className="text-center text-sm text-slate-500">
        NIS officers: <Link href="/staff" className="font-medium text-nis-green underline">sign in to the staff console</Link>
      </p>
    </div>
  );
}
