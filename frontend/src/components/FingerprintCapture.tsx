"use client";

import { useState } from "react";
import { Alert, Button } from "@/components/ui";

/**
 * Fingerprint capture at the biometrics desk.
 *
 * Works with the SecuGen WebAPI (SGIFPCapture), the local service installed
 * with SecuGen Hamster scanners: it listens on https://localhost:8443 on the
 * desk computer and returns an ISO/IEC 19794-2 template and an image. Other
 * scanners can be added as another driver with the same result shape.
 * A simulated scanner is available for testing; the live API refuses it.
 */
export type Fingerprint = { finger: string; template: string; format: "ISO_19794_2" | "ANSI_378" | "SIMULATED"; quality: number | null; nfiq: number | null; device: string | null; image: string | null };

const FINGERS: [string, string][] = [["R_INDEX", "Right index"], ["L_INDEX", "Left index"], ["R_THUMB", "Right thumb"], ["L_THUMB", "Left thumb"]];
const SERVICE_URL = process.env.NEXT_PUBLIC_FINGERPRINT_SERVICE_URL || "https://localhost:8443/SGIFPCapture";
const LICENSE = process.env.NEXT_PUBLIC_FINGERPRINT_LICENSE || "";
const SCANNER = process.env.NEXT_PUBLIC_FINGERPRINT_SCANNER || "secugen";
const SIMULATOR_ALLOWED = process.env.NODE_ENV !== "production" || SCANNER === "simulated";
const MIN_QUALITY = 40;

async function captureSecugen(finger: string): Promise<Fingerprint> {
  let response: Response;
  try {
    response = await fetch(SERVICE_URL, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ Timeout: "10000", Quality: "50", licstr: LICENSE, templateFormat: "ISO", imageWSQRate: "0.75" }),
    });
  } catch {
    throw new Error("The fingerprint scanner service is not running on this computer. Install the SecuGen WebAPI and connect the scanner.");
  }
  const r = await response.json();
  if (r.ErrorCode !== 0) {
    const reasons: Record<number, string> = { 54: "Timed out: place the finger flat on the scanner and try again.", 55: "No scanner found: check the USB cable." };
    throw new Error(reasons[r.ErrorCode] ?? `The scanner reported error ${r.ErrorCode}.`);
  }
  return {
    finger,
    template: r.TemplateBase64,
    format: "ISO_19794_2",
    quality: typeof r.ImageQuality === "number" ? r.ImageQuality : null,
    nfiq: typeof r.NFIQ === "number" ? r.NFIQ : null,
    device: [r.Manufacturer, r.Model, r.SerialNumber].filter(Boolean).join(" ") || null,
    image: r.BMPBase64 ? `data:image/bmp;base64,${r.BMPBase64}` : null,
  };
}

/** Test-only: a drawn ridge pattern and a random template. */
function captureSimulated(finger: string): Fingerprint {
  const canvas = document.createElement("canvas");
  canvas.width = 160;
  canvas.height = 200;
  const ctx = canvas.getContext("2d")!;
  ctx.fillStyle = "#fff";
  ctx.fillRect(0, 0, 160, 200);
  ctx.strokeStyle = "#333";
  ctx.lineWidth = 2;
  const twist = Math.random() * 0.6;
  for (let r = 6; r < 95; r += 6) {
    ctx.beginPath();
    ctx.ellipse(80 + twist * 10, 105, r * 0.8, r, twist, 0.2, Math.PI * 2 - 0.2);
    ctx.stroke();
  }
  const bytes = crypto.getRandomValues(new Uint8Array(400));
  return { finger, template: btoa(String.fromCharCode(...bytes)), format: "SIMULATED", quality: 80, nfiq: 2, device: "Simulated scanner", image: canvas.toDataURL("image/png") };
}

export function FingerprintCapture({ onChange }: { onChange: (prints: Fingerprint[]) => void }) {
  const [prints, setPrints] = useState<Record<string, Fingerprint>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [simulated, setSimulated] = useState(SCANNER === "simulated");

  function update(next: Record<string, Fingerprint>) {
    setPrints(next);
    onChange(Object.values(next));
  }

  async function capture(finger: string) {
    setBusy(finger);
    setError(null);
    try {
      const print = simulated ? captureSimulated(finger) : await captureSecugen(finger);
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
        {SCANNER === "off" && " Fingerprint scanners are switched off in this installation."}
      </p>
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
                  <span className="text-xs text-slate-400">{p ? "Captured" : "Not captured"}</span>
                )}
              </div>
              {p && <div className="mb-2 text-xs text-slate-500">Quality {p.quality ?? "—"}{p.nfiq ? ` · NFIQ ${p.nfiq}` : ""}</div>}
              <div className="flex justify-center gap-1">
                <Button variant={p ? "secondary" : "primary"} className="!px-3 !py-1.5" disabled={busy !== null || SCANNER === "off"} onClick={() => capture(code)}>
                  {busy === code ? "Place finger…" : p ? "Retake" : "Capture"}
                </Button>
                {p && <Button variant="ghost" className="!px-2 !py-1.5" onClick={() => remove(code)} aria-label={`Remove ${label}`}>✕</Button>}
              </div>
            </li>
          );
        })}
      </ul>
      {SIMULATOR_ALLOWED && SCANNER !== "off" && (
        <label className="flex items-center gap-2 text-xs text-slate-500">
          <input type="checkbox" checked={simulated} onChange={(e) => setSimulated(e.target.checked)} />
          Use the simulated scanner (testing only — refused by the live system)
        </label>
      )}
    </div>
  );
}
