import type { ReactNode } from "react";

/** Page heading in the landing page style: small green eyebrow, large bold title. */
export function PageTitle({ title, subtitle, actions, eyebrow = "Staff console" }: { title: ReactNode; subtitle?: ReactNode; actions?: ReactNode; eyebrow?: string }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 pb-5">
      <div>
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-nis-primary">{eyebrow}</p>
        <h1 className="mt-1 text-2xl font-semibold text-slate-900">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-600">{subtitle}</p>}
      </div>
      {actions}
    </div>
  );
}
