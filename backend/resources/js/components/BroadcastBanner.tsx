"use client";

import { useEffect, useState } from "react";
import { useI18n } from "@/components/I18nProvider";

export interface BroadcastProps {
  isActive?: boolean;
  title?: string;
  message?: string;
  tone?: "info" | "warning" | "alert" | "success";
  target?: string;
  expiresAt?: string | null;
  preview?: boolean;
}

const TONES = {
  info: {
    bg: "bg-blue-50 border-blue-200 text-blue-900",
    badge: "bg-blue-100 text-blue-800",
    icon: "text-blue-600",
    label: "Notice",
  },
  warning: {
    bg: "bg-amber-50 border-amber-200 text-amber-900",
    badge: "bg-amber-100 text-amber-800",
    icon: "text-amber-600",
    label: "Important",
  },
  alert: {
    bg: "bg-rose-50 border-rose-200 text-rose-900",
    badge: "bg-rose-100 text-rose-800",
    icon: "text-rose-600",
    label: "Alert",
  },
  success: {
    bg: "bg-emerald-50 border-emerald-200 text-emerald-900",
    badge: "bg-emerald-100 text-emerald-800",
    icon: "text-emerald-600",
    label: "Update",
  },
};

export function BroadcastBanner({
  isActive = false,
  title = "System Announcement",
  message = "",
  tone = "info",
  expiresAt,
  preview = false,
}: BroadcastProps) {
  const { t } = useI18n();
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (preview) return;
    try {
      if (sessionStorage.getItem(`nis_dismissed_broadcast_${title}`)) {
        setDismissed(true);
      }
    } catch {}
  }, [title, preview]);

  function handleDismiss() {
    try {
      sessionStorage.setItem(`nis_dismissed_broadcast_${title}`, "1");
    } catch {}
    setDismissed(true);
  }

  if (!isActive && !preview) return null;
  if (!message && !preview) return null;
  if (dismissed && !preview) return null;

  const currentTone = TONES[tone] || TONES.info;

  return (
    <div
      role="alert"
      className={`relative border-b px-4 py-3 sm:px-6 shadow-sm transition-all ${currentTone.bg}`}
    >
      <div className="mx-auto flex max-w-7xl items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className={`mt-0.5 shrink-0 ${currentTone.icon}`}>
            {tone === "alert" || tone === "warning" ? (
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            ) : (
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
            )}
          </div>
          <div className="text-sm">
            <div className="flex flex-wrap items-center gap-2">
              <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ${currentTone.badge}`}>
                {preview ? t("Preview") : t(currentTone.label)}
              </span>
              <strong className="font-semibold text-slate-900">{title}</strong>
            </div>
            <p className="mt-1 text-slate-700 leading-relaxed">{message}</p>
          </div>
        </div>

        {!preview && (
          <button
            type="button"
            onClick={handleDismiss}
            className="rounded p-1 text-slate-500 hover:bg-black/5 hover:text-slate-800"
            aria-label={t("Dismiss")}
          >
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        )}
      </div>
    </div>
  );
}

export default BroadcastBanner;
