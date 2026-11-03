import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffNotificationsPage from "@/staff/notifications/page";

export default function Notifications() {
  return (
    <StaffLayout>
      <StaffNotificationsPage />
    </StaffLayout>
  );
}
