/**
 * What every fingerprint scanner driver returns for one finger.
 *
 * `template` is base64: an ISO/IEC 19794-2 (or ANSI 378) minutiae template, or a
 * finger image (ISO/IEC 19794-4: WSQ or lossless PNG) for scanners whose browser
 * software only hands out images. All are accepted and stored encrypted.
 */
export type FingerprintFormat = "ISO_19794_2" | "ANSI_378" | "WSQ" | "PNG" | "SIMULATED";

export type Fingerprint = {
  finger: string;
  template: string;
  format: FingerprintFormat;
  quality: number | null;
  nfiq: number | null;
  device: string | null;
  /** Preview for the officer (data: URL); stays on the desk, never sent. */
  image: string | null;
};

export type ScannerId = "digitalpersona" | "mantra" | "secugen" | "simulated";

export interface ScannerDriver {
  id: ScannerId;
  label: string;
  /** Name of the connected reader, or null when this make is not connected or its service is not running. */
  detect(): Promise<string | null>;
  capture(finger: string): Promise<Fingerprint>;
  /** What the desk needs installed, shown when the scanner cannot be reached. */
  setup: string;
}

/** Rejects with `message` if the promise takes longer than `ms`. */
export function withTimeout<T>(promise: Promise<T>, ms: number, message: string): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(message)), ms);
    promise.then(
      (value) => { clearTimeout(timer); resolve(value); },
      (error) => { clearTimeout(timer); reject(error); },
    );
  });
}

/** base64url (as used by DigitalPersona) to standard base64. */
export function base64FromUrl(value: string): string {
  const b64 = value.replace(/-/g, "+").replace(/_/g, "/");
  return b64 + "=".repeat((4 - (b64.length % 4)) % 4);
}

export function bytesToBase64(bytes: Uint8Array): string {
  let binary = "";
  for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  return btoa(binary);
}

/**
 * The image bytes inside a DigitalPersona sample. Depending on the format and
 * client version the sample is a base64url string or { Data }, and the data may
 * itself be a JSON wrapper with its own base64url "Data".
 */
export function unwrapSample(sample: unknown): Uint8Array | null {
  let data = typeof sample === "string" ? sample : (sample as { Data?: unknown } | null)?.Data;
  for (let depth = 0; typeof data === "string" && depth < 3; depth++) {
    const bytes = Uint8Array.from(atob(base64FromUrl(data)), (c) => c.charCodeAt(0));
    if (bytes[0] !== 0x7b /* "{" */) return bytes;
    try {
      data = (JSON.parse(new TextDecoder().decode(bytes)) as { Data?: unknown }).Data;
    } catch {
      return bytes;
    }
  }
  return null;
}

export const isPng = (b: Uint8Array) => b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4e && b[3] === 0x47;
