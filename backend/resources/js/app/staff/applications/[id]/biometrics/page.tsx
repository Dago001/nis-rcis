"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { SignaturePad, WebcamCapture } from "@/components/CaptureTools";
import { FingerprintCapture, type Fingerprint } from "@/components/FingerprintCapture";
import { useI18n } from "@/components/I18nProvider";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Dl, Panel, Spinner } from "@/components/ui";
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
  const { t } = useI18n();
  const [app, setApp] = useState<Application | null>(null);
  const [photo, setPhoto] = useState<string | null>(null);
  const [signature, setSignature] = useState<string | null>(null);
  const [fingerprints, setFingerprints] = useState<Fingerprint[]>([]);
  const [confirmed, setConfirmed] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Application }>("staff", `applications/${id}`).then((r) => {
      setApp(r.data);
    });
  }, [id]);

  async function save() {
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ data: Application }>("staff", `applications/${id}/biometrics`, {
        method: "POST",
        json: {
          photo,
          signature,
          issued_at: "NIS Headquarters",
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
  if (app.status !== "APPROVED_FOR_BIOMETRICS") {
    return (
      <div className="space-y-4 max-w-xl">
        <Alert tone="warning">
          <div className="space-y-2">
            <div className="font-semibold text-base">Application Not Authorized for Biometrics</div>
            <p className="text-sm">
              This application has not received final approval from an Approving Officer (current status: {app.approval_status_label ?? app.status_label}).
              Biometrics capture requires final approval at Stage 2.
            </p>
            <div className="pt-2">
              <Link
                href="/staff/approvals"
                className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              >
                ← Return to Approvers Queue
              </Link>
            </div>
          </div>
        </Alert>
      </div>
    );
  }

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

      <div className="flex flex-wrap items-center gap-3 pt-2">
        <Button onClick={save} disabled={busy || !confirmed}>
          {busy ? "Saving…" : "Save biometrics and create card"}
        </Button>
      </div>
    </div>
  );
}
