/**
 * Sub-folder the app is served from, e.g. "/nis-rcis" on the cPanel test server;
 * empty on localhost and on its own domain. Set at build time (NEXT_PUBLIC_BASE_PATH).
 *
 * next/link and the router add it by themselves. Everything else that points at
 * this app by path needs withBase(): next/image src, plain <a href>, fetch(),
 * window.location, cookie paths and URLs built on the server.
 */
export const basePath = (process.env.NEXT_PUBLIC_BASE_PATH ?? "").replace(/\/$/, "");

export function withBase(path: string): string {
  return basePath + path;
}

/** The path inside the app, without the sub-folder (for returnTo values). */
export function withoutBase(path: string): string {
  return basePath && path.startsWith(basePath) ? path.slice(basePath.length) || "/" : path;
}
