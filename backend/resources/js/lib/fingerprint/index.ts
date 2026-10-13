import { digitalPersona } from "./digitalpersona";
import { mantra } from "./mantra";
import { secugen } from "./secugen";
import { simulated } from "./simulated";
import type { ScannerDriver, ScannerId } from "./types";

export type { Fingerprint, FingerprintFormat, ScannerDriver, ScannerId } from "./types";

/**
 * Supported scanner makes, in the order automatic detection prefers them.
 * To add another make: write a driver returning the same Fingerprint fields
 * (see types.ts), add it here, and allow its local service in the CSP
 * (next.config.ts, connect-src).
 */
export const DRIVERS: ScannerDriver[] = [digitalPersona, mantra, secugen];

/** NEXT_PUBLIC_FINGERPRINT_SCANNER: auto (default), digitalpersona, mantra, secugen, simulated or off. */
export const CONFIGURED = (process.env.NEXT_PUBLIC_FINGERPRINT_SCANNER || "auto") as ScannerId | "auto" | "off";
export const SIMULATOR_ALLOWED = process.env.NODE_ENV !== "production" || CONFIGURED === "simulated";

export function driver(id: ScannerId): ScannerDriver {
  return id === "simulated" ? simulated : DRIVERS.find((d) => d.id === id) ?? secugen;
}

/** Ask every scanner service at once; the first make (in DRIVERS order) with a reader connected wins. */
export async function detectScanner(): Promise<{ driver: ScannerDriver; name: string } | null> {
  const found = await Promise.all(DRIVERS.map((d) => d.detect().catch(() => null)));
  const index = found.findIndex(Boolean);
  return index === -1 ? null : { driver: DRIVERS[index], name: found[index]! };
}
