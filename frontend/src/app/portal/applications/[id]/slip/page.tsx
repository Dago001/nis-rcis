"use client";

import Image from "next/image";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { QrCode } from "@/components/QrCode";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application } from "@/lib/types";

type Slip = { application: Application; photo_url: string | null; qr_payload: string; appointment_slip_available: boolean };

/** Printable application slip / biometrics appointment slip (legacy print-*-slip.php). */
function SlipView() {
  const { id } = useParams<{ id: string }>();
  const appointment = useSearchParams().get("kind") === "appointment";
  const [slip, setSlip] = useState<Slip | null>(null);

  useEffect(() => {
    api<Slip>("applicant", `applications/${id}/slip`).then(setSlip);
  }, [id]);

  if (!slip) return <Spinner />;
  if (appointment && !slip.appointment_slip_available) return <Alert tone="warning">The appointment slip is available once your application is approved for biometrics.</Alert>;

  const a = slip.application;
  const rows: [string, string][] = [
    ["Application number", a.application_number],
    ["Reference", a.reference_number],
    ["Surname", a.surname],
    ["Other names", a.forenames],
    ["Nationality", a.nationality],
    ["Date of birth", nisDate(a.date_of_birth)],
    ["Passport number", a.passport_number],
    ["Application type", a.type === "RENEWAL" ? "RENEWAL" : "NEW RESIDENCE CARD"],
    ["Fee", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
    ["Enrollment center", a.enrollment_center?.name ?? ""],
    ["Appointment", `${nisDate(a.appointment_date)} AT ${a.appointment_time}`],
    ["Submitted", nisDate(a.submitted_at)],
  ];

  return (
    <div className="space-y-4">
      <div className="no-print flex justify-end">
        <Button onClick={() => window.print()}>Print slip</Button>
      </div>
      <article className="mx-auto max-w-3xl border-2 border-nis-green-dark bg-white p-8">
        <header className="flex items-center gap-4 border-b-2 border-nis-gold pb-4">
          <Image src="/nis-crest.png" alt="" width={64} height={64} />
          <div className="flex-1">
            <div className="text-lg font-bold">NIGERIA IMMIGRATION SERVICE</div>
            <div className="text-sm">Directorate of Visa and Residency</div>
            <div className="mt-1 text-sm font-semibold uppercase tracking-wide text-nis-green-dark">
              {appointment ? "Biometrics Appointment Slip" : "Residence Card Application Slip"}
            </div>
          </div>
          <QrCode value={slip.qr_payload} size={96} />
        </header>
        <div className="mt-6 flex gap-6">
          <table className="flex-1 text-sm">
            <tbody>
              {rows.map(([k, v]) => (
                <tr key={k} className="border-b border-slate-100">
                  <th className="w-44 py-1.5 pr-3 text-left font-medium text-slate-600">{k}</th>
                  <td className="py-1.5 font-semibold uppercase">{v}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {slip.photo_url && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={slip.photo_url} alt="Applicant" className="h-40 w-32 border object-cover" />
          )}
        </div>
        <footer className="mt-6 space-y-1 text-xs text-slate-600">
          {appointment ? (
            <>
              <p>Present this slip with your ORIGINAL international passport at the enrollment center 30 minutes before your appointment.</p>
              <p>Officers will scan the QR code to retrieve your application.</p>
            </>
          ) : (
            <p>Keep this slip. You need the application number and your passport number to track your application.</p>
          )}
        </footer>
      </article>
    </div>
  );
}

export default function SlipPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <SlipView />
    </Suspense>
  );
}
