"use client";

import { usePathname } from "next/navigation";
import { SiteFooter } from "../SiteHeader";
import { PublicFooter } from "./PublicFooter";

/** Public information pages that carry the comprehensive footer. */
const FULL_FOOTER = ["/", "/about", "/faq", "/track", "/verify", "/contact", "/privacy"];

/** The comprehensive footer on the landing and information pages; the one-line footer elsewhere. */
export function FooterForPath() {
  return FULL_FOOTER.includes(usePathname()) ? <PublicFooter /> : <SiteFooter />;
}
