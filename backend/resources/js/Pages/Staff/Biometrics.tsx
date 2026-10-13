import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffBiometricsPage from "@/staff/applications/[id]/biometrics/page";

export default function Biometrics() {
  return (
    <StaffLayout>
      <StaffBiometricsPage />
    </StaffLayout>
  );
}
