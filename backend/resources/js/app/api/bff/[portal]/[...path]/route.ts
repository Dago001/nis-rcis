import { NextResponse, type NextRequest } from "next/server";
import { config, isPortal } from "@/lib/config";
import { needsRefresh, refreshTokens } from "@/lib/oauth";
import { clearSession, readSession, sessionCookie, writeSession, type TokenSet } from "@/lib/session";

/**
 * Backend-for-frontend proxy.
 *
 *   /api/bff/staff/...     -> {API}/api/v1/staff/...      (staff bearer token)
 *   /api/bff/applicant/... -> {API}/api/v1/applicant/...  (applicant bearer token)
 *   /api/bff/public/...    -> {API}/api/v1/public/...     (no token)
 *
 * Tokens are refreshed transparently and never exposed to browser JS.
 */
async function handle(request: NextRequest, ctx: RouteContext<"/api/bff/[portal]/[...path]">) {
  const { portal, path } = await ctx.params;

  if (portal !== "public" && !isPortal(portal)) {
    return NextResponse.json({ message: "Not found" }, { status: 404 });
  }
  if (path.some((segment) => segment === ".." || segment.includes("/"))) {
    return NextResponse.json({ message: "Bad request" }, { status: 400 });
  }

  // CSRF defence in depth (cookies are also SameSite=Lax).
  if (request.method !== "GET" && request.method !== "HEAD") {
    const origin = request.headers.get("origin");
    if (origin && origin !== config.appUrl()) {
      return NextResponse.json({ message: "Cross-origin request refused" }, { status: 403 });
    }
  }

  let tokens: TokenSet | null = null;
  let refreshed = false;

  if (portal !== "public") {
    tokens = readSession(portal, request.cookies.get(sessionCookie(portal))?.value);
    if (!tokens) return NextResponse.json({ message: "Unauthenticated" }, { status: 401 });

    if (needsRefresh(tokens)) {
      try {
        tokens = await refreshTokens(portal, tokens.refreshToken);
        refreshed = true;
      } catch {
        const response = NextResponse.json({ message: "Session expired" }, { status: 401 });
        clearSession(response, portal);
        return response;
      }
    }
  }

  const target = new URL(`${config.apiUrl()}/api/v1/${portal}/${path.map(encodeURIComponent).join("/")}`);
  target.search = request.nextUrl.search;

  const headers = new Headers({ Accept: "application/json" });
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  if (tokens) headers.set("Authorization", `Bearer ${tokens.accessToken}`);
  const forwardedFor = request.headers.get("x-forwarded-for");
  if (forwardedFor) headers.set("X-Forwarded-For", forwardedFor);

  const hasBody = !["GET", "HEAD"].includes(request.method);
  const upstream = await fetch(target, {
    method: request.method,
    headers,
    body: hasBody ? await request.arrayBuffer() : undefined,
    cache: "no-store",
    redirect: "manual",
  });

  const responseHeaders = new Headers();
  for (const name of ["content-type", "content-disposition", "retry-after"]) {
    const value = upstream.headers.get(name);
    if (value) responseHeaders.set(name, value);
  }

  // Server errors never reach the browser verbatim: they could carry SQL,
  // file paths or stack traces from the API when debugging is switched on.
  if (upstream.status >= 500) {
    await upstream.body?.cancel();
    return NextResponse.json(
      { message: "The service is temporarily unavailable. Please try again." },
      { status: upstream.status === 503 ? 503 : 502 },
    );
  }

  const response = new NextResponse(upstream.status === 204 ? null : upstream.body, {
    status: upstream.status,
    headers: responseHeaders,
  });

  if (portal !== "public" && tokens) {
    if (upstream.status === 401) clearSession(response, portal);
    else if (refreshed) writeSession(response, portal, tokens);
  }

  return response;
}

export const GET = handle;
export const POST = handle;
export const PUT = handle;
export const PATCH = handle;
export const DELETE = handle;
