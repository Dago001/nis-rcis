import "server-only";
import { config, type Portal } from "./config";
import type { TokenSet } from "./session";

type TokenResponse = {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  token_type: string;
};

function toTokenSet(data: TokenResponse): TokenSet {
  return {
    accessToken: data.access_token,
    refreshToken: data.refresh_token,
    expiresAt: Date.now() + data.expires_in * 1000,
  };
}

async function tokenRequest(body: Record<string, string>): Promise<TokenSet> {
  const response = await fetch(`${config.apiUrl()}/oauth/token`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  if (!response.ok) {
    const detail = await response.text();
    throw new Error(`OAuth token request failed (${response.status}): ${detail.slice(0, 300)}`);
  }

  return toTokenSet((await response.json()) as TokenResponse);
}

/** Authorization code + PKCE exchange (confidential client). */
export function exchangeCode(portal: Portal, code: string, verifier: string): Promise<TokenSet> {
  const client = config.client(portal);
  return tokenRequest({
    grant_type: "authorization_code",
    client_id: client.id,
    client_secret: client.secret,
    redirect_uri: client.redirectUri,
    code_verifier: verifier,
    code,
  });
}

export function refreshTokens(portal: Portal, refreshToken: string): Promise<TokenSet> {
  const client = config.client(portal);
  return tokenRequest({
    grant_type: "refresh_token",
    client_id: client.id,
    client_secret: client.secret,
    refresh_token: refreshToken,
    scope: client.scope,
  });
}

/** Refresh when the access token has less than a minute left. */
export function needsRefresh(tokens: TokenSet): boolean {
  return tokens.expiresAt - Date.now() < 60_000;
}
