"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import { api } from "@/lib/api-client";
import type { StaffRole, StaffUser } from "@/lib/types";
import { SiteFooter, SiteHeader } from "./SiteHeader";
import { GlobalAnnouncements } from "./GlobalAnnouncements";
import { Spinner } from "./ui";
import { useI18n } from "./I18nProvider";

const StaffContext = createContext<StaffUser | null>(null);

export function useStaff(): StaffUser {
  const user = useContext(StaffContext);
  if (!user) throw new Error("useStaff must be used inside StaffShell");
  return user;
}

/** SuperAdmin holds every role. Admin and Supervisor also have broad operational oversight. */
export function hasRole(user: StaffUser, ...roles: StaffRole[]): boolean {
  if (user.role === "SuperAdmin") return true;
  if (user.role === "Admin" && !roles.includes("SuperAdmin")) return true;
  return roles.includes(user.role);
}

const icon = (d: string) => (
  <svg className="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
    <path d={d} />
  </svg>
);

interface NavItem {
  href: string;
  label: string;
  icon: ReactNode;
  roles?: StaffRole[];
}

interface NavSection {
  title: string;
  items: NavItem[];
}

const navSections: NavSection[] = [
  {
    title: "Overview",
    items: [
      { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
      { href: "/staff/queue", label: "Centre queue", icon: icon("M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"), roles: ["EnrollmentOfficer", "ApprovingOfficer", "IssuingOfficer", "Supervisor", "Admin"] },
    ],
  },
  {
    title: "Applications",
    items: [
      { href: "/staff/applications", label: "Vetting", icon: icon("M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9") },
      { href: "/staff/approvals", label: "Approvers", icon: icon("M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2z"), roles: ["ApprovingOfficer", "IssuingOfficer", "Supervisor", "Admin"] },
    ],
  },
  {
    title: "Residence Cards",
    items: [
      { href: "/staff/cards", label: "Card Production", icon: icon("M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM7 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 9h6M12 13h6M5 16h6"), roles: ["ProductionOfficer", "IssuingOfficer", "ApprovingOfficer", "Supervisor", "Admin"] },
      { href: "/staff/card-issuance", label: "Card Issuance", icon: icon("M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"), roles: ["IssuingOfficer", "ApprovingOfficer", "ProductionOfficer", "Supervisor", "Admin"] },
      { href: "/staff/card-stock", label: "Stock & printing", icon: icon("M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"), roles: ["ProductionOfficer", "IssuingOfficer", "ApprovingOfficer", "Supervisor", "Admin"] },
    ],
  },
  {
    title: "Financials",
    items: [
      { href: "/staff/payments", label: "Fee payments", icon: icon("M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM2 10h20M6 15h4") },
    ],
  },
  {
    title: "Reports & Compliance",
    items: [
      { href: "/staff/reports", label: "Issuance reports", icon: icon("M3 3v18h18M7 15l4-4 3 3 6-6"), roles: ["SuperAdmin", "Admin", "Supervisor", "Auditor", "ApprovingOfficer", "ProductionOfficer", "IssuingOfficer"] },
      { href: "/staff/audit-logs", label: "Audit trail", icon: icon("M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"), roles: ["Auditor", "Supervisor", "Admin", "SuperAdmin"] },
      { href: "/staff/data-breaches", label: "Data breach register", icon: icon("M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"), roles: ["Auditor", "Supervisor", "Admin", "SuperAdmin"] },
    ],
  },
  {
    title: "Administration",
    items: [
      { href: "/staff/users", label: "Staff accounts", icon: icon("M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"), roles: ["SuperAdmin", "Admin"] },
      { href: "/staff/system", label: "System health", icon: icon("M22 12h-4l-3 9L9 3l-3 9H2"), roles: ["SuperAdmin", "Admin"] },
      { href: "/staff/settings", label: "Settings", icon: icon("M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"), roles: ["SuperAdmin", "Admin"] },
    ],
  },
];

export function StaffShell({ children }: { children: ReactNode }) {
  const { t } = useI18n();
  const [user, setUser] = useState<StaffUser | null>(() => {
    if (typeof window === "undefined") return null;
    try {
      const cached = sessionStorage.getItem("nis_staff_user");
      return cached ? JSON.parse(cached) : null;
    } catch {
      return null;
    }
  });
  const [error, setError] = useState<string | null>(null);
  const pathname = usePathname();
  const area = useRef<HTMLDivElement>(null);
  const menu = useRef<HTMLDivElement>(null);
  const firstPath = useRef(pathname);

  useEffect(() => {
    api<StaffUser>("staff", "me")
      .then((data) => {
        setUser(data);
        try {
          sessionStorage.setItem("nis_staff_user", JSON.stringify(data));
        } catch {}
      })
      .catch((e) => {
        if (!user) setError(e.message);
      });
  }, []);

  // Sidebar links don't reset the window scroll (the menu stays exactly where it
  // is); if the page was scrolled past the top of the content, bring the new
  // page's top up to just under the header instead of jumping to the very top.
  useEffect(() => {
    if (pathname === firstPath.current || !area.current || !menu.current) return;
    firstPath.current = pathname;
    const header = document.querySelector("header")?.offsetHeight ?? 0;
    const sticky = getComputedStyle(menu.current).position === "sticky" ? parseFloat(getComputedStyle(menu.current).top) : header + 8;
    const target = area.current.getBoundingClientRect().top + window.scrollY + parseFloat(getComputedStyle(area.current).paddingTop) - sticky;
    if (window.scrollY > target) window.scrollTo({ top: target });
  }, [pathname]);

  // Keep the current page's menu item in view inside the menu's own scroll area.
  useEffect(() => {
    const list = menu.current;
    const item = list?.querySelector<HTMLElement>("[aria-current=page]");
    if (!list || !item) return;
    const vertical = list.scrollHeight > list.clientHeight;
    if (vertical && (item.offsetTop < list.scrollTop || item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight)) {
      list.scrollTop = item.offsetTop - list.clientHeight / 2 + item.offsetHeight / 2;
    } else if (!vertical && (item.offsetLeft < list.scrollLeft || item.offsetLeft + item.offsetWidth > list.scrollLeft + list.clientWidth)) {
      list.scrollLeft = item.offsetLeft - list.clientWidth / 2 + item.offsetWidth / 2;
    }
  }, [pathname, user]);

  return (
    <>
      <SiteHeader
        languages
        portal="staff"
        user={
          user
            ? {
                name: user.fullname,
                role: `${user.role === "SuperAdmin" || user.role_label === "Super Administrator" ? "Super Admin" : user.role_label} · ${user.service_number}`,
              }
            : undefined
        }
      />
      <GlobalAnnouncements />
      <div className="w-full flex-1 bg-[#f4f7f5]">
        <div ref={area} className="staff-area mx-auto flex w-full flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row lg:gap-6 lg:px-4 xl:px-6">
        <nav className="no-print lg:w-56 xl:w-[14.5rem] lg:shrink-0" aria-label="Staff console">
          <div
            ref={menu}
            className="relative flex gap-1 overflow-x-auto rounded-2xl border border-[#cde5d5] bg-[#EAF5EE] p-2.5 shadow-sm lg:sticky lg:top-24 lg:max-h-[calc(100vh-7.5rem)] lg:flex-col lg:gap-3 lg:overflow-y-auto lg:overflow-x-hidden lg:overscroll-contain"
          >
            {user &&
              navSections.map((sec, idx) => {
                const visible = sec.items.filter((item) => !item.roles || hasRole(user, ...item.roles));
                if (visible.length === 0) return null;
                return (
                  <div key={sec.title} className={idx > 0 ? "lg:border-t lg:border-[#d7ece0] lg:pt-3" : ""}>
                    <div className="hidden px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 lg:block">
                      {t(sec.title)}
                    </div>
                    <ul className="flex gap-1 lg:flex-col">
                      {visible.map((item) => {
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
                              scroll={false}
                              prefetch={false}
                              aria-current={active ? "page" : undefined}
                              className={`flex items-center gap-2.5 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition-colors ${
                                active
                                  ? "bg-nis-primary font-medium text-white shadow-sm"
                                  : "text-slate-800 hover:bg-white/70 hover:text-nis-primary"
                              }`}
                            >
                              {item.icon}
                              <span>{t(item.label)}</span>
                            </Link>
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                );
              })}
          </div>
        </nav>
        <main id="main" tabIndex={-1} className="min-w-0 flex-1">
          {error ? (
            <p className="text-sm text-red-700">{error}</p>
          ) : user ? (
            <StaffContext.Provider value={user}>{children}</StaffContext.Provider>
          ) : (
            <Spinner />
          )}
        </main>
      </div>
      </div>
      <SiteFooter />
    </>
  );
}
