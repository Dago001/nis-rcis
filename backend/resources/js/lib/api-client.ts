"use client";

export type ApiScope = "staff" | "applicant" | "public";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** First validation message per field, for inline form errors. */
  fieldErrors(): Record<string, string> {
    return Object.fromEntries(Object.entries(this.errors).map(([k, v]) => [k, v[0]]));
  }
}

function getCsrfToken(): string | null {
  if (typeof document === "undefined") return null;
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute("content") : null;
}

/**
 * Direct call to the Laravel API. Uses the session cookie and CSRF token.
 */
export async function api<T = unknown>(scope: ApiScope, path: string, init: RequestInit & { json?: unknown } = {}): Promise<T> {
  const { json, headers, ...rest } = init;
  const cleanPath = path.replace(/^\//, "");
  const url = cleanPath.startsWith("api/") ? `/${cleanPath}` : `/api/v1/${cleanPath}`;
  const csrf = getCsrfToken();

  const response = await fetch(url, {
    ...rest,
    headers: {
      Accept: "application/json",
      ...(json !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(csrf ? { "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest" } : { "X-Requested-With": "XMLHttpRequest" }),
      ...headers,
    },
    body: json !== undefined ? JSON.stringify(json) : rest.body,
    credentials: "same-origin",
  });

  if (response.status === 401 && scope !== "public") {
    const returnTo = window.location.pathname + window.location.search;
    window.location.href = scope === "staff" ? `/login/staff?returnTo=${encodeURIComponent(returnTo)}` : `/login/applicant?returnTo=${encodeURIComponent(returnTo)}`;
    throw new ApiError(401, "Your session has expired. Redirecting to sign in…");
  }

  if (response.status === 204) return undefined as T;

  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(response.status, body.message ?? `Request failed (${response.status})`, body.errors ?? {});
  }
  return body as T;
}

export function upload<T = unknown>(scope: ApiScope, path: string, fields: Record<string, string | Blob>): Promise<T> {
  const form = new FormData();
  for (const [key, value] of Object.entries(fields)) form.append(key, value);
  return api<T>(scope, path, { method: "POST", body: form });
}
