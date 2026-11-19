import type { Metadata } from "next";
import {
  Activity,
  ArrowUpRight,
  CreditCard,
  FileText,
  Wallet,
} from "lucide-react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export const metadata: Metadata = {
  title: "Dashboard",
};

const overviewCards = [
  {
    title: "Total Invoice",
    value: "Rp 0",
    description: "Total nilai invoice",
    icon: FileText,
  },
  {
    title: "Total Pembayaran",
    value: "Rp 0",
    description: "Pembayaran terverifikasi",
    icon: CreditCard,
  },
  {
    title: "Piutang Aktif",
    value: "Rp 0",
    description: "Outstanding receivables",
    icon: Wallet,
  },
  {
    title: "Transaksi Hari Ini",
    value: "0",
    description: "Transaksi tercatat hari ini",
    icon: Activity,
  },
];

export default function DashboardPage() {
  return (
    <div className="mx-auto max-w-[1600px] space-y-6">
      <section className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
            Selamat Datang
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Ringkasan aktivitas dan operasional keuangan perusahaan.
          </p>
        </div>

        <div className="text-xs text-muted-foreground">
          Financial Overview
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {overviewCards.map((item) => {
          const Icon = item.icon;

          return (
            <Card key={item.title}>
              <CardContent className="flex items-start justify-between p-5">
                <div className="min-w-0">
                  <p className="text-sm font-medium text-muted-foreground">
                    {item.title}
                  </p>

                  <p className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                    {item.value}
                  </p>

                  <p className="mt-2 text-xs text-muted-foreground">
                    {item.description}
                  </p>
                </div>

                <div className="rounded-xl bg-primary/10 p-3 text-primary">
                  <Icon size={21} />
                </div>
              </CardContent>
            </Card>
          );
        })}
      </section>

      <section className="grid gap-4 xl:grid-cols-3">
        <Card className="xl:col-span-2">
          <CardHeader>
            <CardTitle>Ringkasan Keuangan</CardTitle>
            <CardDescription>
              Ikhtisar performa keuangan perusahaan.
            </CardDescription>
          </CardHeader>

          <CardContent>
            <div className="flex min-h-[240px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-secondary/30 px-5 text-center">
              <div className="mb-3 rounded-full bg-primary/10 p-3 text-primary">
                <Activity size={24} />
              </div>

              <p className="text-sm font-semibold text-foreground">
                Grafik belum tersedia
              </p>

              <p className="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                Visualisasi pendapatan dan pembayaran akan ditampilkan
                setelah integrasi data laporan keuangan pada Tahap 5E.
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Aktivitas Terbaru</CardTitle>
            <CardDescription>
              Aktivitas transaksi dalam sistem.
            </CardDescription>
          </CardHeader>

          <CardContent>
            <div className="flex min-h-[240px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-secondary/30 px-5 text-center">
              <div className="mb-3 rounded-full bg-primary/10 p-3 text-primary">
                <ArrowUpRight size={24} />
              </div>

              <p className="text-sm font-semibold text-foreground">
                Belum ada aktivitas
              </p>

              <p className="mt-1 text-xs leading-5 text-muted-foreground">
                Aktivitas terbaru akan ditampilkan setelah data API
                terhubung.
              </p>
            </div>
          </CardContent>
        </Card>
      </section>

      <section>
        <Card>
          <CardHeader>
            <CardTitle>Modul Sistem</CardTitle>
            <CardDescription>
              Modul operasional yang tersedia pada Payment Management System.
            </CardDescription>
          </CardHeader>

          <CardContent>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {[
                "Quotation & Agreement",
                "JMU Sertifikasi Invoice",
                "JMU Payment Transactions",
                "Payment Verification",
                "JMU Receipt Management",
                "Accounts Receivable",
                "JMU Refunds & Adjustments",
                "Bank Reconciliation",
                "Financial Reports",
                "JMU Audit Trail",
                "Payment Settings",
              ].map((module, index) => (
                <div
                  key={module}
                  className="flex items-center gap-3 rounded-lg border border-border p-3"
                >
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-xs font-semibold text-muted-foreground">
                    {String(index + 1).padStart(2, "0")}
                  </div>

                  <p className="text-sm font-medium text-foreground">
                    {module}
                  </p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </section>
    </div>
  );
}
