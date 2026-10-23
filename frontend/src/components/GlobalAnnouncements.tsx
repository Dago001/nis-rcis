"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api-client";

interface AnnouncementData {
  broadcast?: {
    is_active?: boolean;
    title?: string;
    message?: string;
    tone?: "info" | "warning" | "danger" | "success";
    target?: string;
  };
  countdown?: {
    is_active?: boolean;
    title?: string;
    description?: string;
    target_date?: string;
    link_label?: string;
    link_url?: string;
  };
}

export function GlobalAnnouncements() {
  const [data, setData] = useState<AnnouncementData | null>(null);

  useEffect(() => {
    let mounted = true;
    api<AnnouncementData>("public", "announcements")
      .then((res) => {
        if (mounted && res) {
          setData(res);
        }
      })
      .catch(() => {
        // Silent catch for announcement polling
      });

    return () => {
      mounted = false;
    };
  }, []);

  if (!data) return null;

  const broadcast = data.broadcast;
  const countdown = data.countdown;

  const toneClasses = {
    info: "bg-blue-50 border-blue-200 text-blue-900",
    warning: "bg-amber-50 border-amber-200 text-amber-900",
    danger: "bg-red-50 border-red-200 text-red-900",
    success: "bg-emerald-50 border-emerald-200 text-emerald-900",
  }[broadcast?.tone || "info"];

  return (
    <div className="no-print space-y-2">
      {broadcast?.is_active && broadcast.message && (
        <div className={`border px-4 py-2.5 text-sm ${toneClasses}`}>
          <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
            <div>
              {broadcast.title && <span className="font-semibold mr-2">{broadcast.title}:</span>}
              <span>{broadcast.message}</span>
            </div>
          </div>
        </div>
      )}
      {countdown?.is_active && countdown.title && (
        <div className="border bg-slate-900 border-slate-800 text-white px-4 py-2.5 text-sm">
          <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
            <div>
              <span className="font-semibold mr-2">{countdown.title}</span>
              {countdown.description && <span className="text-slate-300 mr-2">{countdown.description}</span>}
              {countdown.target_date && (
                <span className="rounded bg-slate-800 px-2 py-0.5 text-xs text-amber-300 font-mono">
                  {new Date(countdown.target_date).toLocaleDateString("en-GB")}
                </span>
              )}
            </div>
            {countdown.link_url && (
              <a
                href={countdown.link_url}
                className="text-xs font-semibold text-emerald-400 hover:underline"
              >
                {countdown.link_label || "Learn more →"}
              </a>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default GlobalAnnouncements;
