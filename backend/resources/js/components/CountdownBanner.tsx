"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useI18n } from "@/components/I18nProvider";

export interface CountdownProps {
  isActive?: boolean;
  title?: string;
  targetDate?: string | null;
  description?: string | null;
  linkLabel?: string | null;
  linkUrl?: string | null;
  preview?: boolean;
}

export function CountdownBanner({
  isActive = false,
  title = "Scheduled Maintenance Window",
  targetDate,
  description,
  linkLabel,
  linkUrl,
  preview = false,
}: CountdownProps) {
  const { t } = useI18n();
  const [timeLeft, setTimeLeft] = useState<{
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
    expired: boolean;
  }>({ days: 0, hours: 0, minutes: 0, seconds: 0, expired: false });

  useEffect(() => {
    if (!targetDate) return;

    function calculate() {
      const target = new Date(targetDate!).getTime();
      const now = Date.now();
      const diff = target - now;

      if (isNaN(diff) || diff <= 0) {
        setTimeLeft({ days: 0, hours: 0, minutes: 0, seconds: 0, expired: true });
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);

      setTimeLeft({ days, hours, minutes, seconds, expired: false });
    }

    calculate();
    const interval = setInterval(calculate, 1000);
    return () => clearInterval(interval);
  }, [targetDate]);

  if (!isActive && !preview) return null;
  if (!targetDate && !preview) return null;
  if (timeLeft.expired && !preview) return null;

  return (
    <div className="relative overflow-hidden border-b border-amber-200 bg-gradient-to-r from-amber-50 via-orange-50 to-amber-50 px-4 py-4 sm:px-6 shadow-sm">
      <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 md:flex-row">
        {/* Left: Info */}
        <div className="flex items-center gap-3 text-center md:text-left">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
            <svg className="h-5 w-5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <div>
            <div className="flex flex-wrap items-center justify-center gap-2 md:justify-start">
              <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                {preview ? t("Preview") : t("Countdown Notice")}
              </span>
              <h4 className="text-sm font-bold text-slate-900 sm:text-base">{title}</h4>
            </div>
            {description && (
              <p className="mt-0.5 max-w-xl text-xs text-slate-600 sm:text-sm">{description}</p>
            )}
          </div>
        </div>

        {/* Center: Countdown Digits */}
        <div className="flex items-center gap-2 sm:gap-3">
          <div className="flex min-w-[54px] flex-col items-center rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 shadow-sm sm:min-w-[62px] sm:px-3 sm:py-2">
            <span className="font-mono text-lg font-extrabold text-amber-900 sm:text-2xl">
              {String(timeLeft.days).padStart(2, "0")}
            </span>
            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-500 sm:text-[10px]">
              {t("Days")}
            </span>
          </div>
          <span className="text-lg font-bold text-amber-600 sm:text-xl">:</span>
          <div className="flex min-w-[54px] flex-col items-center rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 shadow-sm sm:min-w-[62px] sm:px-3 sm:py-2">
            <span className="font-mono text-lg font-extrabold text-amber-900 sm:text-2xl">
              {String(timeLeft.hours).padStart(2, "0")}
            </span>
            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-500 sm:text-[10px]">
              {t("Hours")}
            </span>
          </div>
          <span className="text-lg font-bold text-amber-600 sm:text-xl">:</span>
          <div className="flex min-w-[54px] flex-col items-center rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 shadow-sm sm:min-w-[62px] sm:px-3 sm:py-2">
            <span className="font-mono text-lg font-extrabold text-amber-900 sm:text-2xl">
              {String(timeLeft.minutes).padStart(2, "0")}
            </span>
            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-500 sm:text-[10px]">
              {t("Mins")}
            </span>
          </div>
          <span className="text-lg font-bold text-amber-600 sm:text-xl">:</span>
          <div className="flex min-w-[54px] flex-col items-center rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 shadow-sm sm:min-w-[62px] sm:px-3 sm:py-2">
            <span className="font-mono text-lg font-extrabold text-amber-900 sm:text-2xl text-amber-600">
              {String(timeLeft.seconds).padStart(2, "0")}
            </span>
            <span className="text-[9px] font-bold uppercase tracking-wider text-slate-500 sm:text-[10px]">
              {t("Secs")}
            </span>
          </div>
        </div>

        {/* Right: Optional Link */}
        {linkUrl && linkLabel && (
          <div className="shrink-0">
            <Link
              href={linkUrl}
              className="inline-flex items-center gap-1 rounded-md bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700"
            >
              <span>{linkLabel}</span>
              <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="9 18 15 12 9 6" />
              </svg>
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}

export default CountdownBanner;
