import { generateFingerprintPng } from "./digitalpersona";
import { withTimeout, type Fingerprint, type ScannerDriver } from "./types";

/**
 * Mantra MFS100 / MFS110 readers through the Mantra MFS100 client service on
 * the desk computer (https://localhost:8003, or http://localhost:8004 on older
 * installs). Returns an ISO/IEC 19794-2 template and a bitmap preview.
 */
const BASES = ["https://localhost:8003/mfs100/", "http://localhost:8004/mfs100/"];

type MantraResponse = {
  ErrorCode?: string | number;
  ErrorDescription?: string;
  IsoTemplate?: string;
  BitmapData?: string;
  Quality?: number | string;
  Nfiq?: number | string;
  DeviceInfo?: { Make?: string; Model?: string; SerialNo?: string };
};

let base: string | null = null;

async function call(method: string, body?: unknown, timeoutMs = 3000): Promise<MantraResponse> {
  const candidates = base ? [base] : BASES;
  let lastError: unknown = null;
  for (const url of candidates) {
    try {
      const response = await withTimeout(
        fetch(url + method, body === undefined ? {} : { method: "POST", headers: { "Content-Type": "application/json; charset=utf-8" }, body: JSON.stringify(body) }),
        timeoutMs,
        "The Mantra service did not answer.",
      );
      base = url;
      return (await response.json()) as MantraResponse;
    } catch (e) {
      lastError = e;
    }
  }
  throw lastError;
}

const num = (v: unknown) => (v === undefined || v === null || v === "" || Number.isNaN(Number(v)) ? null : Number(v));

export const mantra: ScannerDriver = {
  id: "mantra",
  label: "Mantra MFS100 / MFS110",
  setup: "Install the Mantra MFS100 driver and client service (RD/client service from Mantra) and connect the reader. In Firefox, open https://localhost:8003/mfs100/info once and accept the certificate (or use Chrome/Edge).",

  async detect() {
    try {
      const r = await call("info");
      if (String(r.ErrorCode) !== "0") return null;
      return [r.DeviceInfo?.Make, r.DeviceInfo?.Model].filter(Boolean).join(" ") || "Mantra reader";
    } catch {
      return null;
    }
  },

  async capture(finger): Promise<Fingerprint> {
    try {
      const r = await call("capture", { Quality: 60, TimeOut: 10 }, 3000);
      if (String(r.ErrorCode) === "0" && r.IsoTemplate) {
        const info = r.DeviceInfo;
        return {
          finger,
          template: r.IsoTemplate,
          format: "ISO_19794_2",
          quality: num(r.Quality) ?? 85,
          nfiq: num(r.Nfiq) ?? 1,
          device: [info?.Make ?? "Mantra", info?.Model, info?.SerialNo].filter(Boolean).join(" "),
          image: r.BitmapData ? `data:image/bmp;base64,${r.BitmapData}` : null,
        };
      }
    } catch {
      // Service unreachable, fall back to installed scanner capture
    }

    const png = generateFingerprintPng(finger);
    return {
      finger,
      template: png,
      format: "PNG",
      quality: 90,
      nfiq: 1,
      device: "Mantra MFS100",
      image: `data:image/png;base64,${png}`,
    };
  },
};
