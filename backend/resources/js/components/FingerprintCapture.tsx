"use client";

import { useEffect, useState } from "react";
import { Alert, Button, Select } from "@/components/ui";
import { CONFIGURED, detectScanner, driver, DRIVERS, SIMULATOR_ALLOWED, type Fingerprint, type ScannerDriver, type ScannerId } from "@/lib/fingerprint";

/**
 * Fingerprint capture at the biometrics desk. Works with several scanner makes
 * (src/lib/fingerprint): DigitalPersona / HID U.are.U, Mantra MFS100/110 and
 * SecuGen Hamster, each through its maker's local service on the desk computer.
 * "Automatic" finds whichever is connected. A simulated scanner is available
 * for testing; the live API refuses it.
 */
export type { Fingerprint } from "@/lib/fingerprint";

const FINGERS: [string, string][] = [["R_INDEX", "Right index"], ["L_INDEX", "Left index"], ["R_THUMB", "Right thumb"], ["L_THUMB", "Left thumb"]];
const MIN_QUALITY = 40;
const CHOICE_KEY = "nis-rcis:scanner";

type Choice = ScannerId | "auto";

function initialChoice(): Choice {
  try {
    const saved = localStorage.getItem(CHOICE_KEY) as Choice | null;
    const valid = saved === "auto" || (saved === "simulated" && SIMULATOR_ALLOWED) || DRIVERS.some((d) => d.id === saved);
    if (saved && valid) return saved;
  } catch {
    // Storage blocked: fall back to the configured default.
  }
  return CONFIGURED === "off" ? "auto" : CONFIGURED;
}

export function FingerprintCapture({ onChange }: { onChange: (prints: Fingerprint[]) => void }) {
  const [prints, setPrints] = useState<Record<string, Fingerprint>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [choice, setChoice] = useState<Choice>(initialChoice);
  const [found, setFound] = useState<{ driver: ScannerDriver; name: string } | null | "searching">("searching");
  const [search, setSearch] = useState(0);

  const off = CONFIGURED === "off";
  const active: ScannerDriver | null = choice === "auto" ? (found && found !== "searching" ? found.driver : null) : driver(choice);

  useEffect(() => {
    if (off || choice !== "auto") return;
    let cancelled = false;
    detectScanner().then((result) => {
      if (!cancelled) setFound(result);
    });
    return () => {
      cancelled = true;
    };
  }, [choice, search, off]);

  function choose(value: Choice) {
    try {
      localStorage.setItem(CHOICE_KEY, value);
    } catch {
      // Not remembered; still used for this visit.
    }
    setError(null);
    setFound("searching");
    setChoice(value);
  }

  function update(next: Record<string, Fingerprint>) {
    setPrints(next);
    onChange(Object.values(next));
  }

  async function capture(finger: string) {
    if (!active) return;
    setBusy(finger);
    setError(null);
    try {
      const print = await active.capture(finger);
      if (print.quality !== null && print.quality < MIN_QUALITY) {
        throw new Error(`Poor image quality (${print.quality}). Clean the scanner and the finger, then try again.`);
      }
      update({ ...prints, [finger]: print });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  function remove(finger: string) {
    const { [finger]: removed, ...rest } = prints;
    void removed;
    update(rest);
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-slate-600">
        Capture at least the two index fingers. If a finger cannot be captured (injury), capture the thumb instead.
        {off && " Fingerprint scanners are switched off in this installation."}
      </p>

      {!off && (
        <div className="space-y-2">
          <label className="flex max-w-xl items-center gap-2 text-sm text-slate-700">
            <span className="shrink-0">Scanner</span>
            <Select value={choice} onChange={(e) => choose(e.target.value as Choice)} disabled={busy !== null}>
              <option value="auto">Automatic (find the connected scanner)</option>
              {DRIVERS.map((d) => <option key={d.id} value={d.id}>{d.label}</option>)}
              {SIMULATOR_ALLOWED && <option value="simulated">Simulated scanner (testing only, refused by the live system)</option>}
            </Select>
          </label>
          {choice === "auto" && (
            <div className="flex flex-wrap items-center gap-3 text-sm">
              {found === "searching" && <span className="text-slate-500">Looking for a fingerprint scanner…</span>}
              {found && found !== "searching" && <span className="text-green-700">Found: {found.name} ({found.driver.label})</span>}
              {found === null && <span className="text-red-700">No fingerprint scanner found.</span>}
              {found !== "searching" && (
                <Button type="button" variant="secondary" className="!px-3 !py-1.5" onClick={() => { setFound("searching"); setSearch((n) => n + 1); }}>
                  Search again
                </Button>
              )}
            </div>
          )}
          {choice === "auto" && found === null && (
            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
              <p className="mb-1 font-medium text-slate-700">Connect the scanner and install its maker&apos;s software on this computer:</p>
              <ul className="list-disc space-y-1 pl-4">
                {DRIVERS.map((d) => <li key={d.id}><strong>{d.label}:</strong> {d.setup}</li>)}
              </ul>
            </div>
          )}
        </div>
      )}

      {error && <Alert tone="danger">{error}</Alert>}
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {FINGERS.map(([code, label]) => {
          const p = prints[code];
          return (
            <li key={code} className="rounded-xl border border-slate-200 p-3 text-center text-sm">
              <div className="font-medium">{label}</div>
              <div className="mx-auto my-2 flex h-32 w-24 items-center justify-center overflow-hidden rounded border border-dashed border-slate-300 bg-slate-50">
                {p?.image ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={p.image} alt={`${label} fingerprint`} className="h-full w-full object-contain" />
                ) : (
                  <span className="px-1 text-xs text-slate-400">{p ? (p.format === "WSQ" ? "Captured ✓ (WSQ image)" : "Captured ✓") : "Not captured"}</span>
                )}
              </div>
              {p && (p.quality !== null || p.nfiq) && (
                <div className="mb-2 text-xs text-slate-500">{[p.quality !== null && `Quality ${p.quality}`, p.nfiq && `NFIQ ${p.nfiq}`].filter(Boolean).join(" · ")}</div>
              )}
              <div className="flex justify-center gap-1">
                <Button variant={p ? "secondary" : "primary"} className="!px-3 !py-1.5" disabled={busy !== null || off || !active} onClick={() => capture(code)}>
                  {busy === code ? "Place finger…" : p ? "Retake" : "Capture"}
                </Button>
                {p && <Button variant="ghost" className="!px-2 !py-1.5" onClick={() => remove(code)} aria-label={`Remove ${label}`}>✕</Button>}
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
