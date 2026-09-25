"use client";

import { NATIONALITIES } from "@/lib/nationalities";
import { Field, Input, Select, Textarea } from "./ui";

export type FormData = Record<string, string>;
type Props = { data: FormData; errors: Record<string, string>; set: (key: string, value: string) => void };

function text(props: Props, name: string, label: string, opts: { required?: boolean; type?: string; hint?: string; upper?: boolean } = {}) {
  return (
    <Field label={label} required={opts.required} error={props.errors[name]} hint={opts.hint}>
      <Input
        name={name}
        type={opts.type ?? "text"}
        value={props.data[name] ?? ""}
        onChange={(e) => props.set(name, opts.upper ? e.target.value.toUpperCase() : e.target.value)}
        required={opts.required}
      />
    </Field>
  );
}

/** Wizard step 1 — personal particulars (legacy residence card booklet fields). */
export function PersonalFields(props: Props) {
  const { data, errors, set } = props;
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {text(props, "surname", "Surname", { required: true, upper: true })}
      {text(props, "forenames", "Other names", { required: true, upper: true })}
      <Field label="Nationality" required error={errors.nationality}>
        <Select value={data.nationality ?? ""} onChange={(e) => set("nationality", e.target.value)} required>
          <option value="">Select nationality…</option>
          {NATIONALITIES.filter((n) => n !== "NIGERIA").map((n) => <option key={n} value={n}>{n}</option>)}
        </Select>
      </Field>
      <Field label="Sex" required error={errors.sex}>
        <Select value={data.sex ?? ""} onChange={(e) => set("sex", e.target.value)} required>
          <option value="">Select…</option>
          <option value="MALE">Male</option>
          <option value="FEMALE">Female</option>
        </Select>
      </Field>
      {text(props, "date_of_birth", "Date of birth", { required: true, type: "date" })}
      {text(props, "place_of_birth", "Place of birth", { required: true, upper: true })}
      {text(props, "profession", "Profession / occupation", { required: true, upper: true })}
      <Field label="Blood group" error={errors.blood_group}>
        <Select value={data.blood_group ?? "UNKNOWN"} onChange={(e) => set("blood_group", e.target.value)}>
          {["UNKNOWN", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"].map((g) => <option key={g}>{g}</option>)}
        </Select>
      </Field>
      {text(props, "height", "Height", { hint: "e.g. 1.75m" })}
      {text(props, "complexion", "Complexion")}
      {text(props, "eye_color", "Colour of eyes")}
      {text(props, "hair_color", "Colour of hair")}
      <div className="sm:col-span-2">{text(props, "distinguished_features", "Distinguishing features", { hint: "Leave blank for NONE", upper: true })}</div>
    </div>
  );
}

/** Wizard step 2 — passport and identity numbers. */
export function PassportFields(props: Props) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {text(props, "passport_number", "Passport number", { required: true, upper: true, hint: "6–15 letters and digits, no spaces" })}
      {text(props, "passport_issue_date", "Passport issue date", { type: "date" })}
      {text(props, "passport_expiry", "Passport expiry date", { required: true, type: "date", hint: "Must be valid for at least 6 more months" })}
      {text(props, "national_id_number", "NIN (if any)")}
      {text(props, "tax_id_number", "Tax identification number (if any)")}
    </div>
  );
}

/** Wizard step 3 — residence and contacts. */
export function ContactFields(props: Props) {
  const { data, errors, set } = props;
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2">
        <Field label="Residential address in Nigeria (domicile)" required error={errors.domicile}>
          <Textarea value={data.domicile ?? ""} onChange={(e) => set("domicile", e.target.value)} required />
        </Field>
      </div>
      <div className="sm:col-span-2">
        <Field label="Change of address (if any)" error={errors.change_of_address}>
          <Textarea value={data.change_of_address ?? ""} onChange={(e) => set("change_of_address", e.target.value)} rows={2} />
        </Field>
      </div>
      {text(props, "phone", "Phone number", { required: true, type: "tel", hint: "Include the country code" })}
      {text(props, "email", "E-mail address", { required: true, type: "email" })}
      <h3 className="pt-2 text-sm font-semibold text-slate-800 sm:col-span-2">Emergency contact in Nigeria</h3>
      {text(props, "emergency_contact_name", "Full name", { required: true, upper: true })}
      {text(props, "emergency_contact_relation", "Relationship", { required: true, upper: true })}
      {text(props, "emergency_contact_phone", "Phone number", { required: true, type: "tel" })}
      <div className="sm:col-span-2">
        <Field label="Address" required error={errors.emergency_contact_address}>
          <Textarea value={data.emergency_contact_address ?? ""} onChange={(e) => set("emergency_contact_address", e.target.value)} rows={2} required />
        </Field>
      </div>
    </div>
  );
}

export const STEP_FIELDS: Record<number, string[]> = {
  1: ["surname", "forenames", "nationality", "sex", "date_of_birth", "place_of_birth", "profession"],
  2: ["passport_number", "passport_expiry"],
  3: ["domicile", "phone", "email", "emergency_contact_name", "emergency_contact_relation", "emergency_contact_phone", "emergency_contact_address"],
};
