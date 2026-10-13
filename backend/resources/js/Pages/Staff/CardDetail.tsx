import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffCardDetailPage from "@/staff/cards/[id]/page";

export default function CardDetail() {
  return (
    <StaffLayout>
      <StaffCardDetailPage />
    </StaffLayout>
  );
}
