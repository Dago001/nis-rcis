/** "20TH JANUARY 2026" — legacy NIS date format used on cards and slips. */
export function nisDate(value?: string | null): string {
  if (!value) return "N/A";
  const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  if (Number.isNaN(date.getTime())) return value.toUpperCase();
  const day = date.getDate();
  const suffix = day % 10 === 1 && day !== 11 ? "ST" : day % 10 === 2 && day !== 12 ? "ND" : day % 10 === 3 && day !== 13 ? "RD" : "TH";
  return `${day}${suffix} ${date.toLocaleString("en-GB", { month: "long" }).toUpperCase()} ${date.getFullYear()}`;
}

export function shortDate(value?: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

export function dateTime(value?: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

export function naira(amount: number): string {
  return new Intl.NumberFormat("en-NG", { style: "currency", currency: "NGN", maximumFractionDigits: 0 }).format(amount);
}

/** "Renewal", "Replacement (lost/stolen card)" or "New card", plus "for spouse/child" on dependants' applications. */
export function applicationType(a: { type: string; renewal_of_card_number?: string | null; dependant_relationship?: string | null }, withCard = false): string {
  const card = withCard && a.renewal_of_card_number ? ` of card ${a.renewal_of_card_number}` : "";
  const base = a.type === "RENEWAL" ? `Renewal${card}` : a.type === "REPLACE" ? `Replacement${card} (lost/stolen card)` : "New residence card";
  return a.dependant_relationship ? `${base} · for ${a.dependant_relationship === "CHILD" ? "child" : "spouse"}` : base;
}
