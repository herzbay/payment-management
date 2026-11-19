"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  FileText,
  FileCheck,
  Receipt,
  CreditCard,
  ShieldCheck,
  BookOpen,
  RefreshCcw,
  Landmark,
  ChartNoAxesCombined,
  ClipboardList,
  Settings,
  X,
  Building2,
  Users,
  BriefcaseBusiness,
} from "lucide-react";

import { cn } from "@/lib/utils";

interface SidebarProps {
  open: boolean;
  onClose: () => void;
}

const navigationGroups = [
  {
    title: "UTAMA",
    items: [
      {
        title: "Dashboard",
        href: "/dashboard",
        icon: LayoutDashboard,
      },
    ],
  },
  {
    title: "MASTER DATA",
    items: [
      {
        title: "Customer Management",
        href: "/customers",
        icon: Users,
      },
      {
        title: "Service Management",
        href: "/services",
        icon: BriefcaseBusiness,
      },
    ],
  },
  {
    title: "OPERASIONAL",
    items: [
      {
        title: "Quotation & Agreement",
        href: "/quotations",
        icon: FileText,
      },
      {
        title: "JMU Sertifikasi Invoice",
        href: "/invoices",
        icon: FileCheck,
      },
      {
        title: "JMU Payment Transactions",
        href: "/payments",
        icon: CreditCard,
      },
      {
        title: "Payment Verification",
        href: "/payment-verification",
        icon: ShieldCheck,
      },
      {
        title: "JMU Receipt Management",
        href: "/receipts",
        icon: Receipt,
      },
    ],
  },
  {
    title: "KEUANGAN",
    items: [
      {
        title: "Accounts Receivable",
        href: "/receivables",
        icon: BookOpen,
      },
      {
        title: "JMU Refunds & Adjustments",
        href: "/refunds",
        icon: RefreshCcw,
      },
      {
        title: "Bank Reconciliation",
        href: "/bank-reconciliation",
        icon: Landmark,
      },
      {
        title: "Financial Reports",
        href: "/reports",
        icon: ChartNoAxesCombined,
      },
    ],
  },
  {
    title: "SISTEM",
    items: [
      {
        title: "JMU Audit Trail",
        href: "/audit-trail",
        icon: ClipboardList,
      },
      {
        title: "Payment Settings",
        href: "/settings",
        icon: Settings,
      },
    ],
  },
];

export function Sidebar({ open, onClose }: SidebarProps) {
  const pathname = usePathname();

  return (
    <>
      {open && (
        <button
          type="button"
          aria-label="Tutup navigasi"
          onClick={onClose}
          className="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
        />
      )}

      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col border-r border-border bg-sidebar transition-transform duration-300",
          "lg:translate-x-0",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className="flex h-[72px] shrink-0 items-center justify-between border-b border-border px-5">
          <Link
            href="/dashboard"
            onClick={onClose}
            className="flex items-center gap-3"
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white">
              <Building2 size={21} />
            </div>
            <div>
              <p className="text-sm font-bold tracking-tight text-foreground">
                Payment Management
              </p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">
                Financial Operations
              </p>
            </div>
          </Link>

          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup sidebar"
            className="rounded-lg p-2 text-muted-foreground hover:bg-secondary lg:hidden"
          >
            <X size={19} />
          </button>
        </div>

        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
          {navigationGroups.map((group) => (
            <div key={group.title}>
              <p className="mb-2 px-3 text-[10px] font-bold tracking-[0.12em] text-muted-foreground">
                {group.title}
              </p>

              <div className="space-y-1">
                {group.items.map((item) => {
                  const Icon = item.icon;
                  const active =
                    pathname === item.href ||
                    (item.href !== "/dashboard" &&
                      pathname.startsWith(`${item.href}/`));

                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      onClick={onClose}
                      className={cn(
                        "flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors",
                        active
                          ? "bg-sidebar-active text-primary"
                          : "text-sidebar-foreground hover:bg-secondary",
                      )}
                    >
                      <Icon
                        size={18}
                        strokeWidth={active ? 2.3 : 1.8}
                        className="shrink-0"
                      />
                      <span className="leading-5">{item.title}</span>
                      {active && (
                        <span className="ml-auto h-5 w-1 rounded-full bg-primary" />
                      )}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>

        <div className="shrink-0 border-t border-border p-4">
          <div className="rounded-xl bg-secondary/70 p-3">
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <ShieldCheck size={17} />
              </div>
              <div className="min-w-0">
                <p className="text-xs font-semibold text-foreground">
                  Secure Workspace
                </p>
                <p className="text-[10px] text-muted-foreground">
                  Internal Company System
                </p>
              </div>
            </div>
          </div>

          <div className="mt-4 flex items-center justify-between px-1">
            <p className="text-[10px] text-muted-foreground">
              Payment Management
            </p>
            <p className="text-[10px] text-muted-foreground">v1.0.0</p>
          </div>
        </div>
      </aside>
    </>
  );
}
