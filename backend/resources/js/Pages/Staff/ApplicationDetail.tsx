import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffApplicationDetailPage from "@/staff/applications/[id]/page";

export default function ApplicationDetail() {
  return (
    <StaffLayout>
      <StaffApplicationDetailPage />
    </StaffLayout>
  );
}
