import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "NIS Residence Card Portal", template: "%s · NIS-RCIS" },
  description: "Nigeria Immigration Service — Residence Card Issuance System",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="flex min-h-full flex-col">{children}</body>
    </html>
  );
}
