const styles: Record<string, string> = {
  PENDING_APPROVAL: "bg-amber-100 text-amber-900",
  QUERIED: "bg-orange-100 text-orange-900",
  REJECTED: "bg-red-100 text-red-800",
  APPROVED_FOR_BIOMETRICS: "bg-sky-100 text-sky-900",
  BIOMETRICS_CAPTURED: "bg-indigo-100 text-indigo-900",
  READY_FOR_COLLECTION: "bg-emerald-100 text-emerald-900",
  ISSUED: "bg-nis-green-light text-nis-green-dark",
  APPROVED: "bg-sky-100 text-sky-900",
  RENEWED: "bg-teal-100 text-teal-900",
  REVOKED: "bg-red-100 text-red-800",
  REPORTED_LOST: "bg-red-100 text-red-800",
  REPORTED_STOLEN: "bg-red-100 text-red-800",
  REFUNDED: "bg-slate-200 text-slate-800",
  VALID: "bg-emerald-100 text-emerald-900",
  EXPIRED: "bg-slate-200 text-slate-800",
  WATCHLISTED: "bg-red-600 text-white",
  REFER_TO_NIS: "bg-red-600 text-white",
  NOT_ISSUED: "bg-slate-200 text-slate-800",
};

export function StatusBadge({ status, label }: { status: string; label?: string }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${styles[status] ?? "bg-slate-100 text-slate-700"}`}>
      {label ?? status.replaceAll("_", " ")}
    </span>
  );
}
