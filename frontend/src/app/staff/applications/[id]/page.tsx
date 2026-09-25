"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { DocumentList, History } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Dl, Field, Panel, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime, naira, nisDate } from "@/lib/format";
import type { Application } from "@/lib/types";

type Detail = { data: Application; photo_url: string | null; payments: { reference: string; status: string; amount_kobo: number; verified_at: string | null }[] };

export default function StaffApplicationPage() {
  const { id } = useParams<{ id: string }>();
  const user = useStaff();
  const [detail, setDetail] = useState<Detail | null>(null);
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => api<Detail>("staff", `applications/${id}`).then(setDetail), [id]);
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  async function act(path: string, body?: unknown) {
    setBusy(true);
    setError(null);
    try {
      await api("staff", `applications/${id}/${path}`, { method: "POST", json: body ?? {} });
      setNotes("");
      await load();
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  if (!detail) return error ? <Alert tone="danger">{error}</Alert> : <Spinner />;
  const a = detail.data;
  const canDecide = hasRole(user, "ApprovingOfficer") && a.status === "PENDING_APPROVAL";
  const canCapture = hasRole(user, "ApprovingOfficer", "IssuingOfficer") && a.status === "APPROVED_FOR_BIOMETRICS";
  const canCollect = hasRole(user, "ApprovingOfficer", "IssuingOfficer") && a.status === "READY_FOR_COLLECTION";

  return (
    <div className="space-y-6">
      <PageTitle
        title={<>{a.application_number} <StatusBadge status={a.status} label={a.status_label} /></>}
        subtitle={`${a.channel === "ASSISTED" ? "Assisted" : "Online"} ${a.type === "RENEWAL" ? `renewal of card ${a.renewal_of_card_number}` : "application"} · submitted ${dateTime(a.submitted_at)}`}
        actions={
          <div className="flex gap-2">
            {canCapture && <Link href={`/staff/applications/${a.id}/biometrics`} className="rounded-lg bg-nis-green px-4 py-2.5 text-sm font-semibold text-white">Open biometrics desk</Link>}
            {a.card && <Link href={`/staff/cards/${a.card.id}`} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Card {a.card.card_number}</Link>}
          </div>
        }
      />

      {error && <Alert tone="danger">{error}</Alert>}

      {canDecide && (
        <Panel title="Decision">
          <div className="space-y-3">
            <Field label="Notes to the applicant" hint="Required when querying or rejecting. The applicant receives these by e-mail.">
              <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} />
            </Field>
            <div className="flex flex-wrap gap-2">
              <Button disabled={busy} onClick={() => act("decision", { decision: "APPROVE", notes: notes || undefined })}>Approve for biometrics</Button>
              <Button variant="gold" disabled={busy || !notes.trim()} onClick={() => act("decision", { decision: "QUERY", notes })}>Query applicant</Button>
              <Button variant="danger" disabled={busy || !notes.trim()} onClick={() => confirm("Reject this application? This cannot be undone.") && act("decision", { decision: "REJECT", notes })}>Reject</Button>
            </div>
          </div>
        </Panel>
      )}

      {canCollect && (
        <Alert tone="success">
          The card is ready. Verify the holder&apos;s original passport, then{" "}
          <button className="font-semibold underline" disabled={busy} onClick={() => act("collect")}>record collection</button>.
        </Alert>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="Particulars" className="lg:col-span-2">
          <div className="flex flex-col gap-5 sm:flex-row">
            {detail.photo_url && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={detail.photo_url} alt="Applicant" className="h-44 w-36 shrink-0 rounded-lg border object-cover" />
            )}
            <Dl
              items={[
                ["Surname", a.surname], ["Other names", a.forenames], ["Nationality", a.nationality], ["Sex", a.sex],
                ["Date of birth", nisDate(a.date_of_birth)], ["Place of birth", a.place_of_birth], ["Profession", a.profession],
                ["Passport", `${a.passport_number} (expires ${nisDate(a.passport_expiry)})`], ["Domicile", a.domicile],
                ["Phone", a.phone], ["E-mail", a.email],
                ["Emergency contact", `${a.emergency_contact_name} (${a.emergency_contact_relation}) ${a.emergency_contact_phone}`],
                ["Height / complexion", `${a.height ?? "—"} / ${a.complexion ?? "—"}`],
                ["Eyes / hair", `${a.eye_color ?? "—"} / ${a.hair_color ?? "—"}`],
              ]}
            />
          </div>
        </Panel>
        <div className="space-y-6">
          <Panel title="Appointment & payment">
            <Dl
              items={[
                ["Center", a.enrollment_center?.name], ["Date", `${nisDate(a.appointment_date)} ${a.appointment_time}`],
                ["Fee", `${naira(a.fee_amount_naira)} — ${a.payment_status}`],
                ...detail.payments.map((p) => [`Payment ${p.reference}`, `${p.status}${p.verified_at ? " (verified)" : ""}`] as [string, string]),
              ]}
            />
          </Panel>
          <Panel title="Documents"><DocumentList scope="staff" applicationId={a.id} documents={a.documents ?? []} /></Panel>
        </div>
      </div>

      <Panel title="Workflow history">{a.history && <History history={a.history} />}</Panel>
    </div>
  );
}
