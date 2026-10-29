"use client";

import { useEffect, useRef, useState, useImperativeHandle, forwardRef } from "react";

declare global {
  interface Window {
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

export const Turnstile = forwardRef<TurnstileRef, TurnstileProps>(function Turnstile(
  { siteKey: customSiteKey, action, onVerify, onExpire, onError, className },
  ref
) {
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetIdRef = useRef<string | null>(null);
  const [resolvedSiteKey, setResolvedSiteKey] = useState<string | null>(
    customSiteKey ||
    process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ||
    (typeof process !== "undefined" && (process.env?.TURNSTILE_SITE_KEY || process.env?.SITE_KEY)) ||
    null
  );

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

  // Fetch from /api/public-account/turnstile-key if not already provided via env
  useEffect(() => {
    if (resolvedSiteKey) return;

    let active = true;
    fetch("/api/public-account/turnstile-key")
      .then((res) => {
        if (!res.ok) return fetch("/api/turnstile-key");
        return res;
      })
      .then((res) => res.json())
      .then((data) => {
        if (active && data?.site_key) {
          setResolvedSiteKey(data.site_key);
        }
      })
      .catch(() => {});

    return () => {
      active = false;
    };
  }, [resolvedSiteKey]);

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
      }

      try {
        widgetIdRef.current = window.turnstile.render(containerRef.current, {
          sitekey: resolvedSiteKey!,
          action,
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
        script.onload = () => {
          renderWidget();
        };
        document.head.appendChild(script);
      } else {
        script.addEventListener("load", renderWidget);
      }
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

  if (!resolvedSiteKey) return null;

  return (
    <div className={className ?? "my-3 flex justify-center"}>
      <div ref={containerRef} />
    </div>
  );
});
