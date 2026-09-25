import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "NIS Residence Card Portal", template: "%s · NIS-RCIS" },
  description: "Nigeria Immigration Service — Residence Card Issuance System",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    // Browser extensions (Grammarly, screen recorders, …) add attributes to
    // <html> and <body> before React loads; don't report those as errors.
    <html lang="en" className="h-full antialiased" suppressHydrationWarning>
      <body className="flex min-h-full flex-col" suppressHydrationWarning>{children}</body>
    </html>
  );
}
