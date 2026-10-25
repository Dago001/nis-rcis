"use client";

import { useEffect, useMemo, useState } from "react";
import { useI18n } from "@/components/I18nProvider";
import { api } from "@/lib/api-client";
import type { EnrollmentCenter, NisZone } from "@/lib/types";
import { Field, Input, Select } from "./ui";

type Slot = { time: string; remaining: number };

/** Official fallback zones extracted from the authoritative NIS Zones and Commands document */
const DEFAULT_ZONES: { code: string; name: string; headquarters: string }[] = [
  { code: "ZONE_A", name: "Zone A (Lagos)", headquarters: "Lagos" },
  { code: "ZONE_B", name: "Zone B (Kaduna)", headquarters: "Kaduna" },
  { code: "ZONE_C", name: "Zone C (Bauchi)", headquarters: "Bauchi" },
  { code: "ZONE_D", name: "Zone D (Minna)", headquarters: "Minna" },
  { code: "ZONE_E", name: "Zone E (Owerri)", headquarters: "Owerri" },
  { code: "ZONE_F", name: "Zone F (Ibadan)", headquarters: "Ibadan" },
  { code: "ZONE_G", name: "Zone G (Benin)", headquarters: "Benin" },
  { code: "ZONE_H", name: "Zone H (Makurdi)", headquarters: "Makurdi" },
];

/**
 * Dynamic, database-driven cascading appointment picker:
 * Select Zone -> Select Command / Enrollment Centre -> Appointment Date -> Time Slot.
 * (State / Location field removed per official specifications).
 */
