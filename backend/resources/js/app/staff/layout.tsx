import { StaffShell } from "@/components/StaffShell";

export const metadata = { title: "Staff console" };

export default function StaffLayout({ children }: LayoutProps<"/staff">) {
  return <StaffShell>{children}</StaffShell>;
}
