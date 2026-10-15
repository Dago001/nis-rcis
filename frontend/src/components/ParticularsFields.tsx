"use client";

import { ECOWAS_NATIONALITIES } from "@/lib/nationalities";
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
  /** Marks a field as actively focused so validation does not trigger prematurely. */
  focus?: (key: string | null) => void;
};

type TextOptions = { required?: boolean; type?: string; hint?: string; upper?: boolean; letters?: boolean; digits?: boolean; maxLength?: number; max?: string; min?: string };

function text(props: Props, name: string, label: string, opts: TextOptions = {}) {
  const hasError = Boolean(props.errors[name]);
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
        aria-invalid={hasError}
        className={hasError ? "!border-red-500" : ""}
        onFocus={() => props.focus?.(name)}
        onChange={(e) => {
          let value = e.target.value;
          if (opts.letters) value = lettersOnly(value);
          if (opts.digits) value = value.replace(/\D/g, "");
          props.set(name, opts.upper ? value.toUpperCase() : value);
        }}
        onBlur={() => {
          props.focus?.(null);
          props.touch?.(name);
        }}
        required={opts.required}
      />
    </Field>
  );
}

export type SelectOption = string | { value: string; label: string };

function select(
  props: Props,
  name: string,
  label: string,
  options: SelectOption[],
  opts: { required?: boolean; placeholder?: string; disabled?: boolean } = {}
) {
  const normalizedOptions = options.map((o) =>
    typeof o === "string" ? { value: o, label: o } : o
  );

  const rawValue = (props.data[name] ?? "").trim();
  const matched = normalizedOptions.find(
    (o) => o.value.toLowerCase() === rawValue.toLowerCase()
  );
  const selectedValue = matched ? matched.value : rawValue;
  const hasError = Boolean(props.errors[name]);

  return (
    <Field label={label} required={opts.required} error={props.errors[name]}>
      <Select
        name={name}
        value={selectedValue}
        disabled={opts.disabled}
        aria-invalid={hasError}
        className={hasError ? "!border-red-500" : ""}
        onFocus={() => props.focus?.(name)}
        onChange={(e) => {
          const val = e.target.value;
          props.set(name, val);
        }}
        onBlur={() => {
          props.focus?.(null);
          props.touch?.(name);
        }}
      >
        <option value="">{opts.placeholder ?? "Select…"}</option>
        {normalizedOptions.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </Select>
    </Field>
  );
}

const OTHER = "__OTHER__";

/** Wizard step 1 of 8 — General Information (Nationality, Category, Document Type, Prescribed Fee). */
export function GeneralInfoFields(props: Props & { feeNaira?: number | null }) {
  const { data, errors, set, touch, focus, feeNaira } = props;
  const feeDisplay = (feeNaira ?? 35000).toLocaleString("en-NG");

  const normalizedNat = (data.nationality ?? "").trim();
  const matchedNat = ECOWAS_NATIONALITIES.find(
    (n) => n.value.toLowerCase() === normalizedNat.toLowerCase()
  );
  const selectedNat = matchedNat ? matchedNat.value : normalizedNat;

  const rawCat = (data.category ?? "WORK").trim().toUpperCase();
  const selectedCat = ["WORK", "BUSINESS", "STUDENT"].includes(rawCat) ? rawCat : "WORK";

  const rawDocType = (data.travel_document_type ?? "PASSPORT").trim().toUpperCase();
  const selectedDocType = rawDocType === "ECOWAS_TC" ? "ECOWAS_TC" : "PASSPORT";

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
            name="nationality"
            value={selectedNat}
            aria-invalid={Boolean(errors.nationality)}
            className={errors.nationality ? "!border-red-500" : ""}
            onFocus={() => focus?.("nationality")}
            onChange={(e) => {
              set("nationality", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("nationality");
            }}
          >
            <option value="">Select country…</option>
            {ECOWAS_NATIONALITIES.map((n) => (
              <option key={n.value} value={n.value}>
                {n.label}
              </option>
            ))}
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
            name="category"
            value={selectedCat}
            aria-invalid={Boolean(errors.category)}
            className={errors.category ? "!border-red-500" : ""}
            onFocus={() => focus?.("category")}
            onChange={(e) => {
              set("category", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("category");
            }}
          >
            <option value="WORK">Work (Employment)</option>
            <option value="BUSINESS">Business (Trade / Enterprise)</option>
            <option value="STUDENT">Student (Academic)</option>
          </Select>
        </Field>

        {/* Document Type: Passport, ECOWAS Travel Certificate - Standard 1-col size */}
        <div>
          <Field
            label="Documents / Other Documents"
            required
            error={errors.travel_document_type}
            hint="Choose Passport or ECOWAS Travel Certificate only"
          >
            <Select
              name="travel_document_type"
              value={selectedDocType}
              aria-invalid={Boolean(errors.travel_document_type)}
              className={errors.travel_document_type ? "!border-red-500" : ""}
              onFocus={() => focus?.("travel_document_type")}
              onChange={(e) => {
                set("travel_document_type", e.target.value);
              }}
              onBlur={() => {
                focus?.(null);
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
  const { data, errors, set, touch, focus, lockContact } = props;
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
      {select(
        props,
        "sex",
        "Sex",
        [
          { value: "MALE", label: "Male" },
          { value: "FEMALE", label: "Female" },
        ],
        { required: true, placeholder: "Select sex…" }
      )}
      {text(props, "date_of_birth", "Date of birth", { required: true, type: "date", max: maxBirthDate, min: "1900-01-01" })}
      {text(props, "place_of_birth", "Place of birth", { required: true, upper: true })}
      {text(props, "profession", "Profession / occupation", { required: true, upper: true })}
      {select(
        props,
        "blood_group",
        "Blood group",
        ["UNKNOWN", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],
        { placeholder: "Select blood group…" }
      )}
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
          <div
            onFocus={() => focus?.("phone")}
            onBlur={() => {
              focus?.(null);
              touch?.("phone");
            }}
          >
            <Field label="Phone number" required error={errors.phone}>
              <PhoneInput
                value={data.phone ?? ""}
                onChange={(v) => {
                  set("phone", v);
                }}
              />
            </Field>
          </div>
          {text(props, "email", "E-mail address", { required: true, type: "email", maxLength: 255 })}
        </>
      )}
    </div>
  );
}

/** Wizard step 3 — Passport or ECOWAS Travel Certificate only. */
export function PassportFields(props: Props) {
  const { data, errors, set, touch, focus } = props;
  const today = new Date().toISOString().slice(0, 10);
  const docType = data.travel_document_type === "ECOWAS_TC" ? "ECOWAS_TC" : "PASSPORT";

  const numLabel = docType === "ECOWAS_TC"
    ? "ECOWAS Travel Certificate Number"
    : "Passport Number";

  const numHint = docType === "ECOWAS_TC"
    ? "As printed on your ECOWAS Travel Certificate"
    : "6–15 letters and digits, no spaces";

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {/* Documents / Other Documents - 1 column to match standard field size */}
      <div>
        <Field label="Documents / Other Documents" required hint="Passport or ECOWAS Travel Certificate only">
          <Select
            name="travel_document_type"
            value={docType}
            onFocus={() => focus?.("travel_document_type")}
            onChange={(e) => {
              set("travel_document_type", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
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
          name="passport_number"
          value={data.passport_number ?? ""}
          maxLength={20}
          aria-invalid={Boolean(errors.passport_number)}
          className={errors.passport_number ? "!border-red-500" : ""}
          onFocus={() => focus?.("passport_number")}
          onChange={(e) => set("passport_number", e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ""))}
          onBlur={() => {
            focus?.(null);
            touch?.("passport_number");
          }}
        />
      </Field>

      {/* Document Issue Date -> Issue Date (compulsory, date picker) */}
      {text(props, "passport_issue_date", "Issue Date", { required: true, type: "date", max: today })}

      {/* Document Expiring Date -> Expiring Date (compulsory, cannot be earlier than issue date) */}
      {text(props, "passport_expiry", "Expiring Date", {
        required: true,
        type: "date",
        min: data.passport_issue_date || today,
        hint: "Must be later than issue date and valid for at least 6 months",
      })}

      {text(props, "national_id_number", "National Identification Number (NIN, if any)", { digits: true, maxLength: 11, hint: "11 digits (Nigerian NIN)" })}
      {text(props, "tax_id_number", "Tax identification number (if any)", { maxLength: 20 })}
    </div>
  );
}

/** State, then its local government areas, then the street address. */
function AddressFields(props: Props & { prefix: "domicile" | "emergency_contact"; streetField: string; streetLabel: string }) {
  const { data, set, touch, focus, prefix, streetField, streetLabel, errors } = props;
  const stateField = `${prefix}_state`;
  const lgaField = `${prefix}_lga`;
  const lgas = NIGERIA_LGAS[data[stateField] ?? ""] ?? [];
  return (
    <>
      <Field label="State" required error={errors[stateField]}>
        <Select
          name={stateField}
          value={data[stateField] ?? ""}
          className={errors[stateField] ? "!border-red-500" : ""}
          onFocus={() => focus?.(stateField)}
          onChange={(e) => {
            set(stateField, e.target.value);
            set(lgaField, "");
          }}
          onBlur={() => {
            focus?.(null);
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
          <Input
            name={streetField}
            value={data[streetField] ?? ""}
            maxLength={150}
            className={errors[streetField] ? "!border-red-500" : ""}
            onFocus={() => focus?.(streetField)}
            onChange={(e) => set(streetField, e.target.value)}
            onBlur={() => {
              focus?.(null);
              touch?.(streetField);
            }}
          />
        </Field>
      </div>
    </>
  );
}

/** Wizard step 4 — residence and contacts. */
export function ContactFields(props: Props) {
  const { data, errors, set, touch, focus } = props;
  const rawRelation = (data.emergency_contact_relation ?? "").trim();
  const matchedRelation = RELATIONSHIPS.find((r) => r.toLowerCase() === rawRelation.toLowerCase());
  const otherRelation = data.emergency_contact_relation_other === "1" || Boolean(rawRelation && !matchedRelation);

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {/* Home country residential address - max 10 words */}
      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">Residential address in Home Country</h3>
      <div className="sm:col-span-2">
        <Field
          label="Address in country of origin"
          required
          error={errors.home_country_address}
          hint="Permanent residential address in your home country (Maximum 10 words)"
        >
          <Input
            name="home_country_address"
            value={data.home_country_address ?? ""}
            maxLength={300}
            aria-invalid={Boolean(errors.home_country_address)}
            className={errors.home_country_address ? "!border-red-500" : ""}
            onFocus={() => focus?.("home_country_address")}
            onChange={(e) => set("home_country_address", e.target.value)}
            onBlur={() => {
              focus?.(null);
              touch?.("home_country_address");
            }}
          />
        </Field>
      </div>

      {/* Residential address in Nigeria:
          Row 1: Street Address & Change of Address side-by-side on same row.
          Row 2: State & LGA side-by-side. */}
      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">Residential address in Nigeria (domicile)</h3>

      {/* Row 1, Column 1: Street Address */}
      <Field label="Street address" required error={errors.domicile} hint="House number, street and area / town">
        <Input
          name="domicile"
          value={data.domicile ?? ""}
          maxLength={150}
          aria-invalid={Boolean(errors.domicile)}
          className={errors.domicile ? "!border-red-500" : ""}
          onFocus={() => focus?.("domicile")}
          onChange={(e) => set("domicile", e.target.value)}
          onBlur={() => {
            focus?.(null);
            touch?.("domicile");
          }}
        />
      </Field>

      {/* Row 1, Column 2: Change of Address (if any) */}
      <Field label="Change of address (if any)" error={errors.change_of_address} hint="If your address has changed recently">
        <Input
          name="change_of_address"
          value={data.change_of_address ?? ""}
          maxLength={150}
          aria-invalid={Boolean(errors.change_of_address)}
          className={errors.change_of_address ? "!border-red-500" : ""}
          onFocus={() => focus?.("change_of_address")}
          onChange={(e) => set("change_of_address", e.target.value)}
          onBlur={() => {
            focus?.(null);
            touch?.("change_of_address");
          }}
        />
      </Field>

      {/* Row 2, Column 1: State */}
      <Field label="State" required error={errors.domicile_state}>
        <Select
          name="domicile_state"
          value={data.domicile_state ?? ""}
          aria-invalid={Boolean(errors.domicile_state)}
          className={errors.domicile_state ? "!border-red-500" : ""}
          onFocus={() => focus?.("domicile_state")}
          onChange={(e) => {
            set("domicile_state", e.target.value);
            set("domicile_lga", "");
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("domicile_state");
          }}
        >
          <option value="">Select state…</option>
          {NIGERIA_STATES.map((s) => <option key={s} value={s}>{s}</option>)}
        </Select>
      </Field>

      {/* Row 2, Column 2: Local Government Area */}
      {select(
        props,
        "domicile_lga",
        "Local government area",
        NIGERIA_LGAS[data.domicile_state ?? ""] ?? [],
        {
          required: true,
          disabled: !(NIGERIA_LGAS[data.domicile_state ?? ""] ?? []).length,
          placeholder: (NIGERIA_LGAS[data.domicile_state ?? ""] ?? []).length ? "Select LGA…" : "Select a state first",
        }
      )}

      {/* Emergency contact in Nigeria */}
      <h3 className="pt-2 text-sm font-semibold text-nis-primary-dark sm:col-span-2">Emergency contact in Nigeria</h3>
      {text(props, "emergency_contact_name", "Full name", { required: true, upper: true, letters: true })}
      <Field label="Relationship" required error={otherRelation ? undefined : errors.emergency_contact_relation}>
        <Select
          name="emergency_contact_relation"
          value={otherRelation ? OTHER : matchedRelation ?? ""}
          className={!otherRelation && errors.emergency_contact_relation ? "!border-red-500" : ""}
          onFocus={() => focus?.("emergency_contact_relation")}
          onChange={(e) => {
            if (e.target.value === OTHER) {
              set("emergency_contact_relation_other", "1");
              set("emergency_contact_relation", "");
            } else {
              set("emergency_contact_relation_other", "");
              set("emergency_contact_relation", e.target.value);
            }
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("emergency_contact_relation");
          }}
        >
          <option value="">Select relationship…</option>
          {RELATIONSHIPS.map((r) => <option key={r} value={r}>{r}</option>)}
          <option value={OTHER}>OTHER</option>
        </Select>
      </Field>
      {otherRelation && text(props, "emergency_contact_relation", "Enter the relationship", { required: true, upper: true, letters: true, maxLength: 50 })}
      <div
        onFocus={() => focus?.("emergency_contact_phone")}
        onBlur={() => {
          focus?.(null);
          touch?.("emergency_contact_phone");
        }}
        className={otherRelation ? "sm:col-span-2" : ""}
      >
        <Field label="Phone number" required error={errors.emergency_contact_phone}>
          <PhoneInput
            value={data.emergency_contact_phone ?? ""}
            onChange={(v) => {
              set("emergency_contact_phone", v);
            }}
          />
        </Field>
      </div>
      <AddressFields {...props} prefix="emergency_contact" streetField="emergency_contact_address" streetLabel="Street address" />
    </div>
  );
}
