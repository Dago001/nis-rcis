import React from "react";
import { PortalLayout } from "@/components/portal/PortalLayout";
import ApplicationDetailPage from "@/portal/applications/[id]/page";

export default function ApplicationDetail() {
  return (
    <PortalLayout>
      <ApplicationDetailPage />
    </PortalLayout>
  );
}
