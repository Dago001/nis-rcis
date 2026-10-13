"use client";

import { ECOWAS_NATIONALITIES, NATIONALITIES } from "@/lib/nationalities";
import { NIGERIA_LGAS, NIGERIA_STATES } from "@/lib/nigeria-lgas";
import { RELATIONSHIPS } from "@/lib/validation";
import { lettersOnly, PhoneInput } from "./inputs";
import { Field, Input, Select, Textarea } from "./ui";

export type FormData = Record<string, string>;
type Props = {
  data: FormData;
  errors: Record<string, string>;
  set: (key: string, value: string) => void;
  /** Marks a field as visited so its error shows straight away. */
  touch?: (key: string) => void;
};

type TextOptions = { required?: boolean; type?: string; hint?: string; upper?: boolean; letters?: boolean; digits?: boolean; maxLength?: number; max?: string; min?: string };

function text(props: Props, name: string, label: string, opts: TextOptions = {}) {
  return (
    <Field label={label} required={opts.required} error={props.errors[name]} hint={opts.hint}>
      <Input
        name={name}
        type={opts.type ?? "text"}
        inputMode={opts.digits ? "numeric" : undefined}
        value={props.data[name] ?? ""}
        maxLength={opts.maxLength ?? 150}
        max={opts.max}
        min={opts.min}
        aria-invalid={Boolean(props.errors[name])}
        className={props.errors[name] ? "!border-red-500" : ""}
        onChange={(e) => {
          let value = e.target.value;
          if (opts.letters) value = lettersOnly(value);
          if (opts.digits) value = value.replace(/\D/g, "");
          props.set(name, opts.upper ? value.toUpperCase() : value);
        }}
        onBlur={() => props.touch?.(name)}
        required={opts.required}
      />
    </Field>
  );
}

function select(props: Props, name: string, label: string, options: string[], opts: { required?: boolean; placeholder?: string; disabled?: boolean } = {}) {
  return (
    <Field label={label} required={opts.required} error={props.errors[name]}>
      <Select
        value={props.data[name] ?? ""}
        disabled={opts.disabled}
        aria-invalid={Boolean(props.errors[name])}
        className={props.errors[name] ? "!border-red-500" : ""}
        onChange={(e) => {
          props.set(name, e.target.value);
          props.touch?.(name);
        }}
        onBlur={() => props.touch?.(name)}
      >
        <option value="">{opts.placeholder ?? "Select…"}</option>
        {options.map((o) => <option key={o} value={o}>{o}</option>)}
      </Select>
    </Field>
  );
}

const OTHER = "__OTHER__";

