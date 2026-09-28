"use client";

import { useCallback, useEffect, useState } from "react";
import { api, type ApiScope } from "./api-client";

/**
 * Fetch through the BFF and re-fetch whenever `path` changes.
 * Pass `null` to skip fetching. `loading` is derived (the result belongs to a different path), so no
 * state is reset synchronously inside the effect.
 */
export function useFetch<T>(scope: ApiScope, path: string | null) {
  const [state, setState] = useState<{ path: string; data?: T; error?: string } | null>(null);
  const [version, setVersion] = useState(0);

  useEffect(() => {
    if (path === null) return;
    let cancelled = false;
    api<T>(scope, path)
      .then((data) => !cancelled && setState({ path, data }))
      .catch((e: Error) => !cancelled && setState({ path, error: e.message }));
    return () => {
      cancelled = true;
    };
  }, [scope, path, version]);

  const reload = useCallback(() => setVersion((v) => v + 1), []);
  const current = path !== null && state?.path === path ? state : null;

  return { data: current?.data ?? null, error: current?.error ?? null, loading: path !== null && !current, reload };
}
