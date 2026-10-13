import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffApprovalsPage from "@/staff/approvals/page";

export default function Approvals() {
  return (
    <StaffLayout>
      <StaffApprovalsPage />
    </StaffLayout>
  );
}
