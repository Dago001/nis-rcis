import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffAuditLogsPage from "@/staff/audit-logs/page";

export default function AuditLogs() {
  return (
    <StaffLayout>
      <StaffAuditLogsPage />
    </StaffLayout>
  );
}
