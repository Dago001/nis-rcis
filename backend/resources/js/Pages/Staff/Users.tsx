import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffUsersPage from "@/staff/users/page";

export default function Users() {
  return (
    <StaffLayout>
      <StaffUsersPage />
    </StaffLayout>
  );
}
