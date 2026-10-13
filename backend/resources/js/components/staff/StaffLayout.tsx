import { ReactNode } from "react";
import { StaffShell } from "@/components/StaffShell";

export function StaffLayout({ children }: { children: ReactNode }) {
  return <StaffShell>{children}</StaffShell>;
}

export default StaffLayout;
