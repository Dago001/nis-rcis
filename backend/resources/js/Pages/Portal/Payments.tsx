import React from "react";
import { PortalLayout } from "@/components/portal/PortalLayout";
import PaymentsPage from "@/portal/payments/page";

export default function Payments() {
  return (
    <PortalLayout>
      <PaymentsPage />
    </PortalLayout>
  );
}
