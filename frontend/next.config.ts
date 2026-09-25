import type { NextConfig } from "next";

const isProduction = process.env.NODE_ENV === "production";

/** Origins (scheme://host[:port]) of a list of URLs, ignoring blanks and bad values. */
function origins(...urls: (string | undefined)[]): string[] {
  return urls.flatMap((url) => {
    try {
      return url ? [new URL(url).origin] : [];
    } catch {
      return [];
    }
  });
}

// The browser talks to the Laravel server only for OAuth sign-in pages and
// short-lived signed document URLs (local disk or S3-compatible storage).
const apiOrigins = origins(process.env.API_PUBLIC_URL || process.env.API_URL, process.env.DOCUMENTS_PUBLIC_URL).join(" ");

// Next.js injects small inline bootstrap scripts, hence 'unsafe-inline' for
// scripts; everything else is locked to this site. Not applied in
// development, where hot reloading needs eval().
const contentSecurityPolicy = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  `img-src 'self' data: blob: ${apiOrigins}`,
  "font-src 'self'",
  // Link prefetches can follow the sign-in redirect to the authorization server.
  `connect-src 'self' ${apiOrigins}`,
  "media-src 'self' blob:",
  "object-src 'none'",
  "base-uri 'self'",
  `form-action 'self' ${apiOrigins}`,
  "frame-ancestors 'none'",
  ...(process.env.APP_URL?.startsWith("https://") ? ["upgrade-insecure-requests"] : []),
].join("; ");

const securityHeaders = [
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  // The biometrics desk needs the camera; nothing else does.
  { key: "Permissions-Policy", value: "camera=(self), microphone=(), geolocation=(), payment=()" },
  { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
  { key: "Strict-Transport-Security", value: "max-age=63072000; includeSubDomains" },
  ...(isProduction ? [{ key: "Content-Security-Policy", value: contentSecurityPolicy }] : []),
];

const nextConfig: NextConfig = {
  poweredByHeader: false,
  // The Next.js development "N" button is replaced by the help assistant.
  devIndicators: false,
  // Agent guidance lives in the repository root CLAUDE.md.
  agentRules: false,
  output: "standalone",
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default nextConfig;
