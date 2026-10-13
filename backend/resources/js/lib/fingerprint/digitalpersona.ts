import { withBase } from "@/lib/base-path";
import { bytesToBase64, isPng, unwrapSample, withTimeout, type Fingerprint, type ScannerDriver } from "./types";

/**
 * HID DigitalPersona readers (U.are.U 4500, 5100, 5300, ...) through the HID
 * DigitalPersona Lite Client (also sold as "Authentication Device Client"), a
 * local service on the desk computer reached by HID's WebSdk script
 * (https://127.0.0.1:52181, then a secure WebSocket on a port it chooses).
 *
 * HID's browser API hands out finger images, not ISO templates. The print is
 * taken as a PNG: lossless (ISO/IEC 19794-4 allows it), and the officer can see
 * it. A matching system can derive WSQ or templates from it later.
 */
type Devices = typeof import("@digitalpersona/devices");

const NOT_RUNNING = "The DigitalPersona Lite Client is not running on this computer.";
let webSdk: Promise<void> | null = null;

/** HID's WebSdk script defines window.WebSdk, which the devices library needs. */
function loadWebSdk(): Promise<void> {
  if ((window as unknown as { WebSdk?: unknown }).WebSdk) return Promise.resolve();
  webSdk ??= new Promise<void>((resolve, reject) => {
    const script = document.createElement("script");
    script.src = withBase("/vendor/digitalpersona/websdk.client.min.js");
    script.onload = () => resolve();
    script.onerror = () => {
      webSdk = null;
      reject(new Error("Could not load the DigitalPersona browser library."));
    };
    document.head.appendChild(script);
  });
  return webSdk;
}

async function open(): Promise<{ dp: Devices; reader: InstanceType<Devices["FingerprintReader"]> }> {
  await loadWebSdk();
  const dp = await import("@digitalpersona/devices");
  return { dp, reader: new dp.FingerprintReader() };
}

const QUALITY_HINTS: Record<number, string> = {
  1: "No image: place the finger on the reader.",
  2: "Too light: press a little harder.",
  3: "Too dark: the finger is too wet or pressed too hard.",
  4: "Too noisy: clean the reader glass.",
  5: "Low contrast: clean the finger and the reader.",
  6: "Not enough detail: place more of the finger on the reader.",
  7: "Not centred: place the finger in the middle of the reader.",
  8: "Not recognised as a finger.",
  21: "Finger too wet: dry it and try again.",
};

export const digitalPersona: ScannerDriver = {
  id: "digitalpersona",
  label: "DigitalPersona (HID U.are.U)",
  setup: "Install the HID DigitalPersona Lite Client on this computer and connect the reader. In Firefox, open https://127.0.0.1:52181/get_connection once and accept the certificate (or use Chrome/Edge).",

  async detect() {
    try {
      const { reader } = await open();
      const ids = await withTimeout(reader.enumerateDevices(), 3000, NOT_RUNNING);
      return ids.length ? `DigitalPersona reader${ids.length > 1 ? ` (${ids.length} connected)` : ""}` : null;
    } catch {
      return null;
    }
  },

  async capture(finger): Promise<Fingerprint> {
    const { dp, reader } = await open();
    let ids: string[];
    try {
      ids = await withTimeout(reader.enumerateDevices(), 5000, NOT_RUNNING);
    } catch {
      throw new Error(`${NOT_RUNNING} ${digitalPersona.setup}`);
    }
    if (!ids.length) throw new Error("No DigitalPersona reader found: check the USB cable.");
    const device = ids[0];

    return new Promise<Fingerprint>((resolve, reject) => {
      let hint = "";
      const done = (error: Error | null, print?: Fingerprint) => {
        clearTimeout(timer);
        const noop = () => undefined;
        reader.onSamplesAcquired = noop;
        reader.onQualityReported = noop;
        reader.onErrorOccurred = noop;
        reader.onCommunicationFailed = noop;
        reader.stopAcquisition(device).catch(noop);
        if (error) reject(error);
        else resolve(print!);
      };
      const timer = setTimeout(() => done(new Error(hint || "Timed out: place the finger flat on the reader and try again.")), 20000);

      // A poor scan is reported and the reader keeps waiting for a better one.
      reader.onQualityReported = (e) => {
        hint = e.quality === dp.QualityCode.Good ? "" : QUALITY_HINTS[e.quality] ?? "Poor scan: try again.";
      };
      reader.onErrorOccurred = (e) => done(new Error(`The reader reported error ${e.error}.`));
      reader.onCommunicationFailed = () => done(new Error(`${NOT_RUNNING} ${digitalPersona.setup}`));
      reader.onSamplesAcquired = (e) => {
        const bytes = unwrapSample(e.samples[0]);
        if (!bytes || !isPng(bytes)) return done(new Error("The reader returned an unreadable image: try again."));
        const png = bytesToBase64(bytes);
        done(null, { finger, template: png, format: "PNG", quality: null, nfiq: null, device: "HID DigitalPersona", image: `data:image/png;base64,${png}` });
      };

      reader.startAcquisition(dp.SampleFormat.PngImage, device).catch((e: unknown) => done(e instanceof Error ? e : new Error(String(e))));
    });
  },
};
