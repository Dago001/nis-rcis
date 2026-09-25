import { NextResponse, type NextRequest } from "next/server";
import { config } from "@/lib/config";

/**
 * Signed-out applicant account actions, forwarded to the API.
 * Only these endpoints are reachable through this route.
 */
const ALLOWED = new Set(["register", "email/resend", "password/forgot", "password/reset"]);

export async function POST(request: NextRequest, ctx: RouteContext<"/api/public-account/[...path]">) {
  const path = (await ctx.params).path.join("/");
  if (!ALLOWED.has(path)) return NextResponse.json({ message: "Not found" }, { status: 404 });

  const origin = request.headers.get("origin");
  if (origin && origin !== config.appUrl()) {
    return NextResponse.json({ message: "Cross-origin request refused" }, { status: 403 });
  }

  const upstream = await fetch(`${config.apiUrl()}/api/v1/applicant/${path}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Forwarded-For": request.headers.get("x-forwarded-for") ?? "",
    },
    body: await request.text(),
    cache: "no-store",
  });

  return new NextResponse(upstream.body, {
    status: upstream.status,
    headers: { "Content-Type": upstream.headers.get("content-type") ?? "application/json" },
  });
}
