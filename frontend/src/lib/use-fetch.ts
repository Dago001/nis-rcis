"use client";

import { useCallback, useEffect, useState } from "react";
import { api, type ApiScope } from "./api-client";

const memoryCache = new Map<string, unknown>();

function getCached<T>(key: string): T | null {
  if (memoryCache.has(key)) {
    return memoryCache.get(key) as T;
  }
  if (typeof window !== "undefined") {
    try {
      const stored = sessionStorage.getItem(`nis_cache_${key}`);
      if (stored) {
        const parsed = JSON.parse(stored) as T;
        memoryCache.set(key, parsed);
        return parsed;
      }
    } catch {}
  }
  return null;
}

function setCached<T>(key: string, data: T): void {
  memoryCache.set(key, data);
  if (typeof window !== "undefined") {
    try {
      sessionStorage.setItem(`nis_cache_${key}`, JSON.stringify(data));
    } catch {}
  }
}

/**
 * Fetch through the BFF and re-fetch whenever `path` changes.
 * Uses Stale-While-Revalidate caching so screens render instantly.
 */
export function useFetch<T>(scope: ApiScope, path: string | null) {
  const cacheKey = path !== null ? `${scope}:${path}` : "";
  const [state, setState] = useState<{ path: string; data?: T; error?: string } | null>(() => {
    if (!cacheKey) return null;
    const cached = getCached<T>(cacheKey);
    return cached ? { path: cacheKey, data: cached } : null;
  });
  const [version, setVersion] = useState(0);

  useEffect(() => {
    if (path === null) return;
    let cancelled = false;
    const key = `${scope}:${path}`;

    api<T>(scope, path)
      .then((data) => {
        if (!cancelled) {
          setCached(key, data);
          setState({ path: key, data });
        }
      })
      .catch((e: Error) => {
        if (!cancelled) setState({ path: key, error: e.message });
      });

    return () => {
      cancelled = true;
    };
  }, [scope, path, version]);

  const reload = useCallback(() => setVersion((v) => v + 1), []);
  const current = path !== null && state?.path === `${scope}:${path}` ? state : null;
  const cachedFallback = path !== null && !current ? getCached<T>(`${scope}:${path}`) : null;
  const activeData = current?.data ?? cachedFallback ?? null;

  return {
    data: activeData,
    error: current?.error ?? null,
    loading: path !== null && activeData === null,
    reload,
  };
}

