import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffSystemHealthPage from "@/staff/system/page";

export default function System() {
  return (
    <StaffLayout>
      <StaffSystemHealthPage />
    </StaffLayout>
  );
}
