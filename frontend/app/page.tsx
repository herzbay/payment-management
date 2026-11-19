import {
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
    <main className="flex min-h-screen items-center justify-center bg-background px-6 py-12">
      <div className="w-full max-w-5xl">
        <div className="mb-12 flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <CreditCard size={23} />
          </div>

          <div>
            <p className="text-base font-bold tracking-tight text-foreground">
              Payment Management
            </p>
            <p className="text-xs text-muted-foreground">
              Financial Operations System
            </p>
          </div>
        </div>

        <section className="max-w-3xl">
          <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700">
            <span className="h-2 w-2 rounded-full bg-blue-600" />
            Sistem Manajemen Keuangan Terintegrasi
          </div>

          <h1 className="text-4xl font-bold leading-tight tracking-tight text-foreground sm:text-5xl">
            Kelola operasional pembayaran perusahaan dalam satu sistem.
          </h1>

          <p className="mt-5 max-w-2xl text-base leading-7 text-muted-foreground">
            Platform terintegrasi untuk mengelola quotation, invoice,
            pembayaran, verifikasi, piutang, rekonsiliasi bank, dan
            laporan keuangan perusahaan.
          </p>
        </section>

        <section className="mt-12 grid gap-4 sm:grid-cols-2">
          {features.map((feature) => {
            const Icon = feature.icon;

            return (
              <div
                key={feature.title}
                className="rounded-xl border border-border bg-card p-5 transition-shadow hover:shadow-md"
              >
                <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-primary">
                  <Icon size={21} />
                </div>

                <h2 className="font-semibold text-card-foreground">
                  {feature.title}
                </h2>

                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                  {feature.description}
                </p>
              </div>
            );
          })}
        </section>

        <footer className="mt-12 flex flex-col gap-2 border-t border-border pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
          <p>Payment Management System</p>
          <p>Internal Company Application</p>
        </footer>
      </div>
    </main>
  );
}