export function AppointmentPicker({
  data,
  errors,
  set,
  touch,
  focus,
}: {
  data: Record<string, string>;
  errors: Record<string, string>;
  set: (k: string, v: string) => void;
  touch?: (k: string) => void;
  focus?: (k: string | null) => void;
}) {
  const { t } = useI18n();
  const [centers, setCenters] = useState<EnrollmentCenter[]>([]);
  const [zones, setZones] = useState<NisZone[]>([]);
  const [selectedZone, setSelectedZone] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [slots, setSlots] = useState<Slot[] | null>(null);
  const [weekend, setWeekend] = useState(false);

  // Load active enrollment centers and zones directory from API
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setLoadError(null);

    api<{ data: EnrollmentCenter[]; zones?: NisZone[] }>("public", "enrollment-centers")
      .then((r) => {
        if (cancelled) return;
        const fetchedCenters = r.data || [];
        setCenters(fetchedCenters);

        if (r.zones && r.zones.length > 0) {
          setZones(r.zones);
        } else {
          // Fallback: aggregate zones from centers data
          const zoneMap = new Map<string, NisZone>();
          DEFAULT_ZONES.forEach((dz) => {
            zoneMap.set(dz.code, { id: 0, code: dz.code, name: dz.name, headquarters: dz.headquarters });
          });
          fetchedCenters.forEach((c) => {
            const zCode = c.zone_code || (c.zone_id ? `ZONE_${c.zone_id}` : "");
            if (zCode && !zoneMap.has(zCode)) {
              zoneMap.set(zCode, {
                id: c.zone_id ?? 0,
                code: zCode,
                name: c.zone_name ?? zCode,
                headquarters: "",
              });
            }
          });
          setZones(Array.from(zoneMap.values()));
        }
        setLoading(false);
      })
      .catch((err) => {
        if (cancelled) return;
        setLoadError(err instanceof Error ? err.message : "Unable to load commands. Please try again.");
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  // Synchronize selected Zone from data or draft
  useEffect(() => {
    if (data.enrollment_zone && !selectedZone) {
      setSelectedZone(data.enrollment_zone);
      return;
    }
    if (!data.enrollment_center_id || centers.length === 0) return;
    const currentCenter = centers.find((c) => String(c.id) === String(data.enrollment_center_id));
    if (currentCenter) {
      const zKey = currentCenter.zone_code || (currentCenter.zone_id ? String(currentCenter.zone_id) : "");
      if (zKey && selectedZone !== zKey) {
        setSelectedZone(zKey);
      }
    }
  }, [data.enrollment_center_id, data.enrollment_zone, centers, selectedZone]);

  // Fetch slot availability for selected center and date
  useEffect(() => {
    if (!data.enrollment_center_id || !data.appointment_date) {
      setSlots(null);
      return;
    }
    let cancelled = false;
    api<{ slots: Slot[]; weekend: boolean }>(
      "public",
      `enrollment-centers/${data.enrollment_center_id}/availability?date=${data.appointment_date}`
    )
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

  // Commands filtered strictly by the selected Zone
  const availableCommands = useMemo(() => {
    if (!selectedZone) return [];

    // Filter centers returned from database
    const matched = centers.filter((c) => {
      if (c.zone_code === selectedZone) return true;
      if (String(c.zone_id) === selectedZone) return true;
      if (c.zone_name && selectedZone.includes(c.zone_name)) return true;
      const zNorm = selectedZone.replace(/[^A-Za-z0-9]/g, "").toUpperCase();
      const cNorm = (c.zone_code || "").replace(/[^A-Za-z0-9]/g, "").toUpperCase();
      return Boolean(zNorm && cNorm && zNorm === cNorm);
    });

    if (matched.length > 0) {
      return matched;
    }

    // Secondary fallback: check if zone in zones list has nested commands
    const zoneObj = zones.find((z) => z.code === selectedZone || String(z.id) === selectedZone);
    if (zoneObj && (zoneObj as any).commands?.length > 0) {
      return (zoneObj as any).commands.map((cmd: any) => ({
        id: cmd.center_id || cmd.id,
        code: cmd.code,
        name: cmd.name,
        state: cmd.state,
        address: cmd.location,
        zone_code: selectedZone,
        zone_id: zoneObj.id,
        is_biometric_enabled: cmd.is_biometric_enabled ?? true,
        is_booking_enabled: cmd.is_booking_enabled ?? true,
      }));
    }

    return [];
  }, [centers, zones, selectedZone]);

  // Handle Zone selection change
  const handleZoneChange = (zoneCode: string) => {
    setSelectedZone(zoneCode);
    set("enrollment_zone", zoneCode);

    // Clear previous command and slot selection when changing zone
    set("enrollment_center_id", "");
    set("appointment_time", "");
    set("enrollment_command", "");
    set("enrollment_state", "");
    touch?.("enrollment_center_id");
  };

  // Handle Command selection change
  const handleCommandChange = (centerId: string) => {
    set("enrollment_center_id", centerId);
    set("appointment_time", "");
    const selected = centers.find((c) => String(c.id) === centerId)
      || availableCommands.find((c) => String(c.id) === centerId);
    if (selected) {
      set("enrollment_command", (selected as any).command_name || selected.name);
      set("enrollment_state", selected.state);
      if (selected.zone_code) {
        set("enrollment_zone", selected.zone_code);
      }
    }
  };

  const displayZones = zones.length > 0 ? zones : DEFAULT_ZONES;

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {/* Field 1: Select Zone */}
      <Field label={t("Select Zone")} required error={errors.enrollment_zone}>
        <Select
          name="enrollment_zone"
          value={selectedZone}
          aria-label={t("Select Zone")}
          onFocus={() => focus?.("enrollment_zone")}
          onChange={(e) => handleZoneChange(e.target.value)}
          onBlur={() => {
            focus?.(null);
            touch?.("enrollment_zone");
          }}
          required
        >
          <option value="">{t("Select a Zone…")}</option>
          {displayZones.map((z) => (
            <option key={z.code} value={z.code}>
              {z.name}
            </option>
          ))}
        </Select>
      </Field>

      {/* Field 2: Select Command / Enrollment Centre */}
      <Field label={t("Select Command / Enrollment Centre")} required error={errors.enrollment_center_id}>
        <Select
          name="enrollment_center_id"
          value={data.enrollment_center_id ?? ""}
          aria-invalid={Boolean(errors.enrollment_center_id)}
          className={errors.enrollment_center_id ? "!border-red-500" : ""}
          onFocus={() => focus?.("enrollment_center_id")}
          onChange={(e) => handleCommandChange(e.target.value)}
          onBlur={() => {
            focus?.(null);
            touch?.("enrollment_center_id");
          }}
          required
          disabled={loading || Boolean(loadError) || !selectedZone || availableCommands.length === 0}
        >
          <option value="">
            {loading
              ? t("Loading commands...")
              : loadError
              ? t("Unable to load commands. Please try again.")
              : !selectedZone
              ? t("Select a Zone first")
              : availableCommands.length === 0
              ? t("No enrollment centres currently available for this zone")
              : t("Select a Command…")}
          </option>
          {availableCommands.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
      </Field>

      {/* Field 3: Appointment Date */}
      <Field
        label={t("Appointment date")}
        required
        error={errors.appointment_date}
        hint={t("Weekdays only, within the next 60 days")}
      >
        <Input
          type="date"
          name="appointment_date"
          min={tomorrow}
          value={data.appointment_date ?? ""}
          aria-invalid={Boolean(errors.appointment_date)}
          className={errors.appointment_date ? "!border-red-500" : ""}
          onFocus={() => focus?.("appointment_date")}
          onChange={(e) => {
            set("appointment_date", e.target.value);
            set("appointment_time", "");
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("appointment_date");
          }}
          required
          disabled={!data.enrollment_center_id}
        />
      </Field>

      {/* Field 4: Time Slot */}
      <Field label={t("Time slot")} required error={errors.appointment_time}>
        <Select
          name="appointment_time"
          value={data.appointment_time ?? ""}
          aria-invalid={Boolean(errors.appointment_time)}
          className={errors.appointment_time ? "!border-red-500" : ""}
          onFocus={() => focus?.("appointment_time")}
          onChange={(e) => {
            set("appointment_time", e.target.value);
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("appointment_time");
          }}
          required
          disabled={!slots || weekend || !data.appointment_date}
        >
          <option value="">{weekend ? t("No appointments at weekends") : t("Select a time…")}</option>
          {slots?.map((s) => (
            <option key={s.time} value={s.time} disabled={s.remaining < 1}>
              {s.time} {s.remaining < 1 ? `(${t("full")})` : `(${s.remaining} ${t("left")})`}
            </option>
          ))}
        </Select>
      </Field>
    </div>
  );
}
