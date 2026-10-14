"use client";

import { usePage } from "@inertiajs/react";
import { BroadcastBanner } from "@/components/BroadcastBanner";
import { CountdownBanner } from "@/components/CountdownBanner";

export function GlobalAnnouncements() {
  try {
    const page = usePage();
    const props = (page?.props as any) || {};
    const announcement = props.announcement;
    const countdown = props.countdown;

    return (
      <div className="no-print">
        {announcement?.is_active && (
          <BroadcastBanner
            isActive={true}
            title={announcement.title}
            message={announcement.message}
            tone={announcement.tone}
            target={announcement.target}
          />
        )}
        {countdown?.is_active && (
          <CountdownBanner
            isActive={true}
            title={countdown.title}
            targetDate={countdown.target_date}
            description={countdown.description}
            linkLabel={countdown.link_label}
            linkUrl={countdown.link_url}
          />
        )}
      </div>
    );
  } catch {
    return null;
  }
}

export default GlobalAnnouncements;
