import { countryCode } from "@/lib/country-codes";

/** ICAO 9303 check digit: weights 7-3-1; digits as-is, A–Z = 10–35, filler "<" = 0. */
export function checkDigit(field: string): string {
  let sum = 0;
  for (let i = 0; i < field.length; i++) {
    const ch = field[i];
    const v = ch >= "0" && ch <= "9" ? Number(ch) : ch >= "A" && ch <= "Z" ? ch.charCodeAt(0) - 55 : 0;
    sum += v * [7, 3, 1][i % 3];
  }
  return String(sum % 10);
}

/** Upper-case, strip accents and apostrophes, turn everything else that is not A–Z / 0–9 into "<". */
function clean(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[̀-ͯ']/g, "")
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, "<");
}

function pad(value: string, length: number): string {
  return value.slice(0, length).padEnd(length, "<");
}

/** YYMMDD, or "<<<<<<" when unknown. */
function mrzDate(value?: string | null): string {
  const m = value?.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? m[1].slice(2) + m[2] + m[3] : "<<<<<<";
}

export type MrzInput = {
  documentCode?: string;
  issuingState?: string;
  documentNumber: string;
  dateOfBirth: string;
  sex: string;
  dateOfExpiry: string;
  nationality: string;
  surname: string;
  givenNames: string;
};

/** The three 30-character lines of a TD1 machine-readable zone (ICAO 9303 Part 5). */
export function td1Mrz(d: MrzInput): [string, string, string] {
  const code = pad(clean(d.documentCode ?? "IR"), 2);
  const state = pad(clean(d.issuingState ?? "NGA"), 3);
  const doc = clean(d.documentNumber).replace(/</g, "");

  // Numbers longer than 9 characters: first 9 in the number field, "<" in its check digit,
  // the rest (plus the check digit over the whole number) at the start of the optional data.
  const docField = doc.length <= 9 ? pad(doc, 9) + checkDigit(pad(doc, 9)) : doc.slice(0, 9) + "<";
  const optional1 = doc.length <= 9 ? "" : doc.slice(9) + checkDigit(doc);
  const line1 = code + state + docField + pad(optional1, 15);

  const dob = mrzDate(d.dateOfBirth);
  const exp = mrzDate(d.dateOfExpiry);
  const sex = d.sex.toUpperCase().startsWith("M") ? "M" : d.sex.toUpperCase().startsWith("F") ? "F" : "<";
  const head = dob + checkDigit(dob) + sex + exp + checkDigit(exp) + pad(countryCode(d.nationality), 3) + pad("", 11);
  const composite = line1.slice(5, 30) + head.slice(0, 7) + head.slice(8, 15) + head.slice(18, 29);
  const line2 = head + checkDigit(composite);

  const line3 = pad(`${clean(d.surname)}<<${clean(d.givenNames)}`.replace(/^<+|<+$/g, "").replace(/(<<)<+/g, "$1"), 30);

  return [line1, line2, line3];
}
