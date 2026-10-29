import { NextResponse } from "next/server";
import { config } from "@/lib/config";

export async function GET() {
  // 1. Check local environment variables
  const localKey =
    process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ||
    process.env.TURNSTILE_SITE_KEY ||
    process.env.SITE_KEY;

  if (localKey) {
    return NextResponse.json({ site_key: localKey });
  }

  // 2. Fallback to querying the Laravel backend public endpoint
  try {
    const upstream = await fetch(`${config.apiUrl()}/api/v1/public/turnstile-key`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (upstream.ok) {
      const data = await upstream.json();
      return NextResponse.json({ site_key: data.site_key ?? null });
    }
  } catch {
    // ignore
  }

  return NextResponse.json({ site_key: null });
}
