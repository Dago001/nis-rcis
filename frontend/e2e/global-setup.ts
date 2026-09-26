import { execFileSync } from "node:child_process";
import path from "node:path";

/**
 * Give the staff test account a fresh authenticator, so the test can set it
 * up and sign in with a computed code. Needs the backend folder next to this one.
 */
export default function globalSetup() {
  const backend = process.env.E2E_BACKEND_DIR ?? path.resolve(__dirname, "../../backend");
  execFileSync("php", ["artisan", "nis:staff-reset-2fa", STAFF_ID], { cwd: backend, stdio: "inherit" });
}

export const STAFF_ID = "10004";
