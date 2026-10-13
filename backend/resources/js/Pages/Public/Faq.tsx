import React from "react";
import Link from "next/link";
import { ContentLayout } from "@/components/public/PublicLayout";

const faqs: [string, React.ReactNode][] = [
  ["Who needs a residence card?", "Citizens of ECOWAS member states (Benin, Cabo Verde, Côte d'Ivoire, The Gambia, Ghana, Guinea, Guinea-Bissau, Liberia, Senegal, Sierra Leone and Togo) who reside in Nigeria for employment, business or academic study must obtain an ECOWAS Residence Card. Cards are valid for exactly 2 years from the date of production."],
  ["What is the application process?", "The process consists of 8 steps: 1. General Information (Nationality, Category: Student/Work/Business, Document type and Fee view); 2. Personal details & photograph; 3. Travel/Identity document; 4. Residence & contacts; 5. Category-specific documents upload; 6. Review & declaration; 7. Fee payment (Paystack); 8. Biometrics appointment scheduling. An immigration Vetting Officer then cross-checks your application before clearing you for biometrics."],
  ["What documents do I need?", "You need: (1) International passport data page OR ECOWAS Travel Certificate, (2) Proof of domicile in Nigeria (utility bill), (3) Passport photograph on white background, and (4) Category-specific document: Admission Letter for Students, Employment Appointment Letter for Workers, or CAC Registration Certificate for Business owners. Expatriate quota is not required."],
  ["How much does it cost, and how do I pay?", "The prescribed fee is ₦35,000. You pay online securely by card, bank transfer or USSD via Paystack in Step 7 before scheduling your biometrics."],
  ["What is the Temporary Residence Slip?", "After your 10-finger biometrics capture at the enrollment center, a Temporary Residence Slip with your live photograph, captured signature, and verification QR code becomes available on your console and the staff console. It serves as temporary legal proof of residency while waiting for your original card. When your card is produced, the temporary slip becomes automatically invalid."],
  ["Can I stop and continue my application later?", "Yes. Click “Save & exit” at any step. Your answers and uploaded documents are securely saved, and you can resume anytime after signing in."],
  ["My application was queried. What should I do?", "Sign in to the applicant portal, read the Vetting Officer's query reason, upload the requested document, and submit your response. The Vetting Officer will re-review your submission."],
  ["What happens at the biometrics appointment?", "Bring your original passport/travel certificate and printed appointment slip. An enrollment officer captures all 10 fingerprints (both hands), takes a live facial photograph and captures your signature."],
  ["How do I know when my card is ready?", "You receive an e-mail and a notification on your dashboard when your physical residence card is produced and ready for collection at your chosen enrollment center."],
  ["How can someone check that my card is genuine?", <>Anyone can scan the QR code on the card, or enter the card and passport numbers on the <Link href="/verify" className="text-nis-primary underline">Verify card</Link> page.</>],
  ["I forgot my password.", <>Use <Link href="/forgot-password" className="text-nis-primary underline">Forgot password</Link> to receive a reset link by e-mail.</>],
];

export default function Faq() {
  return (
    <ContentLayout>
      <div className="mx-auto max-w-3xl">
        <h1 className="text-center text-2xl font-semibold text-slate-900">Frequently Asked Questions</h1>
        <p className="mt-2 text-center text-slate-600">Can&apos;t find your answer? <Link href="/contact" className="text-nis-primary underline">Contact us</Link>.</p>
        <div className="mt-10 space-y-3">
          {faqs.map(([q, a]) => (
            <details key={q} className="group rounded-xl border border-slate-200 bg-white p-5 open:border-nis-primary/40 open:shadow-sm">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                {q}
                <span className="text-xl text-nis-primary transition group-open:rotate-45" aria-hidden>+</span>
              </summary>
              <div className="mt-3 text-sm leading-relaxed text-slate-600">{a}</div>
            </details>
          ))}
        </div>
      </div>
    </ContentLayout>
  );
}
