import type { Fingerprint, ScannerDriver } from "./types";

/** Test-only: a drawn ridge pattern and a random template. The live API refuses it. */
export const simulated: ScannerDriver = {
  id: "simulated",
  label: "Simulated scanner (testing only)",
  setup: "",

  async detect() {
    return "Simulated scanner";
  },

  async capture(finger): Promise<Fingerprint> {
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
  },
};
