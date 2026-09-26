"use client";

import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { SignaturePad, WebcamCapture } from "@/components/CaptureTools";
import { FingerprintCapture, type Fingerprint } from "@/components/FingerprintCapture";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Dl, Field, Input, Panel, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { nisDate } from "@/lib/format";
import type { Application } from "@/lib/types";

/**
 * Biometrics capturing desk (legacy biometrics-capture.php): live photo,
 * signature and fingerprints. Saving creates the residence card (awaiting final approval).
 */
export default function BiometricsDesk() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const user = useStaff();
  const [app, setApp] = useState<Application | null>(null);
  const [photo, setPhoto] = useState<string | null>(null);
  const [signature, setSignature] = useState<string | null>(null);
  const [fingerprints, setFingerprints] = useState<Fingerprint[]>([]);
  const [issuedAt, setIssuedAt] = useState("");
  const [confirmed, setConfirmed] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Application }>("staff", `applications/${id}`).then((r) => {
      setApp(r.data);
      setIssuedAt(r.data.enrollment_center?.name ?? user.command);
    });
  }, [id, user.command]);

  async function save() {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ data: Application }>("staff", `applications/${id}/biometrics`, {
        method: "POST",
        json: {
          photo, signature, issued_at: issuedAt,
          // Images stay on the desk; only the templates are sent.
          fingerprints: fingerprints.length ? fingerprints.map((f) => ({ finger: f.finger, template: f.template, format: f.format, quality: f.quality, nfiq: f.nfiq, device: f.device })) : undefined,
        },
      });
      router.push(r.data.card ? `/staff/cards/${r.data.card.id}` : `/staff/applications/${id}`);
    } catch (e) {
      setError(e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e));
      setBusy(false);
    }
  }

  if (!app) return <Spinner />;
  if (app.status !== "APPROVED_FOR_BIOMETRICS") return <Alert tone="warning">This application is {app.status_label.toLowerCase()} and is not awaiting biometrics.</Alert>;

  return (
    <div className="space-y-6">
      <PageTitle title="Biometrics capturing desk" subtitle={`${app.application_number} · ${app.surname}, ${app.forenames}`} />
      {error && <Alert tone="danger">{error}</Alert>}

      <Panel title="1. Confirm identity">
        <div className="space-y-4">
          <Dl items={[["Passport", app.passport_number], ["Nationality", app.nationality], ["Date of birth", nisDate(app.date_of_birth)], ["Appointment", `${nisDate(app.appointment_date)} ${app.appointment_time}`]]} />
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} />
            I have examined the holder&apos;s ORIGINAL passport and it matches this application.
          </label>
        </div>
      </Panel>

      <Panel title="2. Live facial photograph"><WebcamCapture onCapture={setPhoto} /></Panel>
      <Panel title="3. Holder's signature"><SignaturePad onChange={setSignature} /></Panel>
      <Panel title="4. Fingerprints"><FingerprintCapture onChange={setFingerprints} /></Panel>

      <Panel title="5. Issue">
        <div className="space-y-4">
          <Field label="Place of issue"><Input value={issuedAt} onChange={(e) => setIssuedAt(e.target.value)} /></Field>
          <Button onClick={save} disabled={busy || !confirmed || !photo || !signature}>{busy ? "Saving…" : "Save biometrics and create card"}</Button>
        </div>
      </Panel>
    </div>
  );
}
