"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";
import { DocumentList, History } from "@/components/DocumentList";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Tracker } from "@/components/Tracker";
import { Alert, Button, Dl, Field, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError, upload } from "@/lib/api-client";
import { naira, nisDate } from "@/lib/format";
import type { Application } from "@/lib/types";

const UPLOADABLE = [
  ["photo", "Passport photograph"],
  ["passport_copy", "Passport data page"],
  ["residence_visa", "Residence visa"],
  ["quota_approval", "Expatriate quota approval"],
  ["domicile_proof", "Proof of domicile"],
  ["additional", "Additional document"],
];

function ApplicationDetail() {
  const { id } = useParams<{ id: string }>();
  const submitted = useSearchParams().get("submitted") === "1";
  const [app, setApp] = useState<Application | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [docType, setDocType] = useState("residence_visa");
  const [response, setResponse] = useState("");

  const load = useCallback(() => api<{ data: Application }>("applicant", `applications/${id}`).then((r) => setApp(r.data)), [id]);
  useEffect(() => {
    load().catch((e) => setError(e.message));
  }, [load]);

  async function reupload(file: File) {
    setBusy(true);
    setError(null);
    try {
      const r = await upload<{ data: Application }>("applicant", `applications/${id}/documents`, { type: docType, file });
      setApp(r.data);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
    } finally {
      setBusy(false);
    }
  }

  async function respond() {
    setBusy(true);
    try {
      const r = await api<{ data: Application }>("applicant", `applications/${id}/respond`, { method: "POST", json: { response } });
      setApp(r.data);
      setResponse("");
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (error && !app) return <Alert tone="danger">{error}</Alert>;
  if (!app) return <Spinner />;

  const appointmentReady = ["APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "READY_FOR_COLLECTION", "ISSUED"].includes(app.status);

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow="Applicant console"
        title={<>Application {app.application_number}</>}
        subtitle={<>Reference {app.reference_number} · {app.type === "RENEWAL" ? `Renewal of card ${app.renewal_of_card_number ?? ""}` : "New residence card"}</>}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href={`/portal/applications/${app.id}/slip`} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold">Application slip</Link>
            {appointmentReady && (
              <Link href={`/portal/applications/${app.id}/slip?kind=appointment`} className="rounded-lg bg-nis-green px-3 py-2 text-sm font-semibold text-white">Appointment slip</Link>
            )}
          </div>
        }
      />

      {submitted && <Alert tone="success">Your application has been submitted. We have e-mailed you a confirmation.</Alert>}

      <Panel title={<>Status <StatusBadge status={app.status} label={app.status_label} /></>}>
        <div className="space-y-5">
          <Tracker step={app.tracker_step} status={app.status} />
          {app.status === "APPROVED_FOR_BIOMETRICS" && (
            <Alert tone="info">
              Attend biometrics capture on <strong>{nisDate(app.appointment_date)}</strong> at <strong>{app.appointment_time}</strong>,{" "}
              {app.enrollment_center?.name}. Bring your original passport and your printed appointment slip.
            </Alert>
          )}
          {app.status === "READY_FOR_COLLECTION" && (
            <Alert tone="success">Your residence card is ready for collection at {app.enrollment_center?.name}.</Alert>
          )}
          {app.status === "REJECTED" && <Alert tone="danger">Reason: {app.decision_notes}</Alert>}
        </div>
      </Panel>

      {app.status === "QUERIED" && (
        <Panel title="Action required: respond to query">
          <div className="space-y-4">
            <Alert tone="warning"><strong>Officer&apos;s query:</strong> {app.decision_notes}</Alert>
            {error && <Alert tone="danger">{error}</Alert>}
            <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
              <Field label="Upload a corrected document">
                <Select value={docType} onChange={(e) => setDocType(e.target.value)}>
                  {UPLOADABLE.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </Select>
              </Field>
              <label className="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-slate-50">
                Choose file
                <input type="file" className="sr-only" accept="image/jpeg,image/png,application/pdf" disabled={busy} onChange={(e) => e.target.files?.[0] && reupload(e.target.files[0])} />
              </label>
            </div>
            <Field label="Your response to the officer" required>
              <Textarea value={response} onChange={(e) => setResponse(e.target.value)} />
            </Field>
            <Button onClick={respond} disabled={busy || response.trim().length < 3}>Send response and resubmit</Button>
          </div>
        </Panel>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Panel title="Particulars">
          <Dl
            items={[
              ["Name", `${app.surname}, ${app.forenames}`],
              ["Nationality", app.nationality],
              ["Date of birth", nisDate(app.date_of_birth)],
              ["Passport", app.passport_number],
              ["Address", app.domicile],
              ["Fee", `${naira(app.fee_amount_naira)} (${app.payment_status})`],
            ]}
          />
        </Panel>
        <Panel title="Documents">
          <DocumentList scope="applicant" applicationId={app.id} documents={app.documents ?? []} />
        </Panel>
      </div>

      <Panel title="History">{app.history && <History history={app.history} />}</Panel>
    </div>
  );
}

export default function Page() {
  return (
    <Suspense fallback={<Spinner />}>
      <ApplicationDetail />
    </Suspense>
  );
}
