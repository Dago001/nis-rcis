import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffApplicationsPage from "@/staff/applications/page";

export default function Applications() {
  return (
    <StaffLayout>
      <StaffApplicationsPage />
    </StaffLayout>
  );
}
