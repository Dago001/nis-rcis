import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffDashboardPage from "@/staff/page";

export default function Dashboard() {
  return (
    <StaffLayout>
      <StaffDashboardPage />
    </StaffLayout>
  );
}
