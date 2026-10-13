import type { MetadataRoute } from "next";
import { withBase } from "@/lib/base-path";

/** Installable applicant portal (home-screen app on phones and computers). */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "NIS ECOWAS Residence Card Portal",
    short_name: "NIS Residence",
    description: "Apply for, renew and track your ECOWAS residence card in Nigeria.",
    id: withBase("/portal"),
    start_url: withBase("/portal"),
    scope: withBase("/"),
    display: "standalone",
    background_color: "#ffffff",
    theme_color: "#1f6b1f",
    lang: "en",
    icons: [
      { src: withBase("/icons/icon-192.png"), sizes: "192x192", type: "image/png" },
      { src: withBase("/icons/icon-512.png"), sizes: "512x512", type: "image/png" },
      { src: withBase("/icons/maskable-512.png"), sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
    shortcuts: [
      { name: "My applications", url: withBase("/portal") },
      { name: "Track an application", url: withBase("/track") },
      { name: "Verify a card", url: withBase("/verify") },
    ],
  };
}
