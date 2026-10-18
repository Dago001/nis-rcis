import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffApprovalDetailPage from "@/staff/approvals/[id]/page";

export default function ApprovalDetail() {
  return (
    <StaffLayout>
      <StaffApprovalDetailPage />
    </StaffLayout>
  );
}
