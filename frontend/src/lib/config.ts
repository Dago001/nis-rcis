import "server-only";

export type Portal = "staff" | "applicant";

function required(name: string): string {
  const value = process.env[name];
  if (!value) throw new Error(`Missing environment variable ${name}`);
  return value;
}

/**
 * Server-only configuration. Nothing here is exposed to the browser:
 * client secrets and OAuth tokens never leave the Next.js server.
 */
export const config = {
  /** Public URL of this Next.js app (used for OAuth redirect URIs). */
  appUrl: () => required("APP_URL").replace(/\/$/, ""),
  /** URL the Next.js server uses to reach the Laravel API (can be internal). */
  apiUrl: () => required("API_URL").replace(/\/$/, ""),
  /** URL the *browser* uses to reach the Laravel authorization server. */
  apiPublicUrl: () => (process.env.API_PUBLIC_URL || required("API_URL")).replace(/\/$/, ""),
  /** 32+ character secret used to encrypt the session cookies. */
  sessionSecret: () => {
    const secret = required("SESSION_SECRET");
    if (secret.length < 32) throw new Error("SESSION_SECRET must be at least 32 characters");
    return secret;
  },
  secureCookies: () => process.env.NODE_ENV === "production",
  client: (portal: Portal) => ({
    id: required(portal === "staff" ? "OAUTH_STAFF_CLIENT_ID" : "OAUTH_APPLICANT_CLIENT_ID"),
    secret: required(portal === "staff" ? "OAUTH_STAFF_CLIENT_SECRET" : "OAUTH_APPLICANT_CLIENT_SECRET"),
    scope: portal === "staff" ? "staff" : "applicant",
    redirectUri: `${config.appUrl()}/api/auth/callback/${portal}`,
  }),
};

export function isPortal(value: string): value is Portal {
  return value === "staff" || value === "applicant";
}

/** Where each portal lives in this app. */
export const PORTAL_HOME: Record<Portal, string> = {
  staff: "/staff",
  applicant: "/portal",
};
