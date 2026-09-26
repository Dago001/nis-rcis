"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "@/lib/api-client";
import type { StaffRole, StaffUser } from "@/lib/types";
import { LogoutButton, SiteFooter, SiteHeader } from "./SiteHeader";
import { Spinner } from "./ui";

const StaffContext = createContext<StaffUser | null>(null);

export function useStaff(): StaffUser {
  const user = useContext(StaffContext);
  if (!user) throw new Error("useStaff must be used inside StaffShell");
  return user;
}

/** SuperAdmin holds every role (same rule as the API). */
export function hasRole(user: StaffUser, ...roles: StaffRole[]): boolean {
  return user.role === "SuperAdmin" || roles.includes(user.role);
}

const icon = (d: string) => (
  <svg className="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
    <path d={d} />
  </svg>
);

const nav: { href: string; label: string; icon: ReactNode; roles?: StaffRole[] }[] = [
  { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
  { href: "/staff/applications", label: "Approval queue", icon: icon("M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9") },
  { href: "/staff/payments", label: "Fee payments", icon: icon("M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM2 10h20M6 15h4") },
  { href: "/staff/cards", label: "Residence card register", icon: icon("M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM7 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 9h6M12 13h6M5 16h6") },
  { href: "/staff/applications/new", label: "Assisted application", icon: icon("M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6"), roles: ["SuperAdmin"] },
  { href: "/staff/reports", label: "Issuance reports", icon: icon("M3 3v18h18M7 15l4-4 3 3 6-6") },
  { href: "/staff/audit-logs", label: "Audit trail", icon: icon("M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"), roles: ["Auditor"] },
  { href: "/staff/users", label: "Staff accounts", icon: icon("M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"), roles: ["SuperAdmin"] },
];

export function StaffShell({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<StaffUser | null>(null);
  const [error, setError] = useState<string | null>(null);
  const pathname = usePathname();

  useEffect(() => {
    api<StaffUser>("staff", "me").then(setUser).catch((e) => setError(e.message));
  }, []);

  return (
    <>
      <SiteHeader
        right={
          user && (
            <div className="flex items-center gap-4">
              <div className="hidden text-right text-sm sm:block">
                <div className="font-medium text-slate-800">{user.fullname}</div>
                <div className="text-xs text-slate-500">{user.role_label} · {user.service_number}</div>
              </div>
              <LogoutButton portal="staff" />
            </div>
          )
        }
      />
      <div className="no-print relative overflow-hidden bg-nis-primary-dark">
        <div className="absolute inset-0 bg-[url('/images/hq-dusk.jpg')] bg-cover bg-center opacity-25" aria-hidden />
        <div className="relative mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-[5%] py-5 text-white xl:px-8">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/75">Directorate of Visa and Residency</p>
            <p className="text-lg font-medium">Staff Console</p>
          </div>
          {user && <p className="text-sm text-white/85">Welcome, <span className="font-semibold text-white">{user.fullname}</span></p>}
        </div>
      </div>
      <div className="staff-area mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-[5%] py-8 lg:flex-row xl:px-8">
        <nav className="no-print lg:w-64 lg:shrink-0" aria-label="Staff console">
          <ul className="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm lg:sticky lg:top-24 lg:flex-col">
            {user &&
              nav
                .filter((item) => !item.roles || hasRole(user, ...item.roles))
                .map((item) => {
                  const active =
                    item.href === "/staff"
                      ? pathname === "/staff"
                      : item.href === "/staff/applications"
                        ? pathname.startsWith(item.href) && !pathname.startsWith("/staff/applications/new")
                        : pathname.startsWith(item.href);
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        className={`flex items-center gap-3 whitespace-nowrap rounded-xl px-3 py-2.5 text-sm transition-colors ${active ? "bg-nis-primary font-medium text-white shadow-sm" : "text-slate-700 hover:bg-nis-mint hover:text-nis-primary"}`}
                      >
                        {item.icon}
                        {item.label}
                      </Link>
                    </li>
                  );
                })}
          </ul>
        </nav>
        <main className="min-w-0 flex-1">
          {error ? (
            <p className="text-sm text-red-700">{error}</p>
          ) : user ? (
            <StaffContext.Provider value={user}>{children}</StaffContext.Provider>
          ) : (
            <Spinner />
          )}
        </main>
      </div>
      <SiteFooter />
    </>
  );
}
