import Link from "next/link";

export const metadata = { title: "FAQ" };

const faqs: [string, React.ReactNode][] = [
  ["Who needs a residence card?", "Foreign nationals who live in Nigeria (for employment, business or study) and hold a valid residence visa (STR) must obtain a residence card and renew it before it expires."],
  ["What documents do I need?", "Your international passport data page, your residence visa, a recent passport photograph and, if you are employed, your expatriate quota approval. Proof of address is optional but helps processing."],
  ["How much does it cost, and how do I pay?", "The fee is shown before you pay. Payment is made online by card, bank transfer or USSD through Paystack. Your payment is confirmed automatically before your application is submitted."],
  ["Can I stop and continue my application later?", "Yes. Click “Save & exit” at any step. Your answers and uploaded documents are kept, and you can continue from where you stopped after signing in."],
  ["My application was queried. What should I do?", "Sign in to the portal, read the officer's note, upload the corrected document and send your response. Your application then returns to the approval queue."],
  ["What happens at the biometrics appointment?", "Bring your original passport and your printed appointment slip. An officer confirms your identity, takes a live photograph and records your signature."],
  ["How do I know when my card is ready?", "You receive an e-mail and a notification in the portal when your card is ready for collection at your enrollment center."],
  ["How can someone check that my card is genuine?", <>Anyone can scan the QR code on the card, or enter the card and passport numbers on the <Link href="/verify" className="text-nis-primary underline">Verify card</Link> page.</>],
  ["I forgot my password.", <>Use <Link href="/forgot-password" className="text-nis-primary underline">Forgot password</Link> to receive a reset link by e-mail.</>],
];

export default function FaqPage() {
  return (
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
  );
}
