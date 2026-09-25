"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "@/lib/api-client";
import type { StaffRole, StaffUser } from "@/lib/types";
import { LogoutButton, SiteHeader } from "./SiteHeader";
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

const nav: { href: string; label: string; roles?: StaffRole[] }[] = [
  { href: "/staff", label: "Dashboard" },
  { href: "/staff/applications", label: "Approval queue" },
  { href: "/staff/cards", label: "Residence cards" },
  { href: "/staff/applications/new", label: "Assisted application", roles: ["SuperAdmin"] },
  { href: "/staff/reports", label: "Reports" },
  { href: "/staff/audit-logs", label: "Audit trail", roles: ["Auditor"] },
  { href: "/staff/users", label: "Staff accounts", roles: ["SuperAdmin"] },
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
                <div className="font-semibold">{user.fullname}</div>
                <div className="text-xs text-white/75">{user.role_label} · {user.service_number}</div>
              </div>
              <LogoutButton portal="staff" />
            </div>
          )
        }
      />
      <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 lg:flex-row">
        <nav className="no-print lg:w-56 lg:shrink-0" aria-label="Staff console">
          <ul className="flex gap-1 overflow-x-auto lg:flex-col">
            {user &&
              nav
                .filter((item) => !item.roles || hasRole(user, ...item.roles))
                .map((item) => {
                  const active = item.href === "/staff" ? pathname === "/staff" : pathname.startsWith(item.href);
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        className={`block whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium ${active ? "bg-nis-green text-white" : "text-slate-700 hover:bg-white"}`}
                      >
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
    </>
  );
}
