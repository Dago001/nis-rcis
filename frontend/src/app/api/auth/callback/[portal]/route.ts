import { timingSafeEqual } from "node:crypto";
import { NextResponse, type NextRequest } from "next/server";
import { config, isPortal } from "@/lib/config";
import { exchangeCode } from "@/lib/oauth";
import { readTransaction, transactionCookie, writeSession } from "@/lib/session";

/**
 * Step 2: validate state, exchange the code (client secret + PKCE verifier)
 * server-side, and store the tokens in an encrypted httpOnly cookie.
 */
export async function GET(request: NextRequest, ctx: RouteContext<"/api/auth/callback/[portal]">) {
  const { portal } = await ctx.params;
  if (!isPortal(portal)) return new NextResponse("Not found", { status: 404 });

  const params = request.nextUrl.searchParams;
  const tx = readTransaction(request.cookies.get(transactionCookie(portal))?.value);
  const fail = (reason: string) =>
    NextResponse.redirect(new URL(`/auth-error?reason=${encodeURIComponent(reason)}`, config.appUrl()));

  if (params.get("error")) return fail(params.get("error_description") || params.get("error")!);
  if (!tx) return fail("Your sign-in session expired. Please try again.");

  const state = params.get("state") ?? "";
  const valid = state.length === tx.state.length && timingSafeEqual(Buffer.from(state), Buffer.from(tx.state));
  const code = params.get("code");
  if (!valid || !code) return fail("Invalid sign-in response.");

  try {
    const tokens = await exchangeCode(portal, code, tx.verifier);
    const response = NextResponse.redirect(new URL(tx.returnTo, config.appUrl()));
    writeSession(response, portal, tokens);
    response.cookies.set(transactionCookie(portal), "", { path: `/api/auth/callback/${portal}`, maxAge: 0 });
    return response;
  } catch (error) {
    console.error("OAuth code exchange failed", error);
    return fail("Sign-in could not be completed.");
  }
}
