export type Portal = "staff" | "applicant";

export const config = {
  appUrl: () => (typeof window !== "undefined" ? window.location.origin : ""),
  apiUrl: () => "/api",
  apiPublicUrl: () => "",
};

export function isPortal(value: string): value is Portal {
  return value === "staff" || value === "applicant";
}
