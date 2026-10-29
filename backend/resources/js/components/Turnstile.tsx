import React, { useEffect, useRef, useImperativeHandle, forwardRef } from "react";

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
  const siteKey = customSiteKey || (typeof process !== "undefined" ? process.env?.NEXT_PUBLIC_TURNSTILE_SITE_KEY : undefined);

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

  useEffect(() => {
    if (!siteKey || !containerRef.current) return;

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
          sitekey: siteKey!,
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
  }, [siteKey, action, onVerify, onExpire, onError]);

  if (!siteKey) return null;

  return (
    <div className={className ?? "my-3 flex justify-center"}>
      <div ref={containerRef} />
    </div>
  );
});
