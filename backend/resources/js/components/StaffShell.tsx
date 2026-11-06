"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { createContext, useContext, useEffect, useRef, useState, Suspense, type ReactNode } from "react";
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
  if ((user.role === "Admin" || user.role === "HeadquartersAdmin") && !roles.includes("SuperAdmin")) return true;
  if (roles.includes(user.role)) return true;

  // Role aliases & hierarchy
  if (roles.includes("VettingOfficer") && (user.role === "Inspector" || user.role === "DataEntryOfficer")) return true;
  if (roles.includes("BiometricsOfficer") && user.role === "EnrollmentOfficer") return true;
  if (roles.includes("EnrollmentOfficer") && user.role === "BiometricsOfficer") return true;
  if (roles.includes("CardProductionOfficer") && user.role === "ProductionOfficer") return true;
  if (roles.includes("ProductionOfficer") && user.role === "CardProductionOfficer") return true;
  if (roles.includes("CardIssuanceOfficer") && (user.role === "IssuingOfficer" || (user.role as string) === "IssuanceOfficer")) return true;
  if (roles.includes("IssuingOfficer") && (user.role === "CardIssuanceOfficer" || user.role === "CardReceivingOfficer" || (user.role as string) === "IssuanceOfficer")) return true;
  if (roles.includes("ReportingAuditOfficer") && user.role === "Auditor") return true;
  if (roles.includes("Auditor") && user.role === "ReportingAuditOfficer") return true;

  return false;
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

