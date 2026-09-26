"use client";

import { useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { hasRole, useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner, Textarea } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";

type Breach = {
  id: number; title: string; description: string; severity: string; status: string; data_categories: string | null;
  affected_count: number | null; containment_actions: string | null; occurred_at: string | null; detected_at: string;
  regulator_notified_at: string | null; subjects_notified_at: string | null; regulator_deadline: string; regulator_overdue: boolean; reported_by: string | null;
};


/** NDPA 2023 personal-data breach register. */
export default function DataBreachesPage() {
  const user = useStaff();
  const canEdit = hasRole(user, "SuperAdmin");
  const { data, reload } = useFetch<{ data: Breach[] }>("staff", "data-breaches");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = Object.fromEntries([...new FormData(form)].filter(([, v]) => v !== ""));
    setErrors({});
    try {
      await api("staff", "data-breaches", { method: "POST", json: body });
      form.reset();
      setNotice("Breach recorded. Notify the Nigeria Data Protection Commission within 72 hours if it is notifiable.");
      reload();
    } catch (e) {
      if (e instanceof ApiError) setErrors({ ...e.fieldErrors(), _: e.message });
    }
  }

  async function patch(b: Breach, body: Record<string, string>) {
    await api("staff", `data-breaches/${b.id}`, { method: "PATCH", json: body });
    reload();
  }

  return (
    <div className="space-y-6">
      <PageTitle title="Data breach register" subtitle="Nigeria Data Protection Act 2023: record every personal-data breach; notify the NDPC within 72 hours of detection when required." />
      {notice && <Alert tone="success">{notice}</Alert>}
      {canEdit && (
        <Panel title="Record a breach">
          <form onSubmit={create} className="grid gap-3 sm:grid-cols-2">
            <Field label="Title" required error={errors.title}><Input name="title" required maxLength={200} /></Field>
            <Field label="Severity" required error={errors.severity}>
              <Select name="severity" defaultValue="MEDIUM">{["LOW", "MEDIUM", "HIGH", "CRITICAL"].map((s) => <option key={s}>{s}</option>)}</Select>
            </Field>
            <Field label="Detected at" required error={errors.detected_at}><Input name="detected_at" type="datetime-local" required /></Field>
            <Field label="Occurred at (if known)" error={errors.occurred_at}><Input name="occurred_at" type="datetime-local" /></Field>
            <Field label="Data affected" hint="e.g. names, passport numbers, photos" error={errors.data_categories}><Input name="data_categories" maxLength={300} /></Field>
            <Field label="People affected (approx.)" error={errors.affected_count}><Input name="affected_count" type="number" min={0} /></Field>
            <div className="sm:col-span-2"><Field label="What happened" required error={errors.description}><Textarea name="description" required rows={3} /></Field></div>
            <div className="sm:col-span-2"><Field label="Containment actions taken" error={errors.containment_actions}><Textarea name="containment_actions" rows={2} /></Field></div>
            <div className="sm:col-span-2"><Button type="submit">Record breach</Button></div>
          </form>
        </Panel>
      )}
      {!data ? <Spinner /> : data.data.length === 0 ? <p className="text-sm text-slate-600">No breaches recorded.</p> : (
        <ul className="space-y-3">
          {data.data.map((b) => (
            <li key={b.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <div className="font-medium text-slate-900">{b.title}</div>
                  <div className="text-xs text-slate-500">Detected {dateTime(b.detected_at)} · {b.severity} · {b.status}{b.reported_by ? ` · recorded by ${b.reported_by}` : ""}</div>
                </div>
                {b.regulator_overdue ? <Alert tone="danger">NDPC notification overdue (deadline {dateTime(b.regulator_deadline)})</Alert>
                  : !b.regulator_notified_at ? <span className="text-xs text-nis-orange">NDPC deadline: {dateTime(b.regulator_deadline)}</span>
                  : <span className="text-xs text-nis-primary">NDPC notified {dateTime(b.regulator_notified_at)}</span>}
              </div>
              <p className="mt-2 whitespace-pre-line text-sm text-slate-700">{b.description}</p>
              {b.containment_actions && <p className="mt-1 whitespace-pre-line text-sm text-slate-600"><strong>Containment:</strong> {b.containment_actions}</p>}
              <p className="mt-1 text-xs text-slate-500">Data: {b.data_categories ?? "—"} · People affected: {b.affected_count ?? "—"} · Data subjects notified: {b.subjects_notified_at ? dateTime(b.subjects_notified_at) : "no"}</p>
              {canEdit && (
                <div className="mt-3 flex flex-wrap gap-2">
                  {!b.regulator_notified_at && <Button variant="secondary" onClick={() => patch(b, { regulator_notified_at: new Date().toISOString() })}>Mark NDPC notified</Button>}
                  {!b.subjects_notified_at && <Button variant="secondary" onClick={() => patch(b, { subjects_notified_at: new Date().toISOString() })}>Mark people notified</Button>}
                  {b.status !== "CONTAINED" && b.status !== "CLOSED" && <Button variant="secondary" onClick={() => patch(b, { status: "CONTAINED" })}>Mark contained</Button>}
                  {b.status !== "CLOSED" && <Button variant="secondary" onClick={() => patch(b, { status: "CLOSED" })}>Close</Button>}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