/** Wizard step 1 of 8 — General Information (Nationality, Category, Document Type, Prescribed Fee). */
export function GeneralInfoFields(props: Props & { feeNaira?: number | null }) {
  const { data, errors, set, touch, feeNaira } = props;
  const docType = data.travel_document_type || "PASSPORT";
  const feeDisplay = (feeNaira ?? 35000).toLocaleString("en-NG");

  return (
    <div className="space-y-6">
      <div className="grid gap-5 sm:grid-cols-2">
        {/* Nationality - Complete Dropdown of Countries */}
        <Field
          label="Nationality"
          required
          error={errors.nationality}
          hint="Select your country of origin"
        >
          <Select
            value={data.nationality ?? ""}
            className={errors.nationality ? "!border-red-500" : ""}
            onChange={(e) => {
              set("nationality", e.target.value);
              touch?.("nationality");
            }}
          >
            <option value="">Select country…</option>
            <optgroup label="ECOWAS Member States">
              {ECOWAS_NATIONALITIES.map((n) => (
                <option key={n.value} value={n.value}>
                  {n.label}
                </option>
              ))}
            </optgroup>
            <optgroup label="All Countries">
              {NATIONALITIES.filter((c) => !ECOWAS_NATIONALITIES.some((e) => e.value.toUpperCase() === c.toUpperCase())).map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </optgroup>
          </Select>
        </Field>

        {/* Category / Class: Student, Work, Business */}
        <Field
          label="Category / Class"
          required
          error={errors.category}
          hint="Determines documents to provide"
        >
          <Select
            value={data.category ?? "WORK"}
            className={errors.category ? "!border-red-500" : ""}
            onChange={(e) => {
              set("category", e.target.value);
              touch?.("category");
            }}
          >
            <option value="WORK">Work (Employment)</option>
            <option value="BUSINESS">Business (Trade / Enterprise)</option>
            <option value="STUDENT">Student (Academic)</option>
          </Select>
        </Field>

        {/* Document Type: Passport, ECOWAS Travel Certificate */}
        <div className="sm:col-span-2">
          <Field
            label="Passport/Other Documents"
            required
            error={errors.travel_document_type}
            hint="Choose Passport or ECOWAS Travel Certificate only"
          >
            <Select
              value={docType}
              className={errors.travel_document_type ? "!border-red-500" : ""}
              onChange={(e) => {
                set("travel_document_type", e.target.value);
                touch?.("travel_document_type");
              }}
            >
              <option value="PASSPORT">Passport</option>
              <option value="ECOWAS_TC">ECOWAS Travel Certificate</option>
            </Select>
          </Field>
        </div>
      </div>

      {/* Dynamic Document Notice depending on Category */}
      <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-slate-800">
        <div className="font-semibold text-blue-900 mb-1">
          {data.category === "STUDENT"
            ? "Student Category: Admission Letter is Required"
            : data.category === "BUSINESS"
            ? "Business Category: CAC Registration Letter is Required"
            : "Work Category: Appointment Letter is Required"}
        </div>
        <p className="text-xs text-blue-800">
          {data.category === "STUDENT"
            ? "You will upload your valid Institution Admission Letter in Step 5 (Documents)."
            : data.category === "BUSINESS"
            ? "You will upload your CAC Business Registration Letter / Certificate in Step 5 (Documents)."
            : "You will upload your official Employment Appointment Letter in Step 5 (Documents)."}
        </p>
      </div>

      {/* Application Fee Notice Before Moving to Next Step */}
      <div className="rounded-xl border border-nis-primary/30 bg-gradient-to-r from-emerald-50 via-teal-50 to-white p-4 shadow-sm">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-nis-primary">Application Fee</span>
          <div className="mt-0.5 text-lg font-bold text-slate-900 sm:text-xl">₦{feeDisplay}</div>
        </div>
      </div>
    </div>
  );
}

/** Wizard step 2 — personal particulars. */
export function PersonalFields(props: Props & { lockContact?: boolean }) {
  const { data, errors, set, touch, lockContact } = props;
  const maxBirthDate = new Date(new Date().setFullYear(new Date().getFullYear() - 18)).toISOString().slice(0, 10);
  const isDependant = Boolean(data.dependant_relationship || data.principal_application_id);

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {isDependant ? (
        <Field label="Surname" required error={errors.surname} hint="Inherited from primary applicant (cannot be changed)">
          <Input
            name="surname"
            value={data.surname ?? ""}
            readOnly
            disabled
            className="bg-slate-100 font-semibold text-slate-700 cursor-not-allowed"
          />
        </Field>
      ) : (
        text(props, "surname", "Surname", { required: true, upper: true, letters: true, maxLength: 100 })
      )}
      {text(props, "forenames", "Other names", { required: true, upper: true, letters: true })}
      <Field label="Nationality" hint="Selected in Step 1 (General Information)">
        <Input
          name="nationality"
          value={data.nationality ?? ""}
          readOnly
          disabled
          className="bg-slate-100 font-semibold text-slate-700 cursor-not-allowed"
        />
      </Field>
      {select(props, "sex", "Sex", ["MALE", "FEMALE"], { required: true })}
      {text(props, "date_of_birth", "Date of birth", { required: true, type: "date", max: maxBirthDate, min: "1900-01-01" })}
      {text(props, "place_of_birth", "Place of birth", { required: true, upper: true })}
      {text(props, "profession", "Profession / occupation", { required: true, upper: true })}
      <Field label="Blood group" error={errors.blood_group}>
        <Select value={data.blood_group ?? "UNKNOWN"} onChange={(e) => set("blood_group", e.target.value)}>
          {["UNKNOWN", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"].map((g) => <option key={g}>{g}</option>)}
        </Select>
      </Field>
      {text(props, "height", "Height", { hint: "e.g. 1.75m or 175cm", maxLength: 10 })}
      {text(props, "complexion", "Complexion", { letters: true, upper: true, maxLength: 50 })}
      {text(props, "eye_color", "Colour of eyes", { letters: true, upper: true, maxLength: 50 })}
      {text(props, "hair_color", "Colour of hair", { letters: true, upper: true, maxLength: 50 })}
      <div className="sm:col-span-2">{text(props, "distinguished_features", "Distinguishing features", { hint: "Leave blank for NONE", upper: true })}</div>

      <h3 className="pt-2 text-sm font-semibold text-nis-primary-dark sm:col-span-2">Your contact details</h3>
      {lockContact ? (
        <>
          <Field label="Phone number" hint="From your account registration; it cannot be changed here.">
            <Input value={data.phone ?? ""} readOnly disabled className="bg-slate-100" />
          </Field>
          <Field label="E-mail address" hint="From your account registration; it cannot be changed here.">
            <Input value={data.email ?? ""} readOnly disabled className="bg-slate-100" />
          </Field>
        </>
      ) : (
        <>
          <div onBlur={() => touch?.("phone")}>
            <Field label="Phone number" required error={errors.phone}>
              <PhoneInput value={data.phone ?? ""} onChange={(v) => set("phone", v)} />
            </Field>
          </div>
          {text(props, "email", "E-mail address", { required: true, type: "email", maxLength: 255 })}
        </>
      )}
    </div>
  );
}

/** Wizard step 3 — Passport or ECOWAS Travel Certificate only (National ID removed). */
export function PassportFields(props: Props) {
  const { data, errors, set, touch } = props;
  const today = new Date().toISOString().slice(0, 10);
  const docType = data.travel_document_type === "ECOWAS_TC" ? "ECOWAS_TC" : "PASSPORT";

  const numLabel = docType === "ECOWAS_TC"
    ? "ECOWAS Travel Certificate number"
    : "Passport number";

  const numHint = docType === "ECOWAS_TC"
    ? "As printed on your ECOWAS Travel Certificate"
    : "6–15 letters and digits, no spaces";

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2">
        <Field label="Passport/Other Documents" required hint="Passport or ECOWAS Travel Certificate only">
          <Select
            value={docType}
            onChange={(e) => {
              set("travel_document_type", e.target.value);
              touch?.("travel_document_type");
            }}
          >
            <option value="PASSPORT">Passport</option>
            <option value="ECOWAS_TC">ECOWAS Travel Certificate</option>
          </Select>
        </Field>
      </div>

      <Field label={numLabel} required error={errors.passport_number} hint={numHint}>
        <Input
          value={data.passport_number ?? ""}
          maxLength={20}
          className={errors.passport_number ? "!border-red-500" : ""}
          onChange={(e) => set("passport_number", e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ""))}
          onBlur={() => touch?.("passport_number")}
        />
      </Field>

      {text(props, "passport_issue_date", "Document issue date", { type: "date", max: today })}

      {text(props, "passport_expiry", "Document expiry date", { required: true, type: "date", min: today, hint: "Must be valid for at least 6 more months" })}

      {text(props, "national_id_number", "National Identification Number (NIN, if any)", { digits: true, maxLength: 11, hint: "11 digits (Nigerian NIN)" })}
      {text(props, "tax_id_number", "Tax identification number (if any)", { maxLength: 20 })}
    </div>
  );
}

