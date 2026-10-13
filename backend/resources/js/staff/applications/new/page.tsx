"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { PageTitle } from "@/components/PageTitle";
import { ContactFields, PassportFields, PersonalFields } from "@/components/ParticularsFields";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Panel, Select } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import type { Application } from "@/lib/types";
import { APPOINTMENT_FIELDS, CONTACT_FIELDS, fieldError, PASSPORT_FIELDS, PERSONAL_FIELDS, validate } from "@/lib/validation";

const ALL_FIELDS = [...PERSONAL_FIELDS, ...PASSPORT_FIELDS, ...CONTACT_FIELDS, ...APPOINTMENT_FIELDS];

/** Walk-in application entered by staff (legacy new-card.php; SuperAdmin only). */
export default function AssistedApplicationPage() {
  const router = useRouter();
  const user = useStaff();
  const [data, setData] = useState<Record<string, string>>({ payment_status: "PAID" });
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});
  const [touched, setTouched] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => {
    setData((d) => ({ ...d, [k]: v }));
    setServerErrors((e) => (e[k] ? { ...e, [k]: "" } : e));
  };
  const touch = (k: string) => setTouched((t) => (t.has(k) ? t : new Set(t).add(k)));

  // Every visited field is checked immediately, as in the applicant wizard.
  const errors = useMemo(() => {
    const shown: Record<string, string> = {};
    for (const f of touched) {
      const message = fieldError(f, data);
      if (message) shown[f] = message;
    }
    for (const [f, message] of Object.entries(serverErrors)) if (message) shown[f] = message;
    return shown;
  }, [touched, data, serverErrors]);
  const hasErrors = Object.keys(errors).some((k) => k !== "_");

  if (!hasRole(user, "DataEntryOfficer", "Admin", "SuperAdmin")) return <Alert tone="danger">Only Data Entry Officers and Administrators can enter assisted applications.</Alert>;

  async function submit() {
    setTouched(new Set(ALL_FIELDS));
    if (Object.keys(validate(ALL_FIELDS, data)).length) {
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    setBusy(true);
    setServerErrors({});
    try {
      const r = await api<{ data: Application }>("staff", "applications", { method: "POST", json: data });
      router.push(`/staff/applications/${r.data.id}`);
    } catch (e) {
      if (e instanceof ApiError) setServerErrors({ ...e.fieldErrors(), _: e.message });
      setBusy(false);
    }
  }

  const props = { data, errors, set, touch };
  return (
    <div className="space-y-6">
      <PageTitle title="Assisted application" subtitle="Assist applicant to fill application, upload documents, and process online payment." />
      {hasErrors && <Alert tone="danger">Please correct the highlighted details.</Alert>}
      {serverErrors._ && !hasErrors && <Alert tone="danger">{serverErrors._}</Alert>}
      <Panel title="Personal details"><PersonalFields {...props} /></Panel>
      <Panel title="Passport"><PassportFields {...props} /></Panel>
      <Panel title="Residence & contacts"><ContactFields {...props} /></Panel>
      <Panel title="Biometrics appointment"><AppointmentPicker {...props} /></Panel>
      <Panel title="Fee & Online Payment Policy">
        <Alert tone="warning">
          <strong>Official NIS Policy:</strong> No cash or card payment is allowed at the counter. All application fees must be paid online via the secure payment portal, and all statutory documents must be uploaded.
        </Alert>
        <div className="mt-3">
          <Field label="Payment status" error={errors.payment_status}>
            <Select value={data.payment_status} onChange={(e) => set("payment_status", e.target.value)}>
              <option value="PENDING">Pending (Online payment link generated)</option>
              <option value="PAID">Paid online (Reference confirmed)</option>
            </Select>
          </Field>
        </div>
      </Panel>
      <p className="text-sm text-slate-600">All required documents must be uploaded on the application page immediately after creation.</p>
      <Button onClick={submit} disabled={busy}>{busy ? "Submitting…" : "Create application"}</Button>
    </div>
  );
}
