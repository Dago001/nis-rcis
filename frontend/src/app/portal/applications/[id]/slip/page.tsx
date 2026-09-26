"use client";

import Image from "next/image";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState, type ReactNode } from "react";
import { Barcode } from "@/components/Barcode";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { naira, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";

type Payment = {
  reference: string;
  amount_naira: number;
  date: string | null;
  gateway: string;
  mode: string | null;
  gateway_reference: string | null;
  pan: string | null;
};

type Slip = {
  application: Application;
  photo_url: string | null;
  qr_payload: string;
  appointment_slip_available: boolean;
  payment: Payment | null;
  documents: { type: string; label: string }[];
  submitted_at: string | null;
};

/** 22/09/2026 */
function ddmmyyyy(value?: string | null): string {
  if (!value) return "Not Provided";
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString("en-GB");
}

/** 22/09/2026 20:51 */
function dateTime(value?: string | null): string {
  if (!value) return "Not Provided";
  const d = new Date(value);
  return `${d.toLocaleDateString("en-GB")} ${d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" })}`;
}

/** 23/May/1994 */
function birthDate(value?: string | null): string {
  if (!value) return "Not Provided";
  const d = new Date(`${value.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(d.getTime())) return value;
  return `${String(d.getDate()).padStart(2, "0")}/${d.toLocaleString("en-GB", { month: "long" })}/${d.getFullYear()}`;
}

const show = (v?: string | null) => (v && v.trim() ? v : "Not Provided");
const title = (v?: string | null) => (v ? v.toLowerCase().replace(/(^|[\s-])\p{L}/gu, (m) => m.toUpperCase()) : "Not Provided");

/** Application print-out and biometrics appointment slip. */
function SlipView() {
  const { id } = useParams<{ id: string }>();
  const appointment = useSearchParams().get("kind") === "appointment";
  const [slip, setSlip] = useState<Slip | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<Slip>("applicant", `applications/${id}/slip`).then(setSlip).catch((e) => setError(e.message));
  }, [id]);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!slip) return <Spinner />;
  if (appointment && !slip.appointment_slip_available) return <Alert tone="warning">The appointment slip is available once your application is approved for biometrics.</Alert>;

  return (
    <div className="space-y-4">
      <div className="no-print flex justify-end gap-2">
        <Button onClick={() => window.print()}>Print / save as PDF</Button>
      </div>
      {appointment ? <AppointmentSlip slip={slip} /> : <ApplicationSlip slip={slip} />}
    </div>
  );
}

/* ------------------------------------------------------------------ Application print-out */

function Box({ title: heading, children, className = "" }: { title: string; children: ReactNode; className?: string }) {
  return (
    <section className={`break-inside-avoid border border-slate-300 ${className}`}>
      <h2 className="border-b border-slate-300 bg-slate-50 px-2 py-1 text-[15px] font-medium text-slate-900">{heading}</h2>
      {children}
    </section>
  );
}

function Pairs({ rows }: { rows: [string, string, string?, string?][] }) {
  return (
    <div className="divide-y divide-slate-300">
      {rows.map(([k1, v1, k2, v2]) => (
        <div key={k1} className="grid grid-cols-2 divide-x divide-slate-300">
          <div className="px-2 py-1.5">
            <div className="text-[13px] font-medium text-slate-900">{k1}</div>
            <div className="text-[13px] text-slate-700">{v1}</div>
          </div>
          <div className="px-2 py-1.5">
            {k2 && <div className="text-[13px] font-medium text-slate-900">{k2}</div>}
            {k2 && <div className="text-[13px] text-slate-700">{v2}</div>}
          </div>
        </div>
      ))}
    </div>
  );
}

function ApplicationSlip({ slip }: { slip: Slip }) {
  const a = slip.application;
  const paid = a.payment_status === "PAID";
  const center = a.enrollment_center;
  const approval =
    a.status === "PENDING_APPROVAL" ? "Waiting for approval" : a.status === "QUERIED" ? "Queried – action required" : a.status_label;

  return (
    <article className="mx-auto max-w-[210mm] bg-white p-6 text-slate-900 shadow-sm print:p-0 print:shadow-none">
      {/* Page 1 */}
      <div className="flex justify-center pb-3">
        <Image src="/images/nis-logo.png" alt="Nigeria Immigration Service" width={56} height={56} />
        <div className="ml-2 self-center text-[11px] font-bold leading-tight text-nis-primary">NIGERIA<br />IMMIGRATION<br />SERVICE</div>
      </div>

      <div className="border border-slate-300">
        <div className="flex">
          <div className="w-[88px] shrink-0 border-r border-slate-300 p-1">
            {slip.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={slip.photo_url} alt="Applicant" className="h-[104px] w-full object-cover" />
            ) : (
              <div className="flex h-[104px] items-center justify-center bg-slate-100 text-[10px] text-slate-400">No photo</div>
            )}
          </div>
          <div className="flex flex-1 flex-col">
            <div className="flex flex-1 items-start justify-between gap-4 border-b border-slate-300 px-3 pt-2">
              <div>
                <Barcode value={a.application_number} height={40} moduleWidth={1.1} />
                <div className="text-[15px] tracking-wide">{a.application_number}</div>
              </div>
              <div className="self-center text-lg">{a.type === "RENEWAL" ? "Residence Card Renewal" : a.type === "REPLACE" ? "Residence Card Replacement" : "Residence Card Application"}</div>
            </div>
            <div className="grid grid-cols-5 gap-2 px-3 py-2 text-[13px]">
              <div><div className="font-medium">Reference Number</div><div>{a.reference_number}</div></div>
              <div><div className="font-medium">Application Number</div><div>{a.application_number}</div></div>
              <div><div className="font-medium">Application Date</div><div>{ddmmyyyy(slip.submitted_at ?? a.submitted_at)}</div></div>
              <div>
                <div className="font-medium">Payment Status</div>
                <span className={`mt-0.5 inline-block rounded-full border-2 px-2 text-[11px] font-bold ${paid ? "border-nis-primary text-nis-primary" : "border-amber-500 text-amber-600"}`}>{paid ? "PAID" : "PENDING"}</span>
              </div>
              <div><div className="font-medium">Approval Status</div><div>{approval}</div></div>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-2 space-y-2">
        <Box title="Residence Card Details">
          <Pairs
            rows={[
              ["Type of Application", applicationType(a, true), "Applying For", "Residence Card (Expatriate)"],
              ["Card Validity", "2 Years", "Processing Office", center ? `${center.name}` : "NIS Headquarters, Abuja"],
            ]}
          />
        </Box>

        <Box title="Personal Details">
          <Pairs
            rows={[
              ["Full Name", `${a.sex === "FEMALE" ? "Ms." : "Mr."} ${a.surname}, ${a.forenames}`, "Date of Birth", birthDate(a.date_of_birth)],
              ["Gender", title(a.sex), "Place of Birth", show(a.place_of_birth)],
              ["Nationality", show(a.nationality), "Occupation", show(a.profession)],
              ["Passport Number", a.passport_number, "Passport Expiry Date", ddmmyyyy(a.passport_expiry)],
              ["National Identification Number (NIN)", show(a.national_id_number), "Email", a.email],
            ]}
          />
        </Box>

        <Box title="Contact Information">
          <Pairs
            rows={[
              ["Contact Number", a.phone, "State of Residence", show(a.domicile_state)],
              ["Residential Address in Nigeria", [a.domicile, a.domicile_lga, a.domicile_state].filter(Boolean).join(", "), "Local Government Area", show(a.domicile_lga)],
            ]}
          />
        </Box>

        <Box title="Emergency Contact Information">
          <Pairs
            rows={[
              ["Name of Contact", a.emergency_contact_name, "Relationship", title(a.emergency_contact_relation)],
              ["Address", [a.emergency_contact_address, a.emergency_contact_lga, a.emergency_contact_state].filter(Boolean).join(", "), "Contact Number", a.emergency_contact_phone],
            ]}
          />
        </Box>

        <Box title="Biometrics Appointment">
          <Pairs rows={[["Enrollment Center", show(center?.name), "Appointment Date / Time", `${ddmmyyyy(a.appointment_date)} ${a.appointment_time}`]]} />
        </Box>
      </div>

      {/* Page 2 */}
      <div className="mt-6 grid grid-cols-2 items-start gap-0 break-before-page print:pt-4">
        <Box title="Supporting Documents" className="border-r-0">
          <div className="px-2 py-1.5">
            <div className="text-[13px] text-nis-primary">Documents submitted</div>
            <ol className="mt-1 space-y-1 text-[13px] font-medium">
              {slip.documents.length ? slip.documents.map((d, i) => <li key={d.type} className="border-b border-slate-200 pb-1 last:border-0">{i + 1}. {d.label}</li>) : <li>None</li>}
            </ol>
          </div>
        </Box>
        <div className="space-y-2">
          <Box title="Fee Details">
            <div className="flex justify-between px-2 py-1.5 text-[13px]"><span className="font-medium">Residence Card Fee</span><span>{naira(slip.payment?.amount_naira ?? a.fee_amount_naira)}.00</span></div>
          </Box>
          <Box title="Payment Details">
            <div className="divide-y divide-slate-200 text-[13px]">
              {([
                ["Payment Date", slip.payment?.date ? dateTime(slip.payment.date) : "Not paid"],
                ["Payment Gateway", slip.payment?.gateway ?? "Paystack"],
                ["Payment Mode", show(slip.payment?.mode)],
                ["Payment Reference", show(slip.payment?.reference)],
                ["Gateway Reference", show(slip.payment?.gateway_reference)],
                ["PAN", show(slip.payment?.pan)],
              ] as [string, string][]).map(([k, v]) => (
                <div key={k} className="flex justify-between gap-4 px-2 py-1.5"><span className="font-medium">{k}</span><span>{v}</span></div>
              ))}
            </div>
          </Box>
        </div>
      </div>
    </article>
  );
}

/* ------------------------------------------------------------------ Appointment slip */

function Row({ k, v }: { k: string; v: ReactNode }) {
  return <p className="leading-relaxed"><strong>{k}:</strong> {v}</p>;
}

function AppointmentSlip({ slip }: { slip: Slip }) {
  const a = slip.application;
  const center = a.enrollment_center;
  return (
    <article className="mx-auto max-w-[210mm] bg-white px-14 py-10 text-[15px] text-slate-800 shadow-sm print:p-6 print:shadow-none">
      <div className="flex flex-col items-center">
        <div className="flex items-center gap-2">
          <Image src="/images/nis-logo.png" alt="Nigeria Immigration Service" width={84} height={84} />
          <div className="text-2xl font-extrabold leading-none text-nis-primary">NIGERIA<br />IMMIGRATION<br />SERVICE</div>
        </div>
        <h1 className="mt-3 text-3xl font-light">Appointment Slip</h1>
      </div>

      <div className="mt-4 flex justify-end">
        <Barcode value={a.reference_number} height={48} moduleWidth={1.4} />
      </div>

      <h2 className="mt-2 border-b border-slate-200 pb-1 text-2xl font-semibold">Appointment Details</h2>
      <div className="mt-2">
        <Row k="Appointment Tracking No." v={a.reference_number} />
        <Row k="Appointment Request Date" v={dateTime(slip.submitted_at ?? a.submitted_at)} />
        <Row k="Appointment Date" v={`${a.appointment_date} ${a.appointment_time}`} />
        <Row k="Appointment for" v="Residence Card (biometrics capture)" />
        <Row k="Center Address" v={center ? `${center.name}: ${center.address}` : "NIS Headquarters, Abuja"} />
      </div>

      <h2 className="mt-8 border-b border-slate-200 pb-1 text-2xl font-semibold">Personal &amp; Contact Details</h2>
      <div className="mt-2">
        <Row k="Application ID" v={a.application_number} />
        <Row k="Application Reference ID" v={a.reference_number} />
        <Row k="Name" v={`${a.surname} ${a.forenames}`} />
        <Row k="Passport Number" v={a.passport_number} />
      </div>

      <h2 className="mt-8 border-b border-slate-200 pb-1 text-2xl font-semibold">Instructions</h2>
      <ul className="mt-2 list-disc space-y-1 pl-6">
        <li>Ensure that you verify the status of your application in the applicant portal before your appointment date.</li>
        <li>
          You must bring the following items for the appointment:
          <ol className="mt-1 list-decimal space-y-0.5 pl-6">
            <li>Printout of your application slip</li>
            <li>Printout of this appointment slip</li>
            <li>Your original international passport</li>
            <li>For a renewal, your current residence card</li>
          </ol>
        </li>
        <li>Arrive 30 minutes before your appointment time.</li>
      </ul>
      <p className="mt-10 text-center text-xs text-slate-500">Page 1</p>
    </article>
  );
}

export default function SlipPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <SlipView />
    </Suspense>
  );
}
