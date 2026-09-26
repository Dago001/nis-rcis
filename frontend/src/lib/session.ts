import "server-only";
import type { NextResponse } from "next/server";
import { config, type Portal } from "./config";
import { seal, unseal } from "./crypto";

/**
 * OAuth2 tokens are kept in an encrypted, httpOnly cookie per portal.
 * Browser JavaScript can never read them; every API call goes through the
 * BFF route handler (/api/bff/...) which attaches the bearer token.
 */
export type TokenSet = {
  accessToken: string;
  refreshToken: string;
  expiresAt: number; // epoch ms
};

export type OAuthTransaction = {
  state: string;
  verifier: string;
  returnTo: string;
};

export const sessionCookie = (portal: Portal) => `nis_${portal}_session`;
export const transactionCookie = (portal: Portal) => `nis_${portal}_oauth`;

const SESSION_MAX_AGE = 12 * 60 * 60; // matches the refresh token lifetime

export function readSession(portal: Portal, raw: string | undefined): TokenSet | null {
  return unseal<TokenSet>(raw);
}

export function writeSession(response: NextResponse, portal: Portal, tokens: TokenSet): void {
  response.cookies.set(sessionCookie(portal), seal(tokens), {
    httpOnly: true,
    secure: config.secureCookies(),
    sameSite: "lax",
    path: "/",
    maxAge: SESSION_MAX_AGE,
  });
}

export function clearSession(response: NextResponse, portal: Portal): void {
  response.cookies.set(sessionCookie(portal), "", { httpOnly: true, path: "/", maxAge: 0 });
}

export function writeTransaction(response: NextResponse, portal: Portal, tx: OAuthTransaction): void {
  response.cookies.set(transactionCookie(portal), seal(tx), {
    httpOnly: true,
    secure: config.secureCookies(),
    sameSite: "lax",
    path: `/api/auth/callback/${portal}`,
    maxAge: 600,
  });
}

export function readTransaction(raw: string | undefined): OAuthTransaction | null {
  return unseal<OAuthTransaction>(raw);
}

/** Only allow same-app relative paths as post-login destinations. */
export function safeReturnTo(value: string | null | undefined, fallback: string): string {
  if (!value || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) return fallback;
  return value;
}
