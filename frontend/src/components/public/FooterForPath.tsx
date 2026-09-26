"use client";

import { usePathname } from "next/navigation";
import { SiteFooter } from "../SiteHeader";
import { PublicFooter } from "./PublicFooter";

/** The comprehensive footer only on the landing page; the one-line footer elsewhere. */
export function FooterForPath() {
  return usePathname() === "/" ? <PublicFooter /> : <SiteFooter />;
}
