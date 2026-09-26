import { NextResponse, type NextRequest } from "next/server";
import { config, isPortal } from "@/lib/config";
import { clearSession, readSession, sessionCookie } from "@/lib/session";

/**
 * Revoke the tokens at the API, clear the cookie, then end the
 * authorization-server session so the next sign-in asks for credentials.
 */
export async function POST(request: NextRequest, ctx: RouteContext<"/api/auth/logout/[portal]">) {
  const { portal } = await ctx.params;
  if (!isPortal(portal)) return new NextResponse("Not found", { status: 404 });

  const tokens = readSession(portal, request.cookies.get(sessionCookie(portal))?.value);
  if (tokens) {
    await fetch(`${config.apiUrl()}/api/v1/${portal}/oauth/revoke`, {
      method: "POST",
      headers: { Authorization: `Bearer ${tokens.accessToken}`, Accept: "application/json" },
      cache: "no-store",
    }).catch(() => undefined);
  }

  const logout = new URL(`${config.apiPublicUrl()}/logout`);
  logout.searchParams.set("redirect_uri", config.appUrl());

  const response = NextResponse.redirect(logout, 303);
  clearSession(response, portal);
  return response;
}
