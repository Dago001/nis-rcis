import type { MetadataRoute } from "next";

/** Installable applicant portal (home-screen app on phones and computers). */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "NIS Residence Card Portal",
    short_name: "NIS Residence",
    description: "Apply for, renew and track your Nigerian residence card.",
    id: "/portal",
    start_url: "/portal",
    scope: "/",
    display: "standalone",
    background_color: "#ffffff",
    theme_color: "#1f6b1f",
    lang: "en",
    icons: [
      { src: "/icons/icon-192.png", sizes: "192x192", type: "image/png" },
      { src: "/icons/icon-512.png", sizes: "512x512", type: "image/png" },
      { src: "/icons/maskable-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
    shortcuts: [
      { name: "My applications", url: "/portal" },
      { name: "Track an application", url: "/track" },
      { name: "Verify a card", url: "/verify" },
    ],
  };
}
