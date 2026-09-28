/*
 * NIS Residence Card Portal service worker.
 * - Makes the portal installable and shows a friendly page when offline.
 * - Displays push notifications (sent alongside the e-mails).
 * Personal data is never cached: only the offline page and icons are stored.
 */
// The folder the app is served from ("" on its own domain, "/nis-rcis" in a sub-folder).
const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, "");
const CACHE = "nis-rcis-v2";
const OFFLINE = BASE + "/offline.html";
const ICON = BASE + "/icons/icon-192.png";

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, ICON])));
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  );
});

// Page navigations go to the network; only when it fails is the offline page shown.
self.addEventListener("fetch", (event) => {
  if (event.request.url === new URL(ICON, self.location.origin).href) {
    event.respondWith(caches.match(ICON).then((hit) => hit || fetch(event.request)));
    return;
  }
  if (event.request.mode !== "navigate") return;
  event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});

self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : "" };
  }
  event.waitUntil(
    self.registration.showNotification(data.title || "Nigeria Immigration Service", {
      body: data.body || "",
      icon: ICON,
      badge: ICON,
      tag: data.tag || "nis-rcis",
      // The API sends app paths ("/portal/..."); add the folder.
      data: { url: BASE + (typeof data.url === "string" && data.url.startsWith("/") && !data.url.startsWith("//") ? data.url : "/portal") },
    }),
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = new URL(event.notification.data?.url || BASE + "/portal", self.location.origin).href;
  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((windows) => {
      for (const w of windows) {
        if (w.url.startsWith(self.location.origin) && "focus" in w) {
          w.navigate(url);
          return w.focus();
        }
      }
      return self.clients.openWindow(url);
    }),
  );
});
