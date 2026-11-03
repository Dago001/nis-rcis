import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffProfilePage from "@/staff/profile/page";

export default function Profile() {
  return (
    <StaffLayout>
      <StaffProfilePage />
    </StaffLayout>
  );
}
