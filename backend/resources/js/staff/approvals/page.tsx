"use client";

import { ApplicationsQueue } from "@/components/ApplicationsQueue";

export default function ApproversPage() {
  return (
    <ApplicationsQueue
      pageTitle="Approvers"
      pageSubtitle="Approving officer application review and decisions"
      basePath="/staff/approvals"
    />
  );
}
