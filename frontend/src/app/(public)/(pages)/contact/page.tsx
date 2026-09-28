import Image from "next/image";
import Link from "next/link";

export const metadata = { title: "Contact" };

export default function ContactPage() {
  return (
    <div className="grid gap-10 md:grid-cols-2">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">Contact Support</h1>
        <p className="mt-3 text-slate-600">
          For help with an application, have your <strong>application number</strong> and <strong>passport number</strong> ready.
          Many questions are answered in the <Link href="/faq" className="text-nis-primary underline">FAQ</Link>.
        </p>

        <dl className="mt-8 space-y-6">
          <div>
            <dt className="font-semibold text-slate-900">NIS Headquarters</dt>
            <dd className="mt-1 text-slate-600">Umar Musa Yar&apos;Adua Express Way, Airport Road, Sauka, Abuja, FCT, Nigeria.</dd>
          </div>
          <div>
            <dt className="font-semibold text-slate-900">Office hours</dt>
            <dd className="mt-1 text-slate-600">Monday – Friday, 08:00 – 16:00 (WAT)</dd>
          </div>
          <div>
            <dt className="font-semibold text-slate-900">Enrollment centers</dt>
            <dd className="mt-1 text-slate-600">Biometrics are captured at the center you choose when booking your appointment. Its address is printed on your appointment slip.</dd>
          </div>
          <div>
            <dt className="font-semibold text-slate-900">Check your application</dt>
            <dd className="mt-1 text-slate-600">
              <Link href="/track" className="text-nis-primary underline">Track an application</Link> or{" "}
              <Link href="/login" className="text-nis-primary underline">sign in</Link> to see officer notes and respond to queries.
            </dd>
          </div>
        </dl>
      </div>
      <div className="relative min-h-72 overflow-hidden rounded-2xl shadow-lg">
        <Image src="/images/hq-reception.jpg" alt="NIS Headquarters reception" fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover" />
      </div>
    </div>
  );
}
