import Link from "next/link";

export const metadata = { title: "Privacy notice" };

const sections: [string, React.ReactNode][] = [
  ["Who we are", "The Nigeria Immigration Service (NIS), Directorate of Visa and Residency, is the data controller for the Residence Card Issuance System. This notice explains how we use your personal data under the Nigeria Data Protection Act 2023 (NDPA)."],
  ["What we collect", "Your account details (name, e-mail, phone), your personal particulars (date and place of birth, nationality, sex, physical description, profession), passport and residence details, address in Nigeria, emergency contact, uploaded documents, photograph, signature and fingerprints captured at biometrics, payment references, and records of how your application was handled."],
  ["Why we use it (lawful basis)", "To process your residence card application, produce and verify your card, and carry out the Service's legal duties under the Immigration Act 2015 (performance of a task in the public interest and legal obligation). Your consent is recorded when you create an account."],
  ["Who can see it", "Only authorised NIS officers, according to their role, and partner government agencies that are allowed to verify cards. Payments are processed by Paystack. We never sell your data."],
  ["How long we keep it", "Unfinished applications are deleted after 90 days of inactivity and unverified accounts after 30 days. Documents of rejected applications are deleted after two years. Card records are kept while the card is valid and as required by law."],
  ["How we protect it", "Encrypted connections, two-factor sign-in for officers, private encrypted storage for documents, a full audit trail of officer actions, and regular security testing. Personal-data breaches are recorded and, where required, reported to the Nigeria Data Protection Commission within 72 hours."],
  ["Your rights", <>You can see and download the data we hold about you from the applicant portal (<strong>Download my data</strong>), ask us to correct it, object to or restrict its use where the law allows, and complain to the Nigeria Data Protection Commission (NDPC). To exercise your rights, <Link href="/contact" className="text-nis-primary underline">contact us</Link>.</>],
];

export default function PrivacyPage() {
  return (
    <div className="mx-auto max-w-3xl">
      <p className="text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">Nigeria Data Protection Act 2023</p>
      <h1 className="mt-1 text-2xl font-semibold text-slate-900">Privacy notice</h1>
      <p className="mt-2 text-sm text-slate-500">Version 2026-09</p>
      <div className="mt-8 space-y-6">
        {sections.map(([title, body]) => (
          <section key={title}>
            <h2 className="text-lg font-semibold text-nis-primary-dark">{title}</h2>
            <p className="mt-1 leading-relaxed text-slate-700">{body}</p>
          </section>
        ))}
      </div>
    </div>
  );
}
