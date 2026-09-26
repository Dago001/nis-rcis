"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Dl, Panel, Spinner } from "@/components/ui";
import { dateTime, naira, nisDate } from "@/lib/format";
import type { StaffPayment } from "@/lib/types";
import { useFetch } from "@/lib/use-fetch";

type Detail = StaffPayment & {
  receipt: { date: string | null; gateway: string; mode: string | null; gateway_reference: string | null; pan: string | null } | null;
  draft_data: Record<string, string | null> | null;
  draft_documents: { id: number; label: string; mime_type: string; size_bytes: number; url: string }[];
};

export default function PaymentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { data: p, error } = useFetch<Detail>("staff", `payments/${id}`);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!p) return <Spinner />;
  const d = p.draft_data ?? {};

  return (
    <div className="space-y-6">
      <PageTitle
        title={`Payment ${p.reference}`}
        subtitle={p.applicant ? `${p.applicant.name} · ${p.applicant.email}` : undefined}
        actions={<Link href="/staff/payments" className="text-sm font-medium text-nis-primary hover:underline">← All payments</Link>}
      />

      {p.application ? (
        <Alert tone="success">
          The applicant has submitted application{" "}
          <Link href={`/staff/applications/${p.application.id}`} className="font-medium underline">{p.application.application_number}</Link>. It is in the approval queue ({p.application.status_label}).
        </Alert>
      ) : p.status === "PAID" ? (
        <Alert tone="warning">
          Paid, but the application has not been submitted yet{p.progress ? `: the applicant is at step ${p.progress.current_step} of 7 (${p.progress.step_label})` : ""}. It enters the approval queue once they book biometrics and sign the declaration.
        </Alert>
      ) : null}

      <Panel title="Payment">
        <Dl
          items={[
            ["Status", <StatusBadge key="s" status={p.status === "PAID" ? "ISSUED" : "REJECTED"} label={p.status} />],
            ["Amount", naira(p.amount_naira)],
            ["Reference", p.reference],
            ["Paid on", dateTime(p.paid_at)],
            ["Gateway", p.receipt?.gateway ?? "Paystack"],
            ["Payment mode", p.receipt?.mode ?? p.channel ?? "—"],
            ["Gateway reference", p.receipt?.gateway_reference ?? "—"],
            ["Card", p.receipt?.pan ?? "—"],
          ]}
        />
      </Panel>

      {p.draft_data && (
        <>
          <Panel title="Application in progress (not yet submitted)">
            <Dl
              items={[
                ["Name", [d.surname, d.forenames].filter(Boolean).join(", ")],
                ["Nationality", d.nationality],
                ["Sex", d.sex],
                ["Date of birth", d.date_of_birth ? nisDate(d.date_of_birth) : null],
                ["Place of birth", d.place_of_birth],
                ["Profession", d.profession],
                ["Passport number", d.passport_number],
                ["Passport expiry", d.passport_expiry ? nisDate(d.passport_expiry) : null],
                ["Address in Nigeria", [d.domicile, d.domicile_lga, d.domicile_state].filter(Boolean).join(", ")],
                ["Phone", d.phone],
                ["E-mail", d.email],
                ["Emergency contact", [d.emergency_contact_name, d.emergency_contact_relation].filter(Boolean).join(" · ")],
                ["Emergency contact phone", d.emergency_contact_phone],
                ["Appointment", d.appointment_date ? `${nisDate(d.appointment_date)} ${d.appointment_time ?? ""}` : "Not booked yet"],
              ]}
            />
          </Panel>
          <Panel title="Documents uploaded so far">
            {p.draft_documents.length === 0 ? (
              <p className="text-sm text-slate-600">No documents uploaded yet.</p>
            ) : (
              <ul className="divide-y divide-slate-100 text-sm">
                {p.draft_documents.map((doc) => (
                  <li key={doc.id} className="flex items-center justify-between py-2">
                    <span>{doc.label}</span>
                    <a href={doc.url} target="_blank" rel="noopener noreferrer" className="font-medium text-nis-primary hover:underline">View</a>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </>
      )}
    </div>
  );
}
