"use client";

import Image from "next/image";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState, type ReactNode } from "react";
import { Barcode } from "@/components/Barcode";
import { QrCode } from "@/components/QrCode";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { naira, applicationType } from "@/lib/format";
import type { Application } from "@/lib/types";
import { withBase } from "@/lib/base-path";
import { formatRelationship } from "@/lib/validation";

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
  signature_url?: string | null;
  qr_payload: string;
  appointment_slip_available: boolean;
  temporary_slip_available?: boolean;
  is_card_produced?: boolean;
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

/** Application print-out, biometrics appointment slip, and temporary residence slip. */
function SlipView() {
  const { id } = useParams<{ id: string }>();
  const searchParams = useSearchParams();
  const appointment = searchParams.get("kind") === "appointment";
  const temporary = searchParams.get("kind") === "temporary";
  const fromStaff = searchParams.get("from") === "staff";
  const returnTo = searchParams.get("return_to");
  const [slip, setSlip] = useState<Slip | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const isStaff = fromStaff || (returnTo && returnTo.startsWith("/staff/"));
    const primaryScope = isStaff ? "staff" : "applicant";
    const fallbackScope = isStaff ? "applicant" : "staff";

    api<Slip>(primaryScope, `applications/${id}/slip`)
      .then(setSlip)
      .catch(() => {
        api<Slip>(fallbackScope, `applications/${id}/slip`)
          .then(setSlip)
          .catch((e) => setError(e.message));
      });
  }, [id, fromStaff, returnTo]);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!slip) return <Spinner />;
  if (appointment && !slip.appointment_slip_available) return <Alert tone="warning">The appointment slip is available once your biometrics appointment is booked.</Alert>;
  if (temporary && !slip.temporary_slip_available) return <Alert tone="warning">The temporary residence slip is only available after your biometrics have been captured at the enrollment center.</Alert>;

  const isAdministrative = fromStaff || Boolean(returnTo && returnTo.startsWith("/staff/"));
  const backHref = isAdministrative
    ? (returnTo && returnTo.startsWith("/staff/") ? returnTo : (id ? `/staff/approvals/${id}` : "/staff/approvals"))
    : "/portal";
  const backLabel = isAdministrative ? "← Back" : "← Back to Dashboard";

  return (
    <div className="space-y-4">
      {/* Top action buttons (excluded from print) */}
      <div className="no-print mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3">
        <Link
          href={backHref}
          className="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-800 shadow-xs transition-colors hover:border-nis-primary hover:text-nis-primary"
        >
          {backLabel}
        </Link>
        <Button onClick={() => window.print()}>
          Print / Save as PDF
        </Button>
      </div>

      <div className="w-full overflow-x-auto pb-6">
        {temporary ? <TemporarySlip slip={slip} /> : appointment ? <AppointmentSlip slip={slip} /> : <ApplicationSlip slip={slip} />}
      </div>
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
        <div key={k1} className="grid grid-cols-1 divide-y divide-slate-300 sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:grid-cols-2 print:divide-x print:divide-y-0">
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
    <article className="mx-auto max-w-[210mm] min-w-[320px] bg-white p-3 text-slate-900 shadow-sm sm:p-6 print:p-0 print:shadow-none">
      {/* Page 1 */}
      <div className="flex justify-center pb-3">
        <Image src={withBase("/images/nis-logo.png")} alt="Nigeria Immigration Service" width={56} height={56} />
        <div className="ml-2 self-center text-[11px] font-bold leading-tight text-nis-primary">NIGERIA<br />IMMIGRATION<br />SERVICE</div>
      </div>

      <div className="border border-slate-300">
        <div className="flex">
          <div className="w-[88px] shrink-0 border-r border-slate-300 p-1">
            {slip.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={slip.photo_url}
                alt="Applicant"
                className="h-[104px] w-full object-cover"
                onError={(e) => {
                  e.currentTarget.style.display = "none";
                  const fallback = e.currentTarget.parentElement?.querySelector(".photo-fallback");
                  if (fallback) (fallback as HTMLElement).style.display = "flex";
                }}
              />
            ) : null}
            <div className={`photo-fallback flex h-[104px] flex-col items-center justify-center bg-slate-100 text-slate-400 ${slip.photo_url ? "hidden" : ""}`}>
              <svg className="h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="currentColor">
                <path fillRule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.6-7.812-1.7a.75.75 0 01-.437-.695z" clipRule="evenodd" />
              </svg>
              <span className="text-[10px]">Applicant</span>
            </div>
          </div>
          <div className="flex flex-1 flex-col">
            <div className="flex flex-1 flex-col gap-2 border-b border-slate-300 px-3 pt-2 sm:flex-row sm:items-start sm:justify-between print:flex-row print:justify-between">
              <div>
                <Barcode value={a.application_number} height={40} moduleWidth={1.1} />
                <div className="text-[13px] tracking-wide sm:text-[15px]">{a.application_number}</div>
              </div>
              <div className="self-start text-base font-medium sm:self-center sm:text-lg print:self-center print:text-lg">{a.type === "RENEWAL" ? "Residence Card Renewal" : a.type === "REPLACE" ? "Residence Card Replacement" : "Residence Card Application"}</div>
            </div>
            <div className="grid grid-cols-2 gap-2 px-3 py-2 text-[12px] sm:grid-cols-5 sm:text-[13px] print:grid-cols-5 print:text-[13px]">
              <div><div className="font-medium text-slate-500">Reference Number</div><div className="font-semibold text-slate-900 break-all">{a.reference_number}</div></div>
              <div><div className="font-medium text-slate-500">Application Number</div><div className="font-semibold text-slate-900 break-all">{a.application_number}</div></div>
              <div><div className="font-medium text-slate-500">Application Date</div><div>{ddmmyyyy(slip.submitted_at ?? a.submitted_at)}</div></div>
              <div>
                <div className="font-medium text-slate-500">Payment Status</div>
                <span className={`mt-0.5 inline-block rounded-full border-2 px-2 text-[11px] font-bold ${paid ? "border-nis-primary text-nis-primary" : "border-amber-500 text-amber-600"}`}>{paid ? "PAID" : "PENDING"}</span>
              </div>
              <div className="col-span-2 sm:col-span-1 print:col-span-1"><div className="font-medium text-slate-500">Approval Status</div><div>{approval}</div></div>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-2 space-y-2">
        <Box title="ECOWAS Residence Card Details">
          <Pairs
            rows={[
              ["Type of Application", applicationType(a, true), "Applying For", "ECOWAS Residence Card"],
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

        <Box title="Emergency Contact Information (Nigeria)">
          <Pairs
            rows={[
              ["Name of Contact", a.emergency_contact_name, "Relationship", title(formatRelationship(a.emergency_contact_relation))],
              ["Address", [a.emergency_contact_address, a.emergency_contact_lga, a.emergency_contact_state].filter(Boolean).join(", "), "Contact Number", a.emergency_contact_phone],
            ]}
          />
        </Box>

        {Boolean(a.home_country_emergency_contact_name) && (
          <Box title="Emergency Contact Information (Home Country)">
            <Pairs
              rows={[
                ["Name of Contact", a.home_country_emergency_contact_name, "Relationship", title(formatRelationship(a.home_country_emergency_contact_relation))],
                ["Address", a.home_country_emergency_contact_address, "Contact Number", a.home_country_emergency_contact_phone],
              ]}
            />
          </Box>
        )}

        <Box title="Biometrics Appointment">
          <Pairs
            rows={[
              [
                "Enrollment Center", show(center?.name || a.enrollment_center?.command_name || a.enrollment_center?.name),
                "Zone / State", show([center?.zone_name, center?.state].filter(Boolean).join(" • ") || "Zone D (Minna) • FCT"),
              ],
              [
                "Center Location", show(center?.address || (center?.name ? `${center.name}, ${center.state}` : "Nigeria Immigration Service Headquarters, Sauka, Airport Road, Abuja")),
                "Appointment Date / Time", `${ddmmyyyy(a.appointment_date)} ${a.appointment_time}`,
              ],
            ]}
          />
        </Box>
      </div>

      {/* Page 2 */}
      <div className="mt-6 grid grid-cols-1 items-start gap-3 sm:grid-cols-2 sm:gap-0 print:grid-cols-2 break-before-page print:pt-4">
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

  let statusText = "Pending Approval";
  let isApproved = false;

  if (["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(a.status)) {
    statusText = "Approved for Biometrics";
    isApproved = true;
  } else if (a.status === "QUERIED") {
    statusText = "Queried — Action Required";
  } else if (a.status === "REJECTED") {
    statusText = "Rejected";
  } else {
    // Both PENDING_APPROVAL and VETTED remain internal pending states to the applicant
    statusText = "Pending Approval";
  }

  return (
    <article className="mx-auto max-w-[210mm] min-w-[320px] bg-white px-4 py-6 sm:px-14 sm:py-10 text-[14px] sm:text-[15px] text-slate-800 shadow-sm print:p-6 print:shadow-none">
      <div className="flex flex-col items-center">
        <div className="flex items-center gap-2">
          <Image src={withBase("/images/nis-logo.png")} alt="Nigeria Immigration Service" width={84} height={84} />
          <div className="text-2xl font-extrabold leading-none text-nis-primary">NIGERIA<br />IMMIGRATION<br />SERVICE</div>
        </div>
        <h1 className="mt-3 text-3xl font-light">Appointment Slip</h1>
      </div>

      <div className="mt-4 flex justify-center sm:justify-end">
        <Barcode value={a.reference_number} height={48} moduleWidth={1.4} />
      </div>

      {!isApproved && a.status !== "REJECTED" && a.status !== "QUERIED" && (
        <div className="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3.5 text-xs text-amber-900">
          <strong>Important Notice:</strong> Your application is currently awaiting administrative approval. Attendance at the biometrics enrollment center is strictly subject to final approval. Please verify that your status updates to &ldquo;Approved for Biometrics&rdquo; before visiting the enrollment center.
        </div>
      )}

      {isApproved && (
        <div className="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3.5 text-xs text-emerald-900">
          <strong>Clearance Granted:</strong> Your application has been approved. You are authorized to attend biometrics in accordance with your scheduled appointment details below.
        </div>
      )}

      <h2 className="mt-4 border-b border-slate-200 pb-1 text-2xl font-semibold">Appointment Details</h2>
      <div className="mt-2">
        <Row k="Appointment Tracking No." v={a.reference_number} />
        <Row k="Appointment Request Date" v={dateTime(slip.submitted_at ?? a.submitted_at)} />
        <Row k="Appointment Date" v={`${a.appointment_date} ${a.appointment_time}`} />
        <Row k="Appointment for" v="Residence Card (biometrics capture)" />
        <Row k="Enrollment Center" v={center?.name || a.enrollment_center?.command_name || a.enrollment_center?.name || "NIS Headquarters, Abuja"} />
        <Row k="Zone & State" v={[center?.zone_name, center?.state].filter(Boolean).join(" • ") || "Zone D (Minna) • FCT"} />
        <Row k="Center Address" v={center?.address ? `${center.name}: ${center.address}` : (center?.name ? `${center.name}, ${center.state}` : "Nigeria Immigration Service Headquarters, Sauka, Airport Road, Abuja")} />
        <Row
          k="Application Status"
          v={
            <span className={`font-semibold ${isApproved ? "text-emerald-700" : "text-amber-700"}`}>
              {statusText}
            </span>
          }
        />
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
        <li>
          Ensure that you verify the status of your application in the applicant portal before your appointment date.
          {!isApproved && " Attendance is subject to final administrative approval."}
        </li>
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

/* ------------------------------------------------------------------ Temporary Residence Slip */

function TemporarySlip({ slip }: { slip: Slip }) {
  const a = slip.application;
  const center = a.enrollment_center;
  const isInvalid = Boolean(slip.is_card_produced);

  return (
    <article className="relative mx-auto max-w-[210mm] min-w-[320px] bg-white p-4 text-slate-900 shadow-sm sm:p-8 print:p-0 print:shadow-none overflow-hidden">
      {/* If card is already produced, show a prominent diagonal invalidity watermark */}
      {isInvalid && (
        <div className="pointer-events-none absolute inset-0 z-30 flex items-center justify-center opacity-30 select-none">
          <div className="rotate-[-35deg] border-8 border-red-700 px-10 py-4 text-center text-5xl font-black uppercase tracking-widest text-red-700">
            INVALID — CARD ISSUED<br />
            <span className="text-2xl font-bold tracking-wider">NULL &amp; VOID</span>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="border-b-2 border-nis-primary pb-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Image src={withBase("/images/nis-logo.png")} alt="NIS Logo" width={68} height={68} />
            <div>
              <div className="text-xs font-bold uppercase tracking-widest text-slate-600">Federal Republic of Nigeria</div>
              <div className="text-xl font-black text-nis-primary leading-tight">NIGERIA IMMIGRATION SERVICE</div>
              <div className="text-xs font-semibold text-slate-700">Directorate of Visa &amp; Residency · ECOWAS Unit</div>
            </div>
          </div>
          <div className="text-right">
            <div className="inline-block rounded-md bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-800 border border-amber-300">
              Interim Attestation
            </div>
            <div className="mt-1 text-xs text-slate-500">Form NIS-EC-TR01</div>
          </div>
        </div>

        <div className="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-center text-white">
          <h1 className="text-lg font-bold uppercase tracking-wider sm:text-xl">
            Temporary Residence Slip · Attestation Temporaire de Résidence
          </h1>
          <p className="text-xs text-slate-300">
            Official proof of biometric enrollment pending physical ECOWAS Residence Card production
          </p>
        </div>
      </div>

      {/* Invalidation Alert Banner */}
      {isInvalid ? (
        <div className="mt-4 rounded-lg border-2 border-red-600 bg-red-50 p-3 text-center text-red-800 font-bold">
          ⚠️ NOTICE: THIS TEMPORARY SLIP IS EXPIRED AND INVALID. The official ECOWAS Residence Card has been produced/issued for this applicant.
        </div>
      ) : (
        <div className="mt-3 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900">
          ✓ <strong>VALID INTERIM DOCUMENT:</strong> The bearer has successfully enrolled physical biometrics. Valid across Nigeria until the original smart card is collected.
        </div>
      )}

      {/* Top Identity Block: Photo, Signature, Application Numbers, QR Code */}
      <div className="mt-4 grid grid-cols-1 gap-4 border border-slate-300 p-4 sm:grid-cols-4 print:grid-cols-4">
        {/* Photo & Signature */}
        <div className="flex flex-col items-center justify-between border-b pb-3 sm:border-b-0 sm:border-r sm:pb-0 sm:pr-4 print:border-b-0 print:border-r print:pr-4">
          <div className="h-36 w-28 overflow-hidden rounded border border-slate-300 bg-slate-100">
            {slip.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={slip.photo_url} alt="Applicant" className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-xs text-slate-400">Photo</div>
            )}
          </div>
          <div className="mt-2 w-full text-center">
            <span className="text-[10px] font-semibold uppercase text-slate-500">Live Signature</span>
            <div className="h-10 w-full overflow-hidden rounded border border-slate-200 bg-white p-1">
              {slip.signature_url ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={slip.signature_url} alt="Signature" className="h-full w-full object-contain" />
              ) : (
                <div className="text-[11px] text-slate-400 italic">Captured on desk</div>
              )}
            </div>
          </div>
        </div>

        {/* Primary Data */}
        <div className="space-y-2 sm:col-span-2 print:col-span-2">
          <div>
            <div className="text-[11px] uppercase text-slate-500 font-semibold">Full Legal Name</div>
            <div className="text-base font-bold text-slate-900">{a.surname}, {a.forenames}</div>
          </div>
          <div className="grid grid-cols-2 gap-2 text-xs">
            <div>
              <span className="text-slate-500">Nationality:</span>
              <div className="font-semibold">{a.nationality}</div>
            </div>
            <div>
              <span className="text-slate-500">Gender / Sex:</span>
              <div className="font-semibold">{a.sex}</div>
            </div>
            <div>
              <span className="text-slate-500">Date of Birth:</span>
              <div className="font-semibold">{birthDate(a.date_of_birth)}</div>
            </div>
            <div>
              <span className="text-slate-500">Place of Birth:</span>
              <div className="font-semibold">{show(a.place_of_birth)}</div>
            </div>
            <div>
              <span className="text-slate-500">Travel Document:</span>
              <div className="font-semibold">{a.passport_number}</div>
            </div>
            <div>
              <span className="text-slate-500">Category:</span>
              <div className="font-semibold uppercase">{show(a.category || "WORK")}</div>
            </div>
          </div>
          <div className="pt-1">
            <Barcode value={a.application_number} height={32} moduleWidth={1.1} />
            <div className="text-[11px] font-mono tracking-wider text-slate-600">{a.application_number}</div>
          </div>
        </div>

        {/* QR Code & Reference */}
        <div className="flex flex-col items-center justify-center border-t pt-3 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-4 print:border-t-0 print:border-l print:pl-4">
          <QrCode value={slip.qr_payload} size={110} />
          <span className="mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Scan to Verify</span>
          <span className="text-[10px] font-mono text-slate-600 break-all">{a.reference_number}</span>
        </div>
      </div>

      {/* Address & Enrollment Particulars */}
      <div className="mt-4 grid grid-cols-1 divide-y border border-slate-300 sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:grid-cols-2 print:divide-x print:divide-y-0 text-xs">
        <div className="p-3 space-y-1">
          <div className="font-bold uppercase text-slate-800 border-b pb-1">Residential Address (Nigeria)</div>
          <div><span className="text-slate-500">Street:</span> <span className="font-medium">{a.domicile}</span></div>
          <div><span className="text-slate-500">LGA:</span> <span className="font-medium">{show(a.domicile_lga)}</span></div>
          <div><span className="text-slate-500">State:</span> <span className="font-medium">{show(a.domicile_state)}</span></div>
          <div><span className="text-slate-500">Phone:</span> <span className="font-medium">{a.phone}</span></div>
        </div>
        <div className="p-3 space-y-1">
          <div className="font-bold uppercase text-slate-800 border-b pb-1">Enrollment &amp; Vetting Particulars</div>
          <div><span className="text-slate-500">Enrollment Center:</span> <span className="font-medium">{center?.name ?? "NIS Headquarters, Abuja"}</span></div>
          <div><span className="text-slate-500">Biometrics Status:</span> <span className="font-semibold text-emerald-700">10 Fingers &amp; Facial Captured</span></div>
          <div><span className="text-slate-500">Captured On:</span> <span className="font-medium">{dateTime(a.biometrics_captured_at ?? (a as { updated_at?: string | null }).updated_at)}</span></div>
          <div><span className="text-slate-500">Fee Status:</span> <span className="font-bold text-nis-primary">{a.payment_status}</span></div>
        </div>
      </div>

      {/* Legal Conditions & Disclaimers */}
      <div className="mt-4 rounded border border-slate-200 bg-slate-50 p-3 text-[11px] text-slate-700 space-y-1">
        <div className="font-bold uppercase text-slate-900">Terms of Temporary Residence Slip:</div>
        <ol className="list-decimal pl-4 space-y-0.5">
          <li>This slip is issued under the authority of the Nigeria Immigration Service as temporary attestation of lawful ECOWAS residence enrollment.</li>
          <li>It affords the holder lawful resident status in the Federal Republic of Nigeria during the period of card production.</li>
          <li><strong>AUTOMATIC EXPIRATION:</strong> This temporary slip immediately becomes <strong className="text-red-700">NULL, VOID AND INVALID</strong> once the holder&apos;s physical ECOWAS Residence Card is produced and issued.</li>
          <li>Law enforcement and security agencies may verify the authenticity of this slip in real-time using the QR code.</li>
        </ol>
      </div>

      {/* Official Sign-off Box */}
      <div className="mt-5 grid grid-cols-2 gap-8 text-center text-xs">
        <div>
          <div className="h-10 border-b border-dashed border-slate-400"></div>
          <div className="mt-1 font-semibold text-slate-700">Holder&apos;s Signature</div>
        </div>
        <div>
          <div className="h-10 border-b border-dashed border-slate-400 flex items-center justify-center text-[11px] font-bold text-nis-primary">
            NIGERIA IMMIGRATION SERVICE
          </div>
          <div className="mt-1 font-semibold text-slate-700">Authorized Immigration Officer / Official Stamp</div>
        </div>
      </div>

      <p className="mt-6 text-center text-[10px] text-slate-400">
        NIS RCIS System · Document ID: TR-{a.reference_number} · Page 1 of 1
      </p>
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
