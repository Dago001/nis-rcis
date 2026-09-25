"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { AppointmentPicker } from "@/components/AppointmentPicker";
import { PageTitle } from "@/components/PageTitle";
import { ContactFields, PassportFields, PersonalFields } from "@/components/ParticularsFields";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Panel, Select } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import type { Application } from "@/lib/types";

/** Walk-in application entered by staff (legacy new-card.php; SuperAdmin only). */
export default function AssistedApplicationPage() {
  const router = useRouter();
  const user = useStaff();
  const [data, setData] = useState<Record<string, string>>({ payment_status: "PAID" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => setData((d) => ({ ...d, [k]: v }));

  if (!hasRole(user, "SuperAdmin")) return <Alert tone="danger">Only a Super Administrator can enter assisted applications.</Alert>;

  async function submit() {
    setBusy(true);
    setErrors({});
    try {
      const r = await api<{ data: Application }>("staff", "applications", { method: "POST", json: data });
      router.push(`/staff/applications/${r.data.id}`);
    } catch (e) {
      if (e instanceof ApiError) setErrors({ ...e.fieldErrors(), _: e.message });
      setBusy(false);
    }
  }

  const props = { data, errors, set };
  return (
    <div className="space-y-6">
      <PageTitle title="Assisted application" subtitle="For walk-in applicants. The application enters the same approval queue as online applications." />
      {errors._ && <Alert tone="danger">{errors._}</Alert>}
      <Panel title="Personal details"><PersonalFields {...props} /></Panel>
      <Panel title="Passport"><PassportFields {...props} /></Panel>
      <Panel title="Residence & contacts"><ContactFields {...props} /></Panel>
      <Panel title="Biometrics appointment"><AppointmentPicker {...props} /></Panel>
      <Panel title="Fee">
        <Field label="Payment status" error={errors.payment_status}>
          <Select value={data.payment_status} onChange={(e) => set("payment_status", e.target.value)}>
            <option value="PAID">Paid at the counter (receipt sighted)</option>
            <option value="PENDING">Pending</option>
          </Select>
        </Field>
      </Panel>
      <p className="text-sm text-slate-600">Supporting documents can be uploaded on the application page after it is created.</p>
      <Button onClick={submit} disabled={busy}>{busy ? "Submitting…" : "Create application"}</Button>
    </div>
  );
}
