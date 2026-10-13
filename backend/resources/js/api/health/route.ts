import { NextResponse } from "next/server";

export const dynamic = "force-dynamic";

/**
 * Uptime check for the website: confirms this server is up and can reach the
 * API. Overall status only. 200 when working, 503 when the API is down or failing.
 */
export async function GET() {
  const api = process.env.API_URL ?? "http://127.0.0.1:8000";
  let apiStatus = "unreachable";
  try {
    const response = await fetch(`${api}/api/v1/health`, { cache: "no-store", signal: AbortSignal.timeout(5000), headers: { Accept: "application/json" } });
    apiStatus = ((await response.json()) as { status?: string }).status ?? "unknown";
  } catch {
    // reported below
  }
  const ok = apiStatus === "ok" || apiStatus === "warn";
  return NextResponse.json({ status: ok ? "ok" : "fail", web: "ok", api: apiStatus, time: new Date().toISOString() }, { status: ok ? 200 : 503, headers: { "Cache-Control": "no-store" } });
}
