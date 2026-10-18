import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffCardIssuancePage from "@/staff/card-issuance/page";

export default function CardIssuance() {
  return (
    <StaffLayout>
      <StaffCardIssuancePage />
    </StaffLayout>
  );
}
