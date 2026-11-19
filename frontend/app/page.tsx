import Link from "next/link";
import {
  ArrowRight,
  ChartNoAxesCombined,
  ClipboardList,
  CreditCard,
  ShieldCheck,
} from "lucide-react";

const features = [
  {
    icon: CreditCard,
    title: "Payment Management",
    description: "Kelola transaksi pembayaran dan verifikasi.",
  },
  {
    icon: ClipboardList,
    title: "Financial Operations",
    description: "Kelola invoice, quotation, receipt, dan piutang.",
  },
  {
    icon: ChartNoAxesCombined,
    title: "Financial Analytics",
    description: "Pantau laporan dan performa keuangan.",
  },
  {
    icon: ShieldCheck,
    title: "Security & Audit",
    description: "Kontrol akses dan rekam aktivitas sistem.",
  },
];

export default function Home() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-background px-5 py-6 sm:px-8">
      <div className="w-full max-w-5xl">
        {/* Header */}
        <header className="mb-8 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
              <CreditCard size={21} />
            </div>

            <div>
              <p className="text-sm font-bold tracking-tight text-foreground sm:text-base">
                Payment Management
              </p>
              <p className="text-xs text-muted-foreground">
                Financial Operations System
              </p>
            </div>
          </div>

          <Link
            href="/login"
            className="inline-flex shrink-0 items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
          >
            Masuk
            <ArrowRight size={16} />
          </Link>
        </header>

        {/* Hero */}
        <section className="max-w-3xl">
          <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
            <span className="h-2 w-2 rounded-full bg-blue-600" />
            Sistem Manajemen Keuangan Terintegrasi
          </div>

          <h1 className="text-3xl font-bold leading-tight tracking-tight text-foreground sm:text-4xl lg:text-5xl">
            Kelola operasional pembayaran perusahaan dalam satu sistem.
          </h1>

          <p className="mt-4 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base sm:leading-7">
            Platform terintegrasi untuk mengelola quotation, invoice,
            pembayaran, verifikasi, piutang, rekonsiliasi bank, dan
            laporan keuangan perusahaan.
          </p>

          <div className="mt-6">
            <Link
              href="/login"
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-md transition-all hover:-translate-y-0.5 hover:bg-primary/90 hover:shadow-lg"
            >
              Masuk ke Sistem
              <ArrowRight size={17} />
            </Link>
          </div>
        </section>

        {/* Features */}
        <section className="mt-9 grid gap-3 sm:grid-cols-2">
          {features.map((feature) => {
            const Icon = feature.icon;

            return (
              <div
                key={feature.title}
                className="flex items-start gap-4 rounded-xl border border-border bg-card p-4 transition-shadow hover:shadow-md sm:p-5"
              >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                  <Icon size={20} />
                </div>

                <div className="min-w-0">
                  <h2 className="text-sm font-semibold text-card-foreground sm:text-base">
                    {feature.title}
                  </h2>

                  <p className="mt-1 text-xs leading-5 text-muted-foreground sm:text-sm">
                    {feature.description}
                  </p>
                </div>
              </div>
            );
          })}
        </section>

        {/* Footer */}
        <footer className="mt-8 flex flex-col gap-2 border-t border-border pt-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
          <p>Payment Management System</p>
          <p>Internal Company Application</p>
        </footer>
      </div>
    </main>
  );
}
