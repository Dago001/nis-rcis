import React, { useEffect, useRef, useState, useImperativeHandle, forwardRef } from "react";

const DEFAULT_SITE_KEY = "0x4AAAAAAFpWM5olq43To2dX";

declare global {
  interface Window {
    __TURNSTILE_SITE_KEY__?: string;
    turnstile?: {
      render: (
        container: HTMLElement | string,
        options: {
          sitekey: string;
          action?: string;
          theme?: "light" | "dark" | "auto";
          size?: "normal" | "compact" | "flexible";
          callback?: (token: string) => void;
          "error-callback"?: (errorCode?: string) => void;
          "expired-callback"?: () => void;
        }
      ) => string;
      reset: (widgetId?: string) => void;
      remove: (widgetId?: string) => void;
    };
  }
}

export interface TurnstileRef {
  reset: () => void;
}

export interface TurnstileProps {
  siteKey?: string;
  action?: string;
  onVerify: (token: string) => void;
  onExpire?: () => void;
  onError?: (error?: string) => void;
  className?: string;
}

function getTurnstileKey(propKey?: string): string {
  if (propKey && typeof propKey === "string" && propKey.trim() !== "") {
    return propKey.trim();
  }
  if (typeof window !== "undefined" && window.__TURNSTILE_SITE_KEY__) {
    return window.__TURNSTILE_SITE_KEY__;
  }
  if (typeof document !== "undefined") {
    try {
      const pageEl = document.getElementById("app");
      if (pageEl?.dataset?.page) {
        const parsed = JSON.parse(pageEl.dataset.page);
        if (parsed?.props?.turnstile_site_key) {
          return parsed.props.turnstile_site_key;
        }
      }
    } catch {}
  }
  if (typeof process !== "undefined") {
    const envKey = process.env?.NEXT_PUBLIC_TURNSTILE_SITE_KEY || process.env?.TURNSTILE_SITE_KEY || process.env?.SITE_KEY;
    if (envKey) return envKey;
  }
  return DEFAULT_SITE_KEY;
}

export const Turnstile = forwardRef<TurnstileRef, TurnstileProps>(function Turnstile(
  { siteKey: customSiteKey, action, onVerify, onExpire, onError, className },
  ref
) {
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetIdRef = useRef<string | null>(null);
  const [resolvedSiteKey, setResolvedSiteKey] = useState<string>(() => getTurnstileKey(customSiteKey));

  useImperativeHandle(ref, () => ({
    reset: () => {
      if (widgetIdRef.current && window.turnstile) {
        try {
          window.turnstile.reset(widgetIdRef.current);
        } catch {
          // ignore
        }
      }
    },
  }));

  // Keep site key in sync if prop changes or window variable arrives
  useEffect(() => {
    const currentKey = getTurnstileKey(customSiteKey);
    if (currentKey && currentKey !== resolvedSiteKey) {
      setResolvedSiteKey(currentKey);
    }
  }, [customSiteKey, resolvedSiteKey]);

  useEffect(() => {
    if (!resolvedSiteKey || !containerRef.current) return;

    let isMounted = true;

    function renderWidget() {
      if (!isMounted || !containerRef.current || !window.turnstile) return;

      if (widgetIdRef.current) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch {
          // ignore
        }
        widgetIdRef.current = null;
      }

      try {
        widgetIdRef.current = window.turnstile.render(containerRef.current, {
          sitekey: resolvedSiteKey,
          action: action || undefined,
          theme: "light",
          callback: (token: string) => {
            if (isMounted) onVerify(token);
          },
          "expired-callback": () => {
            if (isMounted) onExpire?.();
          },
          "error-callback": (err?: string) => {
            if (isMounted) onError?.(err);
          },
        });
      } catch (e) {
        console.error("Turnstile render error", e);
      }
    }

    if (window.turnstile) {
      renderWidget();
    } else {
      const scriptId = "cloudflare-turnstile-api";
      let script = document.getElementById(scriptId) as HTMLScriptElement | null;
      if (!script) {
        script = document.createElement("script");
        script.id = scriptId;
        script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
      }

      const interval = setInterval(() => {
        if (!isMounted) {
          clearInterval(interval);
          return;
        }
        if (window.turnstile) {
          clearInterval(interval);
          renderWidget();
        }
      }, 50);

      const timeout = setTimeout(() => {
        clearInterval(interval);
      }, 10000);

      return () => {
        isMounted = false;
        clearInterval(interval);
        clearTimeout(timeout);
        if (widgetIdRef.current && window.turnstile) {
          try {
            window.turnstile.remove(widgetIdRef.current);
          } catch {}
          widgetIdRef.current = null;
        }
      };
    }

    return () => {
      isMounted = false;
      if (widgetIdRef.current && window.turnstile) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch {
          // ignore
        }
        widgetIdRef.current = null;
      }
    };
  }, [resolvedSiteKey, action, onVerify, onExpire, onError]);

  return (
    <div className={className ?? "my-3 flex justify-center"}>
      <div ref={containerRef} />
    </div>
  );
});
