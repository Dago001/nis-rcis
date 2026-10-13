import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffPaymentsPage from "@/staff/payments/page";

export default function Payments() {
  return (
    <StaffLayout>
      <StaffPaymentsPage />
    </StaffLayout>
  );
}
