"use client";

import { useState, type InputHTMLAttributes } from "react";
import { COUNTRIES, type Country } from "@/lib/countries";
import { Input, Select } from "./ui";

/** Split "+2348012345678" into its country and national number. */
export function splitPhone(value: string, fallbackIso = "NG"): { country: Country; national: string } {
  const digits = value.replace(/\D/g, "");
  const fallback = COUNTRIES.find((c) => c.iso === fallbackIso) ?? COUNTRIES[0];
  if (!value.trim().startsWith("+") || !digits) return { country: fallback, national: digits };

  // Longest calling code wins; prefer the fallback country when codes are shared (e.g. +1).
  const matches = COUNTRIES.filter((c) => digits.startsWith(c.dial)).sort((a, b) => b.dial.length - a.dial.length);
  const best = matches.find((c) => c.iso === fallbackIso && c.dial === matches[0]?.dial) ?? matches[0] ?? fallback;
  return { country: best, national: digits.slice(best.dial.length) };
}

/** Flag emoji from an ISO 3166 alpha-2 code. */
function flag(iso: string): string {
  return /^[A-Z]{2}$/.test(iso) ? String.fromCodePoint(...[...iso].map((ch) => 0x1f1a5 + ch.charCodeAt(0))) : "";
}

/**
 * International phone number: a country-code dropdown plus digits only.
 * The value is E.164 ("+2348012345678"); a leading 0 of the national number
 * is dropped automatically ("0801…" becomes "+234801…").
 */
export function PhoneInput({
  value,
  onChange,
  name,
  required,
  disabled,
  defaultIso = "NG",
  id,
}: {
  value: string;
  onChange?: (value: string) => void;
  name?: string;
  required?: boolean;
  disabled?: boolean;
  defaultIso?: string;
  id?: string;
}) {
  const [iso, setIso] = useState(() => splitPhone(value, defaultIso).country.iso);
  const country = COUNTRIES.find((c) => c.iso === iso) ?? COUNTRIES[0];
  const digits = value.replace(/\D/g, "");
  const national = value.startsWith(`+${country.dial}`) ? digits.slice(country.dial.length) : digits;

  function emit(nextCountry: Country, nextNational: string) {
    const digits = nextNational.replace(/\D/g, "").replace(/^0+/, "").slice(0, 14);
    onChange?.(digits ? `+${nextCountry.dial}${digits}` : "");
  }

  return (
    <div className="flex gap-2">
      <Select
        aria-label="Country calling code"
        value={country.iso}
        disabled={disabled}
        onChange={(e) => {
          const next = COUNTRIES.find((c) => c.iso === e.target.value) ?? country;
          setIso(next.iso);
          emit(next, national);
        }}
        className="!w-40 shrink-0"
      >
        {COUNTRIES.map((c) => (
          <option key={c.iso} value={c.iso}>
            {flag(c.iso)} +{c.dial} {c.name}
          </option>
        ))}
      </Select>
      <Input
        id={id}
        type="tel"
        inputMode="numeric"
        autoComplete="tel-national"
        placeholder={country.iso === "NG" ? "8012345678" : "Phone number"}
        value={national}
        required={required}
        disabled={disabled}
        maxLength={15}
        onChange={(e) => emit(country, e.target.value.replace(/\D/g, ""))}
        onKeyDown={(e) => {
          if (e.key.length === 1 && !/[0-9]/.test(e.key) && !e.ctrlKey && !e.metaKey) e.preventDefault();
        }}
      />
      {name && <input type="hidden" name={name} value={value} />}
    </div>
  );
}

/** Password field with a show/hide "eye" button. */
export function PasswordInput(props: InputHTMLAttributes<HTMLInputElement>) {
  const [visible, setVisible] = useState(false);
  return (
    <div className="relative">
      <Input {...props} type={visible ? "text" : "password"} className={`pr-11 ${props.className ?? ""}`} />
      <button
        type="button"
        onClick={() => setVisible((v) => !v)}
        className="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 hover:bg-nis-mint hover:text-nis-primary"
        aria-label={visible ? "Hide password" : "Show password"}
        aria-pressed={visible}
      >
        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          {visible ? (
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22" />
          ) : (
            <>
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </>
          )}
        </svg>
      </button>
    </div>
  );
}

/** Letters, spaces, hyphens, apostrophes and dots only (names). */
export const NAME_PATTERN = /^[\p{L}][\p{L} .'-]*$/u;
export const PHONE_PATTERN = /^\+[1-9][0-9]{6,14}$/;

export function lettersOnly(value: string): string {
  return value.replace(/[^\p{L} .'-]/gu, "");
}
