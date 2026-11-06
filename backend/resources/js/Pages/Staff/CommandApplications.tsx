import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import CommandApplicationsPage from "@/staff/command-applications/page";

export default function CommandApplications() {
  return (
    <StaffLayout>
      <CommandApplicationsPage />
    </StaffLayout>
  );
}
