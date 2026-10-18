import React from "react";
import { PortalLayout } from "@/components/portal/PortalLayout";
import NotificationsPage from "@/portal/notifications/page";

export default function Notifications() {
  return (
    <PortalLayout>
      <NotificationsPage />
    </PortalLayout>
  );
}
