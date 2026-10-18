import React from "react";
import { PortalLayout } from "@/components/portal/PortalLayout";
import CartPage from "@/portal/cart/page";

export default function Cart() {
  return (
    <PortalLayout>
      <CartPage />
    </PortalLayout>
  );
}
