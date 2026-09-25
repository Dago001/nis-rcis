"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api-client";
import type { EnrollmentCenter } from "@/lib/types";
import { Field, Input, Select } from "./ui";

type Slot = { time: string; remaining: number };

/** Enrollment center, date and capacity-aware time slot selection. */
export function AppointmentPicker({ data, errors, set }: { data: Record<string, string>; errors: Record<string, string>; set: (k: string, v: string) => void }) {
  const [centers, setCenters] = useState<EnrollmentCenter[]>([]);
  const [slots, setSlots] = useState<Slot[] | null>(null);
  const [weekend, setWeekend] = useState(false);

  useEffect(() => {
    api<{ data: EnrollmentCenter[] }>("public", "enrollment-centers").then((r) => setCenters(r.data));
  }, []);

  useEffect(() => {
    if (!data.enrollment_center_id || !data.appointment_date) return;
    let cancelled = false;
    api<{ slots: Slot[]; weekend: boolean }>("public", `enrollment-centers/${data.enrollment_center_id}/availability?date=${data.appointment_date}`)
      .then((r) => {
        if (cancelled) return;
        setSlots(r.slots);
        setWeekend(r.weekend);
      })
      .catch(() => !cancelled && setSlots([]));
    return () => {
      cancelled = true;
    };
  }, [data.enrollment_center_id, data.appointment_date]);

  const [tomorrow] = useState(() => new Date(Date.now() + 86_400_000).toISOString().slice(0, 10));
  const center = centers.find((c) => String(c.id) === data.enrollment_center_id);

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2">
        <Field label="Enrollment center" required error={errors.enrollment_center_id}>
          <Select value={data.enrollment_center_id ?? ""} onChange={(e) => set("enrollment_center_id", e.target.value)} required>
            <option value="">Select a center…</option>
            {centers.map((c) => <option key={c.id} value={c.id}>{c.name} — {c.state}</option>)}
          </Select>
        </Field>
        {center && <p className="mt-1 text-xs text-slate-500">{center.address}</p>}
      </div>
      <Field label="Appointment date" required error={errors.appointment_date} hint="Weekdays only, within the next 60 days">
        <Input type="date" min={tomorrow} value={data.appointment_date ?? ""} onChange={(e) => set("appointment_date", e.target.value)} required />
      </Field>
      <Field label="Time slot" required error={errors.appointment_time}>
        <Select value={data.appointment_time ?? ""} onChange={(e) => set("appointment_time", e.target.value)} required disabled={!slots || weekend}>
          <option value="">{weekend ? "No appointments at weekends" : "Select a time…"}</option>
          {slots?.map((s) => (
            <option key={s.time} value={s.time} disabled={s.remaining < 1}>
              {s.time} {s.remaining < 1 ? "(full)" : `(${s.remaining} left)`}
            </option>
          ))}
        </Select>
      </Field>
    </div>
  );
}
