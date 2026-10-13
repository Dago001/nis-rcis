"use client";

import { useEffect } from "react";
import { withBase } from "@/lib/base-path";

/** Registers the service worker (installable portal, offline page, push notifications). */
export function ServiceWorker() {
  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;
    navigator.serviceWorker.register(withBase("/sw.js"), { scope: withBase("/") }).catch(() => undefined);
  }, []);
  return null;
}