/** State, then its local government areas, then the street address. */
function AddressFields(props: Props & { prefix: "domicile" | "emergency_contact"; streetField: string; streetLabel: string }) {
  const { data, set, touch, prefix, streetField, streetLabel, errors } = props;
  const stateField = `${prefix}_state`;
  const lgaField = `${prefix}_lga`;
  const lgas = NIGERIA_LGAS[data[stateField] ?? ""] ?? [];
  return (
    <>
      <Field label="State" required error={errors[stateField]}>
        <Select
          value={data[stateField] ?? ""}
          className={errors[stateField] ? "!border-red-500" : ""}
          onChange={(e) => {
            set(stateField, e.target.value);
            set(lgaField, "");
            touch?.(stateField);
          }}
        >
          <option value="">Select state…</option>
          {NIGERIA_STATES.map((s) => <option key={s} value={s}>{s}</option>)}
        </Select>
      </Field>
      {select(props, lgaField, "Local government area", lgas, { required: true, disabled: !lgas.length, placeholder: lgas.length ? "Select LGA…" : "Select a state first" })}
      <div className="sm:col-span-2">
        <Field label={streetLabel} required error={errors[streetField]} hint="House number, street and area / town">
          <Textarea
            value={data[streetField] ?? ""}
            maxLength={300}
            rows={2}
            className={errors[streetField] ? "!border-red-500" : ""}
            onChange={(e) => set(streetField, e.target.value)}
            onBlur={() => touch?.(streetField)}
          />
        </Field>
      </div>
    </>
  );
}

