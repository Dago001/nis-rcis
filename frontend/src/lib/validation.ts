import { NIGERIA_LGAS } from "./nigeria-lgas";

/**
 * Client-side checks for the application form. They mirror the API rules
 * (backend App\Support\ApplicationRules), which always re-check everything.
 */

export const NAME = /^[\p{L}][\p{L} .'-]*$/u;
export const TEXT = /^[\p{L}][\p{L} .,'()/&-]*$/u;
export const ADDRESS = /^[\p{L}0-9][\p{L}0-9 .,'()/#&:;-]*$/u;
export const PHONE = /^\+[1-9][0-9]{6,14}$/;
export const PASSPORT = /^[A-Z0-9]{6,15}$/;

type Data = Record<string, string | undefined>;
type Check = (value: string, data: Data) => string | undefined;

const today = () => new Date().toISOString().slice(0, 10);
const addMonths = (months: number) => {
  const d = new Date();
  d.setMonth(d.getMonth() + months);
  return d.toISOString().slice(0, 10);
};
const addYears = (years: number) => addMonths(years * 12);

const required = (label: string): Check => (v) => (v.trim() ? undefined : `${label} is required.`);
const pattern = (re: RegExp, message: string): Check => (v) => (!v.trim() || re.test(v.trim()) ? undefined : message);
const all = (...checks: Check[]): Check => (v, d) => {
  for (const check of checks) {
    const message = check(v, d);
    if (message) return message;
  }
  return undefined;
};

const lettersOnly = "Use letters only.";

export function countWords(str: string): number {
  const trimmed = str.trim();
  return trimmed ? trimmed.split(/\s+/).filter(Boolean).length : 0;
}

export const RULES: Record<string, Check> = {
  category: (v) => (!v || !v.trim() ? "Select a category." : !["WORK", "BUSINESS", "STUDENT", "RELIGIOUS"].includes(v.trim().toUpperCase()) ? "Select a valid category." : undefined),
  travel_document_type: (v) => (!v || !v.trim() ? "Select a travel document type." : !["PASSPORT", "ECOWAS_TC", "NATIONAL_ID"].includes(v.trim().toUpperCase()) ? "Select a valid travel document." : undefined),
  quota_reference: pattern(/^[A-Za-z0-9][A-Za-z0-9/ .-]*$/, "Use letters, numbers and / only, as printed on the quota approval."),
  employer_name: (v, d) => (d.quota_reference?.trim() && !v.trim() ? "Enter the employer named on the quota approval." : v.trim() && !TEXT.test(v.trim()) ? "Use letters only." : undefined),
  renewal_card_number: all(required("The card number"), pattern(/^[0-9]{3,12}$/, "Card numbers contain digits only.")),
  surname: all(required("Surname"), pattern(NAME, lettersOnly)),
  forenames: all(required("Other names"), pattern(NAME, lettersOnly)),
  nationality: all(
    (v) => (!v || !v.trim() ? "Select your country of origin." : undefined),
    (v) => (/^NIGERIA(N)?$/i.test(v.trim()) ? "Nigerian citizens do not need a residence card." : undefined),
  ),
  sex: required("Gender"),
  // A dependent child may be any age; everyone else must be an adult.
  date_of_birth: all(required("Date of birth"), (v, d) =>
    d.dependant_relationship === "CHILD"
      ? (v >= today() ? "Enter a date in the past." : v < "1900-01-01" ? "Enter a valid date." : undefined)
      : v > addYears(-18) ? "You must be at least 18 years old." : v < "1900-01-01" ? "Enter a valid date." : undefined),
  place_of_birth: all(required("Place of birth"), pattern(TEXT, "Use letters only.")),
  profession: all(required("Profession"), pattern(TEXT, "Use letters only.")),
  height: pattern(/^[0-9]+(\.[0-9]{1,2})?\s?(m|cm)?$/i, "Use a number, e.g. 1.75m or 175cm."),
  complexion: pattern(NAME, lettersOnly),
  eye_color: pattern(NAME, lettersOnly),
  hair_color: pattern(NAME, lettersOnly),
  distinguished_features: pattern(TEXT, "Use letters only."),

  passport_number: all(
    (v, d) => (!v.trim() ? `${d.travel_document_type === "NATIONAL_ID" ? "National ID number" : d.travel_document_type === "ECOWAS_TC" ? "ECOWAS Travel Certificate number" : "Passport number"} is required.` : undefined),
    pattern(/^[A-Za-z0-9-]{4,20}$/, "4–20 letters and digits (hyphens allowed), no spaces.")
  ),
  passport_issue_date: all(
    required("Issue date"),
    (v) => (v > today() ? "The issue date cannot be in the future." : v < "1900-01-01" ? "Enter a valid date." : undefined)
  ),
  passport_expiry: all(
    (v, d) => {
      if (d.travel_document_type === "NATIONAL_ID" && !v) return undefined;
      if (!v) return "Expiring date is required.";
      if (d.passport_issue_date && v < d.passport_issue_date) {
        return "Expiring date cannot be earlier than the issue date.";
      }
      return v <= addMonths(6) ? "Must be valid for at least 6 more months." : undefined;
    }
  ),
  national_id_number: pattern(/^[A-Za-z0-9-]{4,30}$/, "Use letters, numbers and hyphens only."),
  tax_id_number: pattern(/^[A-Za-z0-9-]{4,30}$/, "Use letters, numbers and hyphens only."),

  home_country_address: all(
    required("Address in country of origin"),
    (v) => (countWords(v) > 10 ? "Address must not exceed 10 words." : undefined),
    pattern(ADDRESS, "Use letters, numbers and normal address punctuation only.")
  ),
  domicile_state: required("State"),
  domicile_lga: all(required("Local government area"), (v, d) => (d.domicile_state && !NIGERIA_LGAS[d.domicile_state]?.includes(v) ? "Select a local government area." : undefined)),
  domicile: all(required("Street address"), pattern(ADDRESS, "Use letters, numbers and normal address punctuation only.")),
  change_of_address: pattern(ADDRESS, "Use letters, numbers and normal address punctuation only."),
  phone: all(required("Phone number"), pattern(PHONE, "Enter a valid phone number (digits only).")),
  email: all(required("E-mail address"), pattern(/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/, "Enter a valid e-mail address.")),
  emergency_contact_name: all(required("Full name"), pattern(NAME, lettersOnly)),
  emergency_contact_relation: all(required("Relationship"), pattern(NAME, lettersOnly)),
  emergency_contact_phone: all(required("Phone number"), pattern(PHONE, "Enter a valid phone number (digits only).")),
  emergency_contact_state: required("State"),
  emergency_contact_lga: all(required("Local government area"), (v, d) => (d.emergency_contact_state && !NIGERIA_LGAS[d.emergency_contact_state]?.includes(v) ? "Select a local government area." : undefined)),
  emergency_contact_address: all(required("Address"), pattern(ADDRESS, "Use letters, numbers and normal address punctuation only.")),

  enrollment_center_id: required("Enrollment center"),
  appointment_date: all(required("Appointment date"), (v) => {
    const day = new Date(`${v}T12:00:00`).getDay();
    return v <= today() ? "Choose a future date." : day === 0 || day === 6 ? "Appointments are on weekdays only." : undefined;
  }),
  appointment_time: required("Time slot"),
};

export const GENERAL_FIELDS = [
  "nationality",
  "category",
  "travel_document_type",
  "passport_number",
  "passport_issue_date",
  "passport_expiry",
  "national_id_number",
  "tax_id_number",
];
export const PERSONAL_FIELDS = [
  "surname", "forenames", "nationality", "sex", "date_of_birth", "place_of_birth", "profession",
  "phone", "email",
  "height", "complexion", "eye_color", "hair_color", "distinguished_features"
];
export const PASSPORT_FIELDS = ["passport_number", "passport_issue_date", "passport_expiry", "national_id_number", "tax_id_number"];
export const CONTACT_FIELDS = [
  "home_country_address",
  "domicile_state", "domicile_lga", "domicile", "change_of_address",
  "emergency_contact_name", "emergency_contact_relation", "emergency_contact_phone",
  "emergency_contact_state", "emergency_contact_lga", "emergency_contact_address",
];
export const APPOINTMENT_FIELDS = ["enrollment_center_id", "appointment_date", "appointment_time"];

/** Error for one field, or undefined when it is valid. */
export function fieldError(field: string, data: Data): string | undefined {
  return RULES[field]?.(data[field] ?? "", data);
}

/** Errors for a list of fields. */
export function validate(fields: string[], data: Data): Record<string, string> {
  const errors: Record<string, string> = {};
  for (const field of fields) {
    const message = fieldError(field, data);
    if (message) errors[field] = message;
  }
  return errors;
}

export const RELATIONSHIPS = [
  "SPOUSE", "HUSBAND", "WIFE", "FATHER", "MOTHER", "SON", "DAUGHTER", "BROTHER", "SISTER",
  "UNCLE", "AUNT", "NEPHEW", "NIECE", "COUSIN", "GRANDFATHER", "GRANDMOTHER", "GRANDSON", "GRANDDAUGHTER",
  "FATHER-IN-LAW", "MOTHER-IN-LAW", "BROTHER-IN-LAW", "SISTER-IN-LAW", "SON-IN-LAW", "DAUGHTER-IN-LAW",
  "STEPFATHER", "STEPMOTHER", "GUARDIAN", "FIANCE", "FIANCEE", "FRIEND", "COLLEAGUE", "EMPLOYER", "LANDLORD", "NEIGHBOUR",
];
