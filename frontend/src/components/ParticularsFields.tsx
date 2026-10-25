"use client";

import { ECOWAS_NATIONALITIES } from "@/lib/nationalities";
import { NIGERIA_LGAS, NIGERIA_STATES } from "@/lib/nigeria-lgas";
import { formatRelationship, RELATIONSHIPS } from "@/lib/validation";
import { lettersOnly, PhoneInput } from "./inputs";
import { Field, Input, Select, Textarea } from "./ui";
import { useI18n } from "./I18nProvider";

export type FormData = Record<string, string>;
export type Props = {
  data: FormData;
  errors: Record<string, string>;
  set: (key: string, value: string) => void;
  /** Marks a field as visited so its error shows straight away. */
  touch?: (key: string) => void;
  /** Marks a field as actively focused so validation does not trigger prematurely. */
  focus?: (key: string | null) => void;
  readOnly?: boolean;
  readOnlyFields?: string[];
};

type TextOptions = { required?: boolean; type?: string; hint?: string; upper?: boolean; letters?: boolean; digits?: boolean; maxLength?: number; max?: string; min?: string };

function text(props: Props, name: string, label: string, opts: TextOptions = {}) {
  const isReadOnly = Boolean(props.readOnly || (props.readOnlyFields && props.readOnlyFields.includes(name)));
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
        readOnly={isReadOnly}
        disabled={isReadOnly}
        aria-invalid={hasError}
        className={hasError ? "!border-red-500" : isReadOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
        onFocus={() => props.focus?.(name)}
        onChange={(e) => {
          if (isReadOnly) return;
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
  const isReadOnly = Boolean(props.readOnly || (props.readOnlyFields && props.readOnlyFields.includes(name)) || opts.disabled);
  const { t } = useI18n();
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
        disabled={isReadOnly}
        aria-invalid={hasError}
        className={hasError ? "!border-red-500" : isReadOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
        onFocus={() => props.focus?.(name)}
        onChange={(e) => {
          if (isReadOnly) return;
          const val = e.target.value;
          props.set(name, val);
        }}
        onBlur={() => {
          props.focus?.(null);
          props.touch?.(name);
        }}
      >
        <option value="">{opts.placeholder ? t(opts.placeholder) : t("Select…")}</option>
        {normalizedOptions.map((o) => (
          <option key={o.value} value={o.value}>
            {t(o.label)}
          </option>
        ))}
      </Select>
    </Field>
  );
}

const OTHER = "__OTHER__";

/** Wizard step 1 — General Information (Nationality, Category, Document Type, Document Details, Fee). */
export function GeneralInfoFields(props: Props & { feeNaira?: number | null }) {
  const { data, errors, set, touch, focus, feeNaira, readOnly } = props;
  const { t } = useI18n();
  const feeDisplay = (feeNaira ?? 35000).toLocaleString("en-NG");
  const today = new Date().toISOString().slice(0, 10);

  const normalizedNat = (data.nationality ?? "").trim();
  const matchedNat = ECOWAS_NATIONALITIES.find(
    (n) => n.value.toLowerCase() === normalizedNat.toLowerCase()
  );
  const selectedNat = matchedNat ? matchedNat.value : normalizedNat;

  const rawCat = (data.category ?? "WORK").trim().toUpperCase();
  const selectedCat = ["WORK", "BUSINESS", "STUDENT", "RELIGIOUS"].includes(rawCat) ? rawCat : "WORK";

  const rawDocType = (data.travel_document_type ?? "PASSPORT").trim().toUpperCase();
  const selectedDocType = rawDocType === "ECOWAS_TC" ? "ECOWAS_TC" : "PASSPORT";

  const numLabel = selectedDocType === "ECOWAS_TC"
    ? "ECOWAS Travel Certificate Number"
    : "Passport Number";

  const numHint = selectedDocType === "ECOWAS_TC"
    ? "As printed on your ECOWAS Travel Certificate"
    : "6–15 letters and digits, no spaces";

  return (
    <div className="space-y-6">
      <div className="grid gap-5 sm:grid-cols-2">
        {/* Row 1, Col 1: Nationality */}
        <Field
          label="Nationality"
          required
          error={errors.nationality}
          hint="Select your country of origin"
        >
          <Select
            name="nationality"
            value={selectedNat}
            disabled={readOnly}
            aria-invalid={Boolean(errors.nationality)}
            className={errors.nationality ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
            onFocus={() => focus?.("nationality")}
            onChange={(e) => {
              if (readOnly) return;
              set("nationality", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("nationality");
            }}
          >
            <option value="">{t("Select country…")}</option>
            {ECOWAS_NATIONALITIES.map((n) => (
              <option key={n.value} value={n.value}>
                {t(n.label)}
              </option>
            ))}
          </Select>
        </Field>

        {/* Row 1, Col 2: Category / Class */}
        <Field
          label="Category / Class"
          required
          error={errors.category}
          hint="Determines documents to provide"
        >
          <Select
            name="category"
            value={selectedCat}
            disabled={readOnly}
            aria-invalid={Boolean(errors.category)}
            className={errors.category ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
            onFocus={() => focus?.("category")}
            onChange={(e) => {
              if (readOnly) return;
              set("category", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("category");
            }}
          >
            <option value="WORK">{t("Work (Employment)")}</option>
            <option value="BUSINESS">{t("Business (Trade / Enterprise)")}</option>
            <option value="STUDENT">{t("Student (Academic)")}</option>
            <option value="RELIGIOUS">{t("Religious Activities")}</option>
          </Select>
        </Field>

        {/* Row 2, Col 1: Travel Document Type */}
        <Field
          label="Documents / Other Documents"
          required
          error={errors.travel_document_type}
          hint="Choose Passport or ECOWAS Travel Certificate only"
        >
          <Select
            name="travel_document_type"
            value={selectedDocType}
            disabled={readOnly}
            aria-invalid={Boolean(errors.travel_document_type)}
            className={errors.travel_document_type ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
            onFocus={() => focus?.("travel_document_type")}
            onChange={(e) => {
              if (readOnly) return;
              set("travel_document_type", e.target.value);
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("travel_document_type");
            }}
          >
            <option value="PASSPORT">{t("Passport")}</option>
            <option value="ECOWAS_TC">{t("ECOWAS Travel Certificate")}</option>
          </Select>
        </Field>

        {/* Row 2, Col 2: Document Number */}
        <Field label={numLabel} required error={errors.passport_number} hint={numHint}>
          <Input
            name="passport_number"
            value={data.passport_number ?? ""}
            maxLength={20}
            readOnly={readOnly}
            disabled={readOnly}
            aria-invalid={Boolean(errors.passport_number)}
            className={errors.passport_number ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
            onFocus={() => focus?.("passport_number")}
            onChange={(e) => {
              if (readOnly) return;
              set("passport_number", e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ""));
            }}
            onBlur={() => {
              focus?.(null);
              touch?.("passport_number");
            }}
          />
        </Field>

        {/* Row 3, Col 1: Document Issue Date */}
        {text(props, "passport_issue_date", "Document Issue Date", {
          required: true,
          type: "date",
          max: today,
          hint: "Issue date cannot be in the future",
        })}

        {/* Row 3, Col 2: Document Expiring Date */}
        {text(props, "passport_expiry", "Document Expiring Date", {
          required: true,
          type: "date",
          min: data.passport_issue_date || today,
          hint: "Must be later than issue date and valid for at least 6 months",
        })}

        {/* Row 4, Col 1: National Identification Number (if any) */}
        {text(props, "national_id_number", "National Identification Number (if any)", {
          required: false,
          maxLength: 30,
          hint: "Optional national ID number from country of origin or Nigeria",
        })}

        {/* Row 4, Col 2: Tax Identification Number (if any) */}
        {text(props, "tax_id_number", "Tax Identification Number (if any)", {
          required: false,
          maxLength: 30,
          hint: "Optional tax identification number",
        })}
      </div>

      {/* Category Required Supporting Documents - Complete Professionally Numbered List */}
      {(() => {
        const cat = data.category || "WORK";
        let categoryTitle = "Work";
        let specificDocs: { title: string; mandatory: boolean }[] = [];

        if (cat === "STUDENT") {
          categoryTitle = "Student";
          specificDocs = [
            { title: "Institution Admission Letter", mandatory: true },
          ];
        } else if (cat === "BUSINESS") {
          categoryTitle = "Business";
          specificDocs = [
            { title: "CAC Business Registration Certificate / Documents", mandatory: true },
          ];
        } else if (cat === "RELIGIOUS") {
          categoryTitle = "Religious Activities";
          specificDocs = [
            { title: "Letter of Invitation from Host Religious Organization", mandatory: true },
            { title: "Evidence of Host Organization Registration or Recognition", mandatory: true },
          ];
        } else {
          categoryTitle = "Work";
          specificDocs = [
            { title: "Employment Appointment / Offer Letter", mandatory: true },
          ];
        }

        const allCategoryDocs = [
          { title: "International Passport data page OR ECOWAS Travel Certificate", mandatory: true },
          { title: "Proof of domicile in Nigeria (Utility bill / Tenancy agreement)", mandatory: true },
          ...specificDocs,
          { title: "Recent passport photograph on white background", mandatory: true },
        ];

        return (
          <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5 text-sm text-slate-800 space-y-3">
            <div className="flex items-center gap-2 border-b border-slate-200/80 pb-2.5">
              <svg className="h-5 w-5 text-nis-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <h3 className="font-semibold text-slate-900 text-sm sm:text-base">
                {t(`Required Supporting Documents — ${categoryTitle}`)}
              </h3>
            </div>
            <p className="text-xs text-slate-600">
              {t("Please ensure you have valid digital copies of the following documents before proceeding to Step 4 (Supporting Documents):")}
            </p>
            <ol className="divide-y divide-slate-200/60 text-xs sm:text-sm">
              {allCategoryDocs.map((doc, idx) => (
                <li key={idx} className="py-2.5 flex items-start justify-between gap-3">
                  <div className="flex items-start gap-2.5">
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[11px] font-bold text-slate-700">
                      {idx + 1}
                    </span>
                    <span className="font-medium text-slate-800 leading-snug">
                      {t(doc.title)}
                    </span>
                  </div>
                  <span className={`shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold ${
                    doc.mandatory
                      ? "bg-emerald-100 text-emerald-800"
                      : "bg-slate-200/80 text-slate-600"
                  }`}>
                    {doc.mandatory ? t("Mandatory") : t("Conditional / if applicable")}
                  </span>
                </li>
              ))}
            </ol>
          </div>
        );
      })()}

      {/* Application Fee Notice */}
      <div className="rounded-xl border border-nis-primary/30 bg-gradient-to-r from-emerald-50 via-teal-50 to-white p-4 shadow-sm">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-nis-primary">{t("Application Fee")}</span>
          <div className="mt-0.5 text-lg font-bold text-slate-900 sm:text-xl">₦{feeDisplay}</div>
        </div>
      </div>
    </div>
  );
}

/** Wizard step 2 — personal particulars. */
export function PersonalFields(props: Props & { lockContact?: boolean }) {
  const { data, errors, set, touch, focus, lockContact, readOnly } = props;
  const { t } = useI18n();
  const isDependant = Boolean(data.dependant_relationship || data.principal_application_id);
  const isChild = isDependant && data.dependant_relationship === "CHILD";
  const today = new Date().toISOString().slice(0, 10);
  const maxBirthDate = isChild ? today : new Date(new Date().setFullYear(new Date().getFullYear() - 18)).toISOString().slice(0, 10);

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
        "Gender",
        [
          { value: "MALE", label: "Male" },
          { value: "FEMALE", label: "Female" },
        ],
        { required: true, placeholder: "Select gender…" }
      )}
      {text(props, "date_of_birth", "Date of birth", { required: true, type: "date", max: maxBirthDate, min: "1900-01-01" })}
      {text(props, "place_of_birth", "Place of birth", { required: true, upper: true })}
      {text(props, "profession", "Profession / occupation", { required: true, upper: true })}
      {text(props, "distinguished_features", "Distinguishing features", { hint: "Leave blank for NONE", upper: true })}

      <h3 className="pt-2 text-sm font-semibold text-nis-primary-dark sm:col-span-2">
        {isDependant ? t("Applicant Contact Details") : t("Your contact details")}
      </h3>
      {lockContact && !isDependant ? (
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
            <Field
              label="Phone number"
              required
              error={errors.phone}
              hint={isDependant ? "Pre-populated with guardian contact. Editable for dependent if desired." : undefined}
            >
              <PhoneInput
                value={data.phone ?? ""}
                disabled={readOnly}
                onChange={(v) => {
                  if (readOnly) return;
                  set("phone", v);
                }}
              />
            </Field>
          </div>
          {text(props, "email", "E-mail address", {
            required: true,
            type: "email",
            maxLength: 255,
            hint: isDependant ? "Pre-populated with guardian contact. Editable for dependent if desired." : undefined,
          })}
        </>
      )}
    </div>
  );
}

/** @deprecated Kept for backward compatibility. Document fields are now in GeneralInfoFields. */
export function PassportFields(_props: Props) {
  return null;
}

/** Wizard step 3 — residence and contacts. */
export function ContactFields(props: Props) {
  const { data, errors, set, touch, focus, readOnly } = props;
  const { t } = useI18n();
  const rawRelation = (data.emergency_contact_relation ?? "").trim();
  const normalizedRelation = formatRelationship(rawRelation);
  const matchedRelation = RELATIONSHIPS.find((r) => r.toLowerCase() === normalizedRelation.toLowerCase() || r.toLowerCase() === rawRelation.toLowerCase());
  const otherRelation = data.emergency_contact_relation_other === "1" || Boolean(rawRelation && !matchedRelation);

  const rawHomeRelation = (data.home_country_emergency_contact_relation ?? "").trim();
  const normalizedHomeRelation = formatRelationship(rawHomeRelation);
  const matchedHomeRelation = RELATIONSHIPS.find((r) => r.toLowerCase() === normalizedHomeRelation.toLowerCase() || r.toLowerCase() === rawHomeRelation.toLowerCase());
  const otherHomeRelation = data.home_country_emergency_contact_relation_other === "1" || Boolean(rawHomeRelation && !matchedHomeRelation);

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {/* Home country residential address - max 10 words */}
      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">{t("Residential address in Home Country")}</h3>
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
          readOnly={readOnly}
          disabled={readOnly}
          aria-invalid={Boolean(errors.home_country_address)}
          className={errors.home_country_address ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
          onFocus={() => focus?.("home_country_address")}
          onChange={(e) => {
            if (readOnly) return;
            set("home_country_address", e.target.value);
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("home_country_address");
          }}
        />
      </Field>

      {/* Residential address in Nigeria:
          Row 1: Street Address & Change of Address side-by-side on same row.
          Row 2: State & LGA side-by-side. */}
      <h3 className="text-sm font-semibold text-nis-primary-dark sm:col-span-2">{t("Residential address in Nigeria (domicile)")}</h3>

      {/* Row 1, Column 1: Street Address */}
      <Field label="Street address" required error={errors.domicile} hint="House number, street and area / town">
        <Input
          name="domicile"
          value={data.domicile ?? ""}
          maxLength={150}
          readOnly={readOnly}
          disabled={readOnly}
          aria-invalid={Boolean(errors.domicile)}
          className={errors.domicile ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
          onFocus={() => focus?.("domicile")}
          onChange={(e) => {
            if (readOnly) return;
            set("domicile", e.target.value);
          }}
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
          readOnly={readOnly}
          disabled={readOnly}
          aria-invalid={Boolean(errors.change_of_address)}
          className={errors.change_of_address ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
          onFocus={() => focus?.("change_of_address")}
          onChange={(e) => {
            if (readOnly) return;
            set("change_of_address", e.target.value);
          }}
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
          disabled={readOnly}
          aria-invalid={Boolean(errors.domicile_state)}
          className={errors.domicile_state ? "!border-red-500" : readOnly ? "!bg-slate-100 !text-slate-700 !cursor-not-allowed font-medium" : ""}
          onFocus={() => focus?.("domicile_state")}
          onChange={(e) => {
            if (readOnly) return;
            set("domicile_state", e.target.value);
            set("domicile_lga", "");
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("domicile_state");
          }}
        >
          <option value="">{t("Select state…")}</option>
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
      <h3 className="pt-2 text-sm font-semibold text-nis-primary-dark sm:col-span-2">{t("Emergency contact in Nigeria")}</h3>
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
          <option value="">{t("Select relationship…")}</option>
          {RELATIONSHIPS.map((r) => <option key={r} value={r}>{t(r)}</option>)}
          <option value={OTHER}>{t("OTHER")}</option>
        </Select>
      </Field>
      {otherRelation && (
        <div className="sm:col-span-2">
          {text(props, "emergency_contact_relation", "Enter the relationship", { required: true, upper: true, letters: true, maxLength: 50 })}
        </div>
      )}

      {/* Row 2: Phone number & State */}
      <div
        onFocus={() => focus?.("emergency_contact_phone")}
        onBlur={() => {
          focus?.(null);
          touch?.("emergency_contact_phone");
        }}
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

      <Field label="State" required error={errors.emergency_contact_state}>
        <Select
          name="emergency_contact_state"
          value={data.emergency_contact_state ?? ""}
          aria-invalid={Boolean(errors.emergency_contact_state)}
          className={errors.emergency_contact_state ? "!border-red-500" : ""}
          onFocus={() => focus?.("emergency_contact_state")}
          onChange={(e) => {
            set("emergency_contact_state", e.target.value);
            set("emergency_contact_lga", "");
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("emergency_contact_state");
          }}
        >
          <option value="">{t("Select state…")}</option>
          {NIGERIA_STATES.map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </Select>
      </Field>

      {/* Row 3: Local Government & Street Address side by side on same row */}
      <div className="sm:col-span-2 grid gap-4 sm:grid-cols-2">
        {select(
          props,
          "emergency_contact_lga",
          "Local government area",
          NIGERIA_LGAS[data.emergency_contact_state ?? ""] ?? [],
          {
            required: true,
            disabled: !(NIGERIA_LGAS[data.emergency_contact_state ?? ""] ?? []).length,
            placeholder: (NIGERIA_LGAS[data.emergency_contact_state ?? ""] ?? []).length
              ? "Select LGA…"
              : "Select a state first",
          }
        )}

        <Field
          label="Street address"
          required
          error={errors.emergency_contact_address}
          hint="House number, street and area / town"
        >
          <Input
            name="emergency_contact_address"
            value={data.emergency_contact_address ?? ""}
            maxLength={150}
            aria-invalid={Boolean(errors.emergency_contact_address)}
            className={errors.emergency_contact_address ? "!border-red-500" : ""}
            onFocus={() => focus?.("emergency_contact_address")}
            onChange={(e) => set("emergency_contact_address", e.target.value)}
            onBlur={() => {
              focus?.(null);
              touch?.("emergency_contact_address");
            }}
          />
        </Field>
      </div>

      {/* Emergency contact in Home Country */}
      <h3 className="pt-3 text-sm font-semibold text-nis-primary-dark sm:col-span-2">{t("Emergency contact in Home Country")}</h3>
      {text(props, "home_country_emergency_contact_name", "Full name", { required: true, upper: true, letters: true })}
      <Field label="Relationship" required error={otherHomeRelation ? undefined : errors.home_country_emergency_contact_relation}>
        <Select
          name="home_country_emergency_contact_relation"
          value={otherHomeRelation ? OTHER : matchedHomeRelation ?? ""}
          className={!otherHomeRelation && errors.home_country_emergency_contact_relation ? "!border-red-500" : ""}
          onFocus={() => focus?.("home_country_emergency_contact_relation")}
          onChange={(e) => {
            if (e.target.value === OTHER) {
              set("home_country_emergency_contact_relation_other", "1");
              set("home_country_emergency_contact_relation", "");
            } else {
              set("home_country_emergency_contact_relation_other", "");
              set("home_country_emergency_contact_relation", e.target.value);
            }
          }}
          onBlur={() => {
            focus?.(null);
            touch?.("home_country_emergency_contact_relation");
          }}
        >
          <option value="">{t("Select relationship…")}</option>
          {RELATIONSHIPS.map((r) => <option key={r} value={r}>{t(r)}</option>)}
          <option value={OTHER}>{t("OTHER")}</option>
        </Select>
      </Field>
      {otherHomeRelation && (
        <div className="sm:col-span-2">
          {text(props, "home_country_emergency_contact_relation", "Enter the relationship", { required: true, upper: true, letters: true, maxLength: 50 })}
        </div>
      )}

      <div
        onFocus={() => focus?.("home_country_emergency_contact_phone")}
        onBlur={() => {
          focus?.(null);
          touch?.("home_country_emergency_contact_phone");
        }}
      >
        <Field label="Phone number" required error={errors.home_country_emergency_contact_phone}>
          <PhoneInput
            value={data.home_country_emergency_contact_phone ?? ""}
            onChange={(v) => {
              set("home_country_emergency_contact_phone", v);
            }}
          />
        </Field>
      </div>

      <Field
        label="Address"
        required
        error={errors.home_country_emergency_contact_address}
        hint="Full residential address in home country"
      >
        <Input
          name="home_country_emergency_contact_address"
          value={data.home_country_emergency_contact_address ?? ""}
          maxLength={300}
          aria-invalid={Boolean(errors.home_country_emergency_contact_address)}
          className={errors.home_country_emergency_contact_address ? "!border-red-500" : ""}
          onFocus={() => focus?.("home_country_emergency_contact_address")}
          onChange={(e) => set("home_country_emergency_contact_address", e.target.value)}
          onBlur={() => {
            focus?.(null);
            touch?.("home_country_emergency_contact_address");
          }}
        />
      </Field>
    </div>
  );
}
