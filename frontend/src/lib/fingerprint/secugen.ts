import type { Fingerprint, ScannerDriver } from "./types";

/**
 * SecuGen Hamster scanners through the SecuGen WebAPI (SGIFPCapture), a local
 * service on the desk computer at https://localhost:8443. Returns an ISO/IEC
 * 19794-2 template and a bitmap preview.
 */
const SERVICE_URL = process.env.NEXT_PUBLIC_FINGERPRINT_SERVICE_URL || "https://localhost:8443/SGIFPCapture";
const LICENSE = process.env.NEXT_PUBLIC_FINGERPRINT_LICENSE || "";

type SecugenResponse = {
  ErrorCode: number;
  TemplateBase64?: string;
  BMPBase64?: string;
  ImageQuality?: number;
  NFIQ?: number;
  Manufacturer?: string;
  Model?: string;
  SerialNumber?: string;
};

async function call(timeoutMs: number): Promise<SecugenResponse> {
  const response = await fetch(SERVICE_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ Timeout: String(timeoutMs), Quality: "50", licstr: LICENSE, templateFormat: "ISO", imageWSQRate: "0.75" }),
  });
  return (await response.json()) as SecugenResponse;
}

export const secugen: ScannerDriver = {
  id: "secugen",
  label: "SecuGen Hamster",
  setup: `Install the SecuGen WebAPI and the SecuGen driver, and connect the scanner. In Firefox, open ${new URL(SERVICE_URL).origin} once and accept the certificate (or use Chrome/Edge).`,

  async detect() {
    try {
      // A short capture attempt: 54 = timed out waiting for a finger (scanner present), 55 = no scanner.
      const r = await call(500);
      return r.ErrorCode === 55 ? null : "SecuGen scanner";
    } catch {
      return null;
    }
  },

  async capture(finger): Promise<Fingerprint> {
    let r: SecugenResponse;
    try {
      r = await call(10000);
    } catch {
      throw new Error(`The SecuGen scanner service is not reachable. ${secugen.setup}`);
    }
    if (r.ErrorCode !== 0) {
      const reasons: Record<number, string> = { 54: "Timed out: place the finger flat on the scanner and try again.", 55: "No SecuGen scanner found: check the USB cable." };
      throw new Error(reasons[r.ErrorCode] ?? `The scanner reported error ${r.ErrorCode}.`);
    }
    return {
      finger,
      template: r.TemplateBase64 ?? "",
      format: "ISO_19794_2",
      quality: typeof r.ImageQuality === "number" ? r.ImageQuality : null,
      nfiq: typeof r.NFIQ === "number" ? r.NFIQ : null,
      device: [r.Manufacturer, r.Model, r.SerialNumber].filter(Boolean).join(" ") || "SecuGen",
      image: r.BMPBase64 ? `data:image/bmp;base64,${r.BMPBase64}` : null,
    };
  },
};