/** Wizard step 3 — residence and contacts. */
export function ContactFields(props: Props) {
  const { data, errors, set, touch } = props;
  const listedRelation = RELATIONSHIPS.includes(data.emergency_contact_relation ?? "");
  const otherRelation = data.emergency_contact_relation_other === "1" || Boolean(data.emergency_contact_relation && !listedRelation);

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">Residential address in Home Country</h3>
      <div className="sm:col-span-2">
        <Field label="Address in country of origin" required error={errors.home_country_address} hint="Permanent residential address in your home country">
          <Textarea
            value={data.home_country_address ?? ""}
            maxLength={300}
            rows={2}
            className={errors.home_country_address ? "!border-red-500" : ""}
            onChange={(e) => set("home_country_address", e.target.value)}
            onBlur={() => touch?.("home_country_address")}
          />
        </Field>
      </div>

      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">Residential address in Nigeria (domicile)</h3>
      <AddressFields {...props} prefix="domicile" streetField="domicile" streetLabel="Street address" />
      <div className="sm:col-span-2">
        <Field label="Change of address (if any)" error={errors.change_of_address}>
          <Textarea value={data.change_of_address ?? ""} maxLength={300} onChange={(e) => set("change_of_address", e.target.value)} onBlur={() => touch?.("change_of_address")} rows={2} />
        </Field>
      </div>

      <h3 className="pt-2 text-sm font-semibold text-nis-primary-dark sm:col-span-2">Emergency contact in Nigeria</h3>
      {text(props, "emergency_contact_name", "Full name", { required: true, upper: true, letters: true })}
      <Field label="Relationship" required error={otherRelation ? undefined : errors.emergency_contact_relation}>
        <Select
          value={otherRelation ? OTHER : listedRelation ? data.emergency_contact_relation : ""}
          className={!otherRelation && errors.emergency_contact_relation ? "!border-red-500" : ""}
          onChange={(e) => {
            if (e.target.value === OTHER) {
              set("emergency_contact_relation_other", "1");
              set("emergency_contact_relation", "");
            } else {
              set("emergency_contact_relation_other", "");
              set("emergency_contact_relation", e.target.value);
              touch?.("emergency_contact_relation");
            }
          }}
        >
          <option value="">Select relationship…</option>
          {RELATIONSHIPS.map((r) => <option key={r} value={r}>{r}</option>)}
          <option value={OTHER}>OTHER</option>
        </Select>
      </Field>
      {otherRelation && text(props, "emergency_contact_relation", "Enter the relationship", { required: true, upper: true, letters: true, maxLength: 50 })}
      <div onBlur={() => touch?.("emergency_contact_phone")} className={otherRelation ? "sm:col-span-2" : ""}>
        <Field label="Phone number" required error={errors.emergency_contact_phone}>
          <PhoneInput value={data.emergency_contact_phone ?? ""} onChange={(v) => set("emergency_contact_phone", v)} />
        </Field>
      </div>
      <AddressFields {...props} prefix="emergency_contact" streetField="emergency_contact_address" streetLabel="Street address" />
    </div>
  );
}
