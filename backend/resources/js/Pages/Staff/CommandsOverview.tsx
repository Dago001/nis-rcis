import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import CommandsOverviewPage from "@/staff/commands/page";

export default function CommandsOverview() {
  return (
    <StaffLayout>
      <CommandsOverviewPage />
    </StaffLayout>
  );
}
