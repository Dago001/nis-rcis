import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffPaymentDetailPage from "@/staff/payments/[id]/page";

export default function PaymentDetail() {
  return (
    <StaffLayout>
      <StaffPaymentDetailPage />
    </StaffLayout>
  );
}
