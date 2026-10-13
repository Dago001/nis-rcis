import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import PrintCardPage from "@/staff/cards/[id]/print/page";

export default function CardPrint() {
  return (
    <StaffLayout>
      <PrintCardPage />
    </StaffLayout>
  );
}
