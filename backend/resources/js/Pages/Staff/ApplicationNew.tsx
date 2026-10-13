import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffNewApplicationPage from "@/staff/applications/new/page";

export default function ApplicationNew() {
  return (
    <StaffLayout>
      <StaffNewApplicationPage />
    </StaffLayout>
  );
}