function getRoleNavSections(user: StaffUser): NavSection[] {
  const role = user.role;

  // 1. Dedicated Vetting Officer Sidebar (Prompt Section 1)
  if (role === "VettingOfficer" || role === "Inspector" || role === "DataEntryOfficer") {
    return [
      {
        title: "Overview",
        items: [
          { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
        ],
      },
      {
        title: "Applications",
        items: [
          { href: "/staff/applications", label: "Vetting", icon: icon("M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9") },
          { href: "/staff/applications?assigned=me", label: "My Assigned Applications", icon: icon("M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM20 8v6M23 11h-6") },
          { href: "/staff/applications?filter=vetted_by_me", label: "Applications Vetted by Me", icon: icon("M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z") },
          { href: "/staff/applications?status=QUERIED", label: "Queried Applications", icon: icon("M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z") },
        ],
      },
      {
        title: "Workspace",
        items: [
          { href: "/staff/notifications", label: "Notifications", icon: icon("M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0") },
          { href: "/staff/my-activity", label: "My Activity", icon: icon("M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z") },
        ],
      },
      {
        title: "Account",
        items: [
          { href: "/staff/profile", label: "My Profile", icon: icon("M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z") },
        ],
      },
    ];
  }

  // 2. Dedicated Approving Officer Sidebar (Prompt Section 2)
  if (role === "ApprovingOfficer" || role === "Supervisor") {
    return [
      {
        title: "Overview",
        items: [
          { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
        ],
      },
      {
        title: "Applications",
        items: [
          { href: "/staff/applications?view=vetting_records", label: "Vetting Records", icon: icon("M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8") },
          { href: "/staff/approvals", label: "Approval Queue", icon: icon("M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2z") },
          { href: "/staff/approvals?filter=approved_by_me", label: "Approved by Me", icon: icon("M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z") },
          { href: "/staff/approvals?status=QUERIED", label: "Queried Applications", icon: icon("M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z") },
        ],
      },
      {
        title: "Workspace",
        items: [
          { href: "/staff/notifications", label: "Notifications", icon: icon("M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0") },
          { href: "/staff/my-activity", label: "My Activity", icon: icon("M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z") },
        ],
      },
      {
        title: "Account",
        items: [
          { href: "/staff/profile", label: "My Profile", icon: icon("M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z") },
        ],
      },
    ];
  }

  // 3. Dedicated Issuance Officer Sidebar (Prompt Section 5)
  if (role === "CardIssuanceOfficer" || role === "IssuingOfficer" || (role as string) === "IssuanceOfficer") {
    return [
      {
        title: "Overview",
        items: [
          { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
        ],
      },
      {
        title: "Residence Cards",
        items: [
          { href: "/staff/card-issuance", label: "Card Issuance", icon: icon("M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z") },
        ],
      },
      {
        title: "Workspace",
        items: [
          { href: "/staff/notifications", label: "Notifications", icon: icon("M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0") },
          { href: "/staff/my-activity", label: "My Activity", icon: icon("M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z") },
        ],
      },
      {
        title: "Account",
        items: [
          { href: "/staff/profile", label: "My Profile", icon: icon("M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z") },
        ],
      },
    ];
  }

  // 4. General & Administrative Navigation (SuperAdmin, HeadquartersAdmin, CommandAdmin, ZonalAdmin, etc.)
  return [
    {
      title: "Overview",
      items: [
        { href: "/staff", label: "Dashboard", icon: icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z") },
        {
          href: "/staff/national-operations",
          label: "National Operations",
          icon: icon("M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin"],
        },
      ],
    },
    {
      title: "Application Management",
      items: [
        {
          href: "/staff/applications",
          label: "Vetting",
          icon: icon("M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "VettingOfficer", "ApprovingOfficer", "Inspector", "DataEntryOfficer"],
        },
        {
          href: "/staff/approvals",
          label: "Approval",
          icon: icon("M9 12l2 2 4-4M7 3h10a2 2 0 0 1 2 2v14l-7-3-7 3V5a2 2 0 0 1 2-2z"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "ApprovingOfficer", "Supervisor"],
        },
        {
          href: "/staff/queue",
          label: "Biometrics",
          icon: icon("M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "BiometricsOfficer", "EnrollmentOfficer"],
        },
      ],
    },
    {
      title: "Residence Cards",
      items: [
        {
          href: "/staff/cards",
          label: "Card Production",
          icon: icon("M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM7 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 9h6M12 13h6M5 16h6"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "CardProductionOfficer", "ProductionOfficer"],
        },
        {
          href: "/staff/card-distribution",
          label: "Card Distribution",
          icon: icon("M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2zM3 13h18"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "CardDistributionOfficer", "CardReceivingOfficer"],
        },
        {
          href: "/staff/card-issuance",
          label: "Card Issuance",
          icon: icon("M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "CardIssuanceOfficer", "IssuingOfficer"],
        },
        {
          href: "/staff/card-stock",
          label: "Stock & printing",
          icon: icon("M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "CardProductionOfficer", "ProductionOfficer"],
        },
      ],
    },
    {
      title: "Management",
      items: [
        {
          href: "/staff/commands",
          label: "Commands Overview",
          icon: icon("M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "ReportingAuditOfficer", "Auditor"],
        },
        {
          href: "/staff/reports",
          label: "Reports",
          icon: icon("M3 3v18h18M7 15l4-4 3 3 6-6"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin", "ReportingAuditOfficer", "Auditor"],
        },
        {
          href: "/staff/audit-logs",
          label: "Audit trail",
          icon: icon("M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ReportingAuditOfficer", "Auditor"],
        },
      ],
    },
    {
      title: "Administration",
      items: [
        {
          href: "/staff/users",
          label: "Staff accounts",
          icon: icon("M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"),
          roles: ["SuperAdmin", "Admin", "HeadquartersAdmin", "ZonalAdmin", "CommandAdmin"],
        },
        {
          href: "/staff/settings",
          label: "Settings",
          icon: icon("M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"),
          roles: ["SuperAdmin", "Admin"],
        },
      ],
    },
    {
      title: "Account",
      items: [
        { href: "/staff/notifications", label: "Notifications", icon: icon("M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0") },
        { href: "/staff/my-activity", label: "My Activity", icon: icon("M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z") },
        { href: "/staff/profile", label: "My Profile", icon: icon("M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z") },
      ],
    },
  ];
}

function StaffShellInner({ children }: { children: ReactNode }) {
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
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const pathname = usePathname();
  const searchParams = useSearchParams();
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

  // Close mobile drawer on route changes
  useEffect(() => {
    setMobileNavOpen(false);
  }, [pathname, searchParams]);

  // Handle escape key and lock body scroll when mobile drawer is open
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setMobileNavOpen(false);
    };
    if (mobileNavOpen) {
      document.addEventListener("keydown", onKey);
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "";
    }
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [mobileNavOpen]);

  useEffect(() => {
    if (pathname === firstPath.current || !area.current || !menu.current) return;
    firstPath.current = pathname;
    const header = document.querySelector("header")?.offsetHeight ?? 0;
    const sticky = getComputedStyle(menu.current).position === "sticky" ? parseFloat(getComputedStyle(menu.current).top) : header + 8;
    const target = area.current.getBoundingClientRect().top + window.scrollY + parseFloat(getComputedStyle(area.current).paddingTop) - sticky;
    if (window.scrollY > target) window.scrollTo({ top: target });
  }, [pathname]);

  useEffect(() => {
    const list = menu.current;
    const item = list?.querySelector<HTMLElement>("[aria-current=page]");
    if (!list || !item) return;
    const vertical = list.scrollHeight > list.clientHeight;
    if (vertical && (item.offsetTop < list.scrollTop || item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight)) {
      list.scrollTop = item.offsetTop - list.clientHeight / 2 + item.offsetHeight / 2;
    }
  }, [pathname, searchParams, user]);

  const isItemActive = (itemHref: string) => {
    if (itemHref === "/staff") {
      return pathname === "/staff";
    }

    if (itemHref.includes("?")) {
      const [itemPath, itemQuery] = itemHref.split("?");
      if (pathname !== itemPath) return false;
      const targetParams = new URLSearchParams(itemQuery);
      for (const [key, val] of targetParams.entries()) {
        if (searchParams.get(key) !== val) return false;
      }
      return true;
    }

    if (pathname === itemHref) {
      // If current search has specific sub-filters, don't keep base queue item active
      if (
        searchParams.has("assigned") ||
        searchParams.has("filter") ||
        searchParams.has("view") ||
        (searchParams.has("status") && searchParams.get("status") === "QUERIED")
      ) {
        return false;
      }
      return true;
    }

    return pathname.startsWith(itemHref);
  };

  const activeNavSections = user ? getRoleNavSections(user) : [];

  const currentActiveItem = user
    ? activeNavSections
        .flatMap((s) => s.items)
        .find((it) => isItemActive(it.href))
    : null;

  const restrictedNav = user
    ? activeNavSections
        .flatMap((s) => s.items)
        .find((it) => it.roles && isItemActive(it.href) && !hasRole(user, ...it.roles))
    : null;

  const roleLabelDisplay = user
    ? user.role === "SuperAdmin" || user.role_label === "Super Administrator"
      ? "Super Admin"
      : user.role === "CardIssuanceOfficer" || user.role === "IssuingOfficer"
      ? "Issuance Officer"
      : user.role_label || user.role
    : undefined;

  return (
    <>
      <SiteHeader
        languages
        portal="staff"
        user={
          user
            ? {
                name: user.rank ? `${user.rank} ${user.fullname}` : user.fullname,
                role: roleLabelDisplay,
              }
            : undefined
        }
      />
      <GlobalAnnouncements />
      <div className="w-full flex-1 bg-[#f4f7f5]">
        <div ref={area} className="staff-area mx-auto flex w-full flex-1 flex-col gap-4 sm:gap-6 px-3 py-4 sm:px-6 sm:py-6 lg:flex-row lg:gap-6 lg:px-4 xl:px-6">
          
          {/* Mobile & Tablet Collapsible Navigation Bar (< lg) */}
          <div className="no-print flex items-center justify-between rounded-xl border border-[#b2d6c0] bg-[#d2e8db] p-2.5 shadow-sm lg:hidden">
            <div className="flex items-center gap-2 min-w-0">
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#1b5e20]/15 text-[#1b5e20]">
                {currentActiveItem?.icon || icon("M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z")}
              </span>
              <div className="min-w-0">
                <div className="text-[10px] font-bold uppercase tracking-wider text-emerald-900/80">{t("Staff Console")}</div>
                <div className="truncate text-xs sm:text-sm font-semibold text-slate-900">{currentActiveItem ? t(currentActiveItem.label) : t("Dashboard")}</div>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setMobileNavOpen(true)}
              aria-expanded={mobileNavOpen}
              aria-controls="staff-mobile-drawer"
              aria-label={t("Open staff navigation menu")}
              className="flex min-h-[40px] items-center gap-1.5 rounded-lg border border-[#b2d6c0] bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 active:scale-95"
            >
              <svg className="h-4 w-4 text-[#1b5e20]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <span>{t("Menu")}</span>
            </button>
          </div>

          {/* Mobile Slide-Over Navigation Drawer (< lg) */}
          {mobileNavOpen && (
            <div className="fixed inset-0 z-50 flex lg:hidden" id="staff-mobile-drawer" role="dialog" aria-modal="true">
              {/* Backdrop */}
              <div
                className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                onClick={() => setMobileNavOpen(false)}
                aria-hidden="true"
              />

              {/* Drawer Content */}
              <div className="relative ml-0 flex w-full max-w-xs sm:max-w-sm flex-col bg-[#d2e8db] shadow-2xl border-r border-[#b2d6c0]">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-[#b2d6c0] bg-white/90 px-4 py-3.5">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#1b5e20] text-white">
                      <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    <div className="min-w-0">
                      <h2 className="text-xs font-bold tracking-wider uppercase text-[#1b5e20]">{t("Staff Console")}</h2>
                      <div className="truncate text-xs text-slate-600">{roleLabelDisplay || "Officer"}</div>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setMobileNavOpen(false)}
                    aria-label={t("Close navigation drawer")}
                    className="flex min-h-[40px] min-w-[40px] items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                  >
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  </button>
                </div>

                {/* User Info Bar: Rank + Full Name (Service Number hidden) */}
                {user && (
                  <div className="border-b border-[#b2d6c0] bg-white/70 px-4 py-2.5">
                    <div className="text-xs font-bold text-slate-900 truncate">
                      {user.rank ? `${user.rank} ${user.fullname}` : user.fullname}
                    </div>
                    <div className="text-[11px] text-emerald-900 font-medium truncate">
                      {roleLabelDisplay}
                    </div>
                  </div>
                )}

                {/* Drawer Nav Items */}
                <div className="flex-1 overflow-y-auto p-3 space-y-4">
                  {user &&
                    activeNavSections.map((sec, idx) => {
                      const visible = sec.items.filter((item) => !item.roles || hasRole(user, ...item.roles));
                      if (visible.length === 0) return null;
                      return (
                        <div key={sec.title} className={idx > 0 ? "border-t border-[#bfdec9] pt-3" : ""}>
                          <div className="px-2 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-900/80">
                            {t(sec.title)}
                          </div>
                          <ul className="space-y-1">
                            {visible.map((item) => {
                              const active = isItemActive(item.href);
                              return (
                                <li key={item.href}>
                                  <Link
                                    href={item.href}
                                    scroll={false}
                                    prefetch={false}
                                    onClick={() => setMobileNavOpen(false)}
                                    aria-current={active ? "page" : undefined}
                                    className={`flex min-h-[42px] items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors ${
                                      active
                                        ? "bg-[#1b5e20] font-semibold text-white shadow-sm ring-1 ring-black/5"
                                        : "text-slate-900 font-medium hover:bg-white/75 hover:text-emerald-950"
                                    }`}
                                  >
                                    <span className={active ? "text-white" : "text-emerald-900/80"}>{item.icon}</span>
                                    <span className="truncate">{t(item.label)}</span>
                                  </Link>
                                </li>
                              );
                            })}
                          </ul>
                        </div>
                      );
                    })}
                </div>
              </div>
            </div>
          )}

          {/* Desktop Fixed Sidebar (>= lg) */}
          <nav className="no-print hidden lg:block lg:w-56 xl:w-[15rem] lg:shrink-0" aria-label="Staff console">
            <div
              ref={menu}
              className="sticky top-24 max-h-[calc(100vh-7.5rem)] flex flex-col gap-3 overflow-y-auto overflow-x-hidden overscroll-contain rounded-2xl border border-[#b2d6c0] bg-[#d2e8db] p-2.5 shadow-sm"
            >
              {user &&
                activeNavSections.map((sec, idx) => {
                  const visible = sec.items.filter((item) => !item.roles || hasRole(user, ...item.roles));
                  if (visible.length === 0) return null;
                  return (
                    <div key={sec.title} className={idx > 0 ? "border-t border-[#bfdec9] pt-3" : ""}>
                      <div className="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-900/80">
                        {t(sec.title)}
                      </div>
                      <ul className="flex flex-col gap-1">
                        {visible.map((item) => {
                          const active = isItemActive(item.href);
                          return (
                            <li key={item.href}>
                              <Link
                                href={item.href}
                                scroll={false}
                                prefetch={false}
                                aria-current={active ? "page" : undefined}
                                className={`flex items-center gap-2.5 whitespace-nowrap rounded-xl px-3 py-2 text-sm transition-colors ${
                                  active
                                    ? "bg-[#1b5e20] font-semibold text-white shadow-sm ring-1 ring-black/5"
                                    : "text-slate-900 font-medium hover:bg-white/75 hover:text-emerald-950"
                                }`}
                              >
                                <span className={active ? "text-white" : "text-emerald-900/80"}>{item.icon}</span>
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

          {/* Main Content Area */}
          <main id="main" tabIndex={-1} className="min-w-0 flex-1">
            {error ? (
              <p className="text-sm text-red-700">{error}</p>
            ) : !user ? (
              <Spinner />
            ) : restrictedNav ? (
              <div className="rounded-2xl border border-red-200 bg-white p-8 text-center shadow-sm max-w-xl mx-auto my-8">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600 mb-4">
                  <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM12 8v4M12 16h.01"/></svg>
                </div>
                <h2 className="text-lg font-bold text-slate-900">{t("Access Restricted (403 Forbidden)")}</h2>
                <p className="mt-2 text-sm text-slate-600">
                  {t("Your assigned role ({role}) does not have administrative authorization to access the {module} module.", {
                    role: roleLabelDisplay || user.role,
                    module: restrictedNav.label,
                  })}
                </p>
                <div className="mt-6">
                  <Link href="/staff" className="inline-flex items-center gap-2 rounded-lg bg-nis-primary hover:bg-nis-primary-dark px-4 py-2 text-sm font-semibold text-white shadow-sm transition">
                    <span>{t("Return to My Dashboard")}</span>
                  </Link>
                </div>
              </div>
            ) : (
              <StaffContext.Provider value={user}>{children}</StaffContext.Provider>
            )}
          </main>
        </div>
      </div>
      <SiteFooter />
    </>
  );
}

export function StaffShell({ children }: { children: ReactNode }) {
  return (
    <Suspense fallback={<div className="min-h-screen bg-[#f4f7f5]" />}>
      <StaffShellInner>{children}</StaffShellInner>
    </Suspense>
  );
}
