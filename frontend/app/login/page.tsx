"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowRight,
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
  ShieldCheck,
  CreditCard,
  LoaderCircle,
} from "lucide-react";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export default function LoginPage() {
  const router = useRouter();
  const { user, isLoading, signIn } = useAuth();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!isLoading && user) {
      router.replace("/dashboard");
    }
  }, [isLoading, user, router]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setError("");

    if (!email.trim() || !password) {
      setError("Email dan password wajib diisi.");
      return;
    }

    try {
      setSubmitting(true);

      await signIn(email.trim(), password);

      router.replace("/dashboard");
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Terjadi kesalahan saat login."
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading || user) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-background">
        <LoaderCircle className="animate-spin text-primary" size={30} />
      </main>
    );
  }

  return (
    <main className="grid min-h-screen bg-background lg:grid-cols-2">
      <section className="relative hidden flex-col justify-between overflow-hidden bg-slate-950 p-12 text-white lg:flex xl:p-16">
        <div className="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl" />
        <div className="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl" />

        <div className="relative z-10 flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600">
            <CreditCard size={23} />
          </div>

          <div>
            <p className="text-sm font-bold tracking-tight">
              Payment Management
            </p>
            <p className="mt-0.5 text-xs text-slate-400">
              Financial Operations System
            </p>
          </div>
        </div>

        <div className="relative z-10 max-w-xl">
          <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-400/20 bg-blue-500/10 px-3 py-1.5 text-xs font-medium text-blue-300">
            <ShieldCheck size={14} />
            Secure Financial Workspace
          </div>

          <h1 className="text-4xl font-bold leading-tight tracking-tight xl:text-5xl">
            Manage your financial operations in one place.
          </h1>

          <p className="mt-6 max-w-lg text-sm leading-7 text-slate-400">
            Sistem terintegrasi untuk mengelola quotation, invoice,
            pembayaran, verifikasi, piutang, rekonsiliasi bank,
            dan laporan keuangan perusahaan.
          </p>

          <div className="mt-10 grid grid-cols-2 gap-4">
            {[
              "Payment Management",
              "Invoice & Receipt",
              "Bank Reconciliation",
              "Financial Reporting",
            ].map((feature) => (
              <div
                key={feature}
                className="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-3"
              >
                <ShieldCheck
                  size={15}
                  className="shrink-0 text-blue-400"
                />
                <span className="text-xs text-slate-300">
                  {feature}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="relative z-10 flex items-center justify-between text-xs text-slate-500">
          <p>Payment Management System</p>
          <p>Internal Company Application</p>
        </div>
      </section>

      <section className="flex min-h-screen items-center justify-center px-5 py-12 sm:px-10">
        <div className="w-full max-w-md">
          <div className="mb-10 flex items-center gap-3 lg:hidden">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white">
              <CreditCard size={21} />
            </div>

            <div>
              <p className="text-sm font-bold text-foreground">
                Payment Management
              </p>
              <p className="text-xs text-muted-foreground">
                Financial Operations
              </p>
            </div>
          </div>

          <div className="mb-8">
            <p className="text-sm font-semibold text-primary">
              Selamat Datang
            </p>

            <h2 className="mt-2 text-3xl font-bold tracking-tight text-foreground">
              Masuk ke akun Anda
            </h2>

            <p className="mt-3 text-sm leading-6 text-muted-foreground">
              Masukkan email dan password untuk mengakses
              Payment Management System.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <label
                htmlFor="email"
                className="text-sm font-medium text-foreground"
              >
                Email
              </label>

              <div className="relative">
                <Mail
                  size={18}
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                  id="email"
                  type="email"
                  placeholder="nama@perusahaan.com"
                  autoComplete="username"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  className="h-11 pl-10"
                  disabled={submitting}
                  required
                />
              </div>
            </div>

            <div className="space-y-2">
              <label
                htmlFor="password"
                className="text-sm font-medium text-foreground"
              >
                Password
              </label>

              <div className="relative">
                <LockKeyhole
                  size={18}
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  placeholder="Masukkan password"
                  autoComplete="current-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="h-11 pl-10 pr-11"
                  disabled={submitting}
                  required
                />

                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={
                    showPassword
                      ? "Sembunyikan password"
                      : "Tampilkan password"
                  }
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                >
                  {showPassword ? (
                    <EyeOff size={18} />
                  ) : (
                    <Eye size={18} />
                  )}
                </button>
              </div>
            </div>

            {error && (
              <div
                role="alert"
                className="rounded-lg border border-danger/20 bg-danger-background px-4 py-3 text-sm text-danger"
              >
                {error}
              </div>
            )}

            <Button
              type="submit"
              className="h-11 w-full"
              disabled={submitting}
            >
              {submitting ? (
                <>
                  <LoaderCircle size={17} className="animate-spin" />
                  Sedang masuk...
                </>
              ) : (
                <>
                  Masuk
                  <ArrowRight size={17} />
                </>
              )}
            </Button>
          </form>

          <div className="mt-8 flex items-center justify-center gap-2 text-xs text-muted-foreground">
            <ShieldCheck size={15} />
            Koneksi autentikasi yang aman
          </div>

          <p className="mt-10 text-center text-xs text-muted-foreground">
            © {new Date().getFullYear()} Payment Management System.
            All rights reserved.
          </p>
        </div>
      </section>
    </main>
  );
}
