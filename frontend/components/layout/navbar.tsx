"use client";

import { useCallback, useEffect, useState, useSyncExternalStore } from "react";
import { usePathname } from "next/navigation";
import {
  Menu,
  Moon,
  Sun,
  Bell,
  ChevronDown,
  UserRound,
  Settings,
  LogOut,
} from "lucide-react";

interface NavbarProps {
  onMenuClick: () => void;
}

const THEME_EVENT = "payment-management-theme-change";

const pageTitles: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/quotations": "Quotation & Agreement",
  "/invoices": "JMU Sertifikasi Invoice",
  "/payments": "JMU Payment Transactions",
  "/payment-verification": "Payment Verification",
  "/receipts": "JMU Receipt Management",
  "/receivables": "Accounts Receivable",
  "/refunds": "JMU Refunds & Adjustments",
  "/bank-reconciliation": "Bank Reconciliation",
  "/reports": "Financial Reports",
  "/audit-trail": "JMU Audit Trail",
  "/settings": "Payment Settings",
};

export function Navbar({ onMenuClick }: NavbarProps) {
  const pathname = usePathname();

  const [profileOpen, setProfileOpen] = useState(false);

  const subscribe = useCallback((callback: () => void) => {
    window.addEventListener(THEME_EVENT, callback);
    window.addEventListener("storage", callback);

    return () => {
      window.removeEventListener(THEME_EVENT, callback);
      window.removeEventListener("storage", callback);
    };
  }, []);

  const getSnapshot = useCallback(() => {
    return document.documentElement.classList.contains("dark");
  }, []);

  const getServerSnapshot = useCallback(() => false, []);

  const darkMode = useSyncExternalStore(
    subscribe,
    getSnapshot,
    getServerSnapshot
  );

  useEffect(() => {
    const savedTheme = localStorage.getItem(
      "payment-management-theme"
    );

    const isDark = savedTheme === "dark";

    document.documentElement.classList.toggle("dark", isDark);

    window.dispatchEvent(new Event(THEME_EVENT));
  }, []);

  const pageTitle =
    pageTitles[pathname] ??
    Object.entries(pageTitles).find(([path]) =>
      pathname.startsWith(`${path}/`)
    )?.[1] ??
    "Payment Management";

  function toggleTheme() {
    const nextTheme = darkMode ? "light" : "dark";

    document.documentElement.classList.toggle(
      "dark",
      nextTheme === "dark"
    );

    localStorage.setItem("payment-management-theme", nextTheme);

    window.dispatchEvent(new Event(THEME_EVENT));
  }

  return (
    <header className="sticky top-0 z-30 flex h-[72px] shrink-0 items-center justify-between border-b border-border bg-card/95 px-4 backdrop-blur sm:px-6">
      <div className="flex min-w-0 items-center gap-3">
        <button
          type="button"
          onClick={onMenuClick}
          aria-label="Buka navigasi"
          className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-secondary lg:hidden"
        >
          <Menu size={21} />
        </button>

        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-foreground sm:text-base">
            {pageTitle}
          </p>

          <p className="mt-0.5 hidden text-xs text-muted-foreground sm:block">
            Payment Management System
          </p>
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-1 sm:gap-2">
        <button
          type="button"
          onClick={toggleTheme}
          aria-label={
            darkMode ? "Gunakan tema terang" : "Gunakan tema gelap"
          }
          title={darkMode ? "Tema terang" : "Tema gelap"}
          className="rounded-lg p-2.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
        >
          {darkMode ? <Sun size={19} /> : <Moon size={19} />}
        </button>

        <button
          type="button"
          aria-label="Notifikasi"
          title="Notifikasi"
          className="relative rounded-lg p-2.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
        >
          <Bell size={19} />

          <span className="absolute right-2 top-2 h-2 w-2 rounded-full border-2 border-card bg-danger" />
        </button>

        <div className="mx-1 hidden h-8 w-px bg-border sm:block" />

        <div className="relative">
          <button
            type="button"
            onClick={() => setProfileOpen(!profileOpen)}
            aria-expanded={profileOpen}
            className="flex items-center gap-2 rounded-lg p-1.5 transition-colors hover:bg-secondary"
          >
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
              A
            </div>

            <div className="hidden text-left sm:block">
              <p className="text-xs font-semibold text-foreground">
                Administrator
              </p>

              <p className="mt-0.5 text-[10px] text-muted-foreground">
                Super Admin
              </p>
            </div>

            <ChevronDown
              size={15}
              className="hidden text-muted-foreground sm:block"
            />
          </button>

          {profileOpen && (
            <>
              <button
                type="button"
                aria-label="Tutup menu profil"
                className="fixed inset-0 z-40 cursor-default"
                onClick={() => setProfileOpen(false)}
              />

              <div className="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-border bg-card p-2 shadow-lg">
                <div className="border-b border-border px-3 py-3">
                  <p className="text-sm font-semibold text-foreground">
                    Administrator
                  </p>

                  <p className="mt-1 truncate text-xs text-muted-foreground">
                    admin@company.com
                  </p>
                </div>

                <div className="space-y-1 py-2">
                  <button
                    type="button"
                    onClick={() => setProfileOpen(false)}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-foreground hover:bg-secondary"
                  >
                    <UserRound size={16} />
                    Profil Pengguna
                  </button>

                  <button
                    type="button"
                    onClick={() => setProfileOpen(false)}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-foreground hover:bg-secondary"
                  >
                    <Settings size={16} />
                    Pengaturan Akun
                  </button>

                  <div className="my-1 border-t border-border" />

                  <button
                    type="button"
                    disabled
                    title="Logout akan diaktifkan pada tahap autentikasi"
                    className="flex w-full cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-muted-foreground opacity-60"
                  >
                    <LogOut size={16} />
                    Keluar
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
