import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffRefundsPage from "@/staff/refunds/page";

export default function Refunds() {
  return (
    <StaffLayout>
      <StaffRefundsPage />
    </StaffLayout>
  );
}
