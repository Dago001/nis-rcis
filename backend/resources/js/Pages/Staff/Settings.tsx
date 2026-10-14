import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffSettingsPage from "@/staff/settings/page";

export default function Settings() {
  return (
    <StaffLayout>
      <StaffSettingsPage />
    </StaffLayout>
  );
}
