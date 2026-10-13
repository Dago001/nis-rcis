import { NextResponse, type NextRequest } from "next/server";
import { config, isPortal, PORTAL_HOME } from "@/lib/config";
import { pkceChallenge, randomToken } from "@/lib/crypto";
import { safeReturnTo, writeTransaction } from "@/lib/session";

/**
 * Step 1 of the OAuth2 authorization-code + PKCE flow: send the browser to
 * the Laravel authorization server with a fresh state and code challenge.
 */
export async function GET(request: NextRequest, ctx: RouteContext<"/api/auth/login/[portal]">) {
  const { portal } = await ctx.params;
  if (!isPortal(portal)) return new NextResponse("Not found", { status: 404 });

  const client = config.client(portal);
  const state = randomToken();
  const verifier = randomToken(48);
  const returnTo = safeReturnTo(request.nextUrl.searchParams.get("returnTo"), PORTAL_HOME[portal]);

  const authorize = new URL(`${config.apiPublicUrl()}/oauth/authorize`);
  authorize.search = new URLSearchParams({
    client_id: client.id,
    redirect_uri: client.redirectUri,
    response_type: "code",
    scope: client.scope,
    state,
    code_challenge: pkceChallenge(verifier),
    code_challenge_method: "S256",
  }).toString();

  const response = NextResponse.redirect(authorize);
  writeTransaction(response, portal, { state, verifier, returnTo });
  return response;
}
