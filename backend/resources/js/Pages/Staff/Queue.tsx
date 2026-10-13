import React from "react";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffQueuePage from "@/staff/queue/page";

export default function Queue() {
  return (
    <StaffLayout>
      <StaffQueuePage />
    </StaffLayout>
  );
}
