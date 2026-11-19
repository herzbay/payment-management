"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  ArrowDownRight,
  ArrowUpRight,
  CalendarDays,
  CheckCircle2,
  Clock3,
  FileText,
  LoaderCircle,
  RefreshCw,
  Wallet,
} from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import { dashboardService } from "@/lib/dashboard";
import type {
  DashboardData,
  RevenueData,
} from "@/types/dashboard";

interface DateRange {
  from: string;
  to: string;
}

function getDefaultDateRange(): DateRange {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

  return {
    from: formatDateInput(firstDay),
    to: formatDateInput(today),
  };
}

function formatDateInput(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function formatCurrency(
  amount: number,
  currency: string,
  compact = false
): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
    notation: compact ? "compact" : "standard",
  }).format(amount);
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat("id-ID").format(value);
}

function formatMonth(period: string): string {
  const [year, month] = period.split("-").map(Number);

  if (!year || !month) return period;

  return new Intl.DateTimeFormat("id-ID", {
    month: "short",
    year: "2-digit",
  }).format(new Date(year, month - 1, 1));
}

function formatDate(date: string): string {
  if (!date) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  }).format(new Date(date));
}

function StatCard({
  title,
  value,
  description,
  icon,
  iconClass,
}: {
  title: string;
  value: string;
  description: string;
  icon: React.ReactNode;
  iconClass: string;
}) {
  return (
    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-muted-foreground">
            {title}
          </p>
          <p className="mt-3 break-words text-2xl font-bold tracking-tight text-card-foreground">
            {value}
          </p>
        </div>

        <div
          className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconClass}`}
        >
          {icon}
        </div>
      </div>

      <p className="mt-3 text-xs text-muted-foreground">
        {description}
      </p>
    </div>
  );
}

export default function DashboardPage() {
  const [dateRange, setDateRange] = useState<DateRange>(
    getDefaultDateRange
  );
  const [appliedRange, setAppliedRange] = useState<DateRange>(
    getDefaultDateRange
  );

  const [dashboard, setDashboard] = useState<DashboardData | null>(
    null
  );
  const [revenue, setRevenue] = useState<RevenueData | null>(null);

  const [selectedCurrency, setSelectedCurrency] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

    const loadDashboard = useCallback(async () => {
    try {
        const [dashboardResult, revenueResult] = await Promise.all([
        dashboardService.getDashboard(appliedRange),
        dashboardService.getRevenue(appliedRange),
        ]);

        setDashboard(dashboardResult);
        setRevenue(revenueResult);
    } catch (err) {
        setError(
        err instanceof Error
            ? err.message
            : "Terjadi kesalahan saat mengambil data dashboard."
        );
    } finally {
        setIsLoading(false);
    }
    }, [appliedRange]);

    useEffect(() => {
    const timeoutId = window.setTimeout(() => {
        void loadDashboard();
    }, 0);

    return () => {
        window.clearTimeout(timeoutId);
    };
    }, [loadDashboard]);

  const currencies = useMemo(() => {
    const values = [
      ...(dashboard?.financial_summary.map((item) => item.currency) ??
        []),
      ...(dashboard?.receivables_summary.map((item) => item.currency) ??
        []),
      ...(revenue?.totals.map((item) => item.currency) ?? []),
    ];

    return [...new Set(values)];
  }, [dashboard, revenue]);

  const activeCurrency =
    currencies.find((currency) => currency === selectedCurrency) ??
    currencies[0] ??
    "IDR";

  const financialSummary = dashboard?.financial_summary.find(
    (item) => item.currency === activeCurrency
  );

  const receivablesSummary = dashboard?.receivables_summary.find(
    (item) => item.currency === activeCurrency
  );

  const revenueChartData = useMemo(() => {
    return (
      revenue?.data
        .filter((item) => item.currency === activeCurrency)
        .map((item) => ({
          ...item,
          month_label: formatMonth(item.period),
        })) ?? []
    );
  }, [revenue, activeCurrency]);

  const revenueTotals = revenue?.totals.find(
    (item) => item.currency === activeCurrency
  );

  function handleApplyFilter() {
    if (!dateRange.from || !dateRange.to) {
      setError("Tanggal awal dan tanggal akhir wajib diisi.");
      return;
    }

    if (dateRange.from > dateRange.to) {
      setError("Tanggal awal tidak boleh melewati tanggal akhir.");
      return;
    }

    setIsLoading(true);
    setError("");
    setAppliedRange({ ...dateRange });
  }

  function handleResetFilter() {
    const defaults = getDefaultDateRange();

    setDateRange(defaults);
    setIsLoading(true);
    setError("");
    setAppliedRange(defaults);
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground">
            Dashboard
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Ringkasan kondisi keuangan dan aktivitas pembayaran perusahaan.
          </p>
        </div>

        <button
          type="button"
            onClick={() => {
            setIsLoading(true);
            setError("");
            void loadDashboard();
            }}
          disabled={isLoading}
          className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-60"
        >
          <RefreshCw
            size={16}
            className={isLoading ? "animate-spin" : ""}
          />
          Refresh
        </button>
      </div>

      <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="mb-3 flex items-center gap-2">
          <CalendarDays size={17} className="text-muted-foreground" />
          <h2 className="text-sm font-semibold text-card-foreground">
            Filter Periode
          </h2>
        </div>

        <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
          <div className="flex-1">
            <label
              htmlFor="dashboard-from"
              className="mb-1.5 block text-xs font-medium text-muted-foreground"
            >
              Tanggal Awal
            </label>
            <input
              id="dashboard-from"
              type="date"
              value={dateRange.from}
              onChange={(event) =>
                setDateRange((current) => ({
                  ...current,
                  from: event.target.value,
                }))
              }
              className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
            />
          </div>

          <div className="flex-1">
            <label
              htmlFor="dashboard-to"
              className="mb-1.5 block text-xs font-medium text-muted-foreground"
            >
              Tanggal Akhir
            </label>
            <input
              id="dashboard-to"
              type="date"
              value={dateRange.to}
              min={dateRange.from}
              onChange={(event) =>
                setDateRange((current) => ({
                  ...current,
                  to: event.target.value,
                }))
              }
              className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
            />
          </div>

          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleApplyFilter}
              disabled={isLoading}
              className="inline-flex h-10 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary-hover disabled:opacity-60"
            >
              Terapkan
            </button>

            <button
              type="button"
              onClick={handleResetFilter}
              disabled={isLoading}
              className="inline-flex h-10 items-center justify-center rounded-lg border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-secondary disabled:opacity-60"
            >
              Reset
            </button>
          </div>
        </div>
      </section>

      {error && (
        <div
          role="alert"
          className="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-background p-4 text-sm text-danger"
        >
          <AlertCircle size={18} className="mt-0.5 shrink-0" />
          <div className="flex-1">{error}</div>
          <button
            type="button"
            onClick={() => void loadDashboard()}
            className="shrink-0 font-semibold underline"
          >
            Coba lagi
          </button>
        </div>
      )}

      {isLoading ? (
        <div className="flex min-h-72 flex-col items-center justify-center rounded-xl border border-border bg-card">
          <LoaderCircle
            size={30}
            className="animate-spin text-primary"
          />
          <p className="mt-3 text-sm text-muted-foreground">
            Memuat data dashboard...
          </p>
        </div>
      ) : dashboard ? (
        <>
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                Ringkasan Keuangan
              </h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Periode {formatDate(dashboard.period.from)} –{" "}
                {formatDate(dashboard.period.to)}
              </p>
            </div>

            <div className="flex items-center gap-2">
              <label
                htmlFor="dashboard-currency"
                className="text-xs font-medium text-muted-foreground"
              >
                Mata uang
              </label>
              <select
                id="dashboard-currency"
                value={activeCurrency}
                onChange={(event) =>
                  setSelectedCurrency(event.target.value)
                }
                className="h-9 rounded-lg border border-input bg-card px-3 text-sm text-foreground outline-none focus:border-ring"
              >
                {currencies.length === 0 && (
                  <option value="IDR">IDR</option>
                )}
                {currencies.map((currency) => (
                  <option key={currency} value={currency}>
                    {currency}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
              title="Total Invoice"
              value={formatCurrency(
                financialSummary?.total_invoiced ?? 0,
                activeCurrency
              )}
              description={`${formatNumber(
                financialSummary?.invoice_count ?? 0
              )} invoice dalam periode`}
              icon={<FileText size={21} />}
              iconClass="bg-blue-500/10 text-blue-600 dark:text-blue-400"
            />

            <StatCard
              title="Pembayaran Terverifikasi"
              value={formatCurrency(
                financialSummary?.verified_payments ?? 0,
                activeCurrency
              )}
              description={`${formatNumber(
                financialSummary?.verified_payment_count ?? 0
              )} transaksi terverifikasi`}
              icon={<Wallet size={21} />}
              iconClass="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
            />

            <StatCard
              title="Outstanding Receivables"
              value={formatCurrency(
                receivablesSummary?.outstanding_amount ?? 0,
                activeCurrency
              )}
              description="Total piutang yang belum terselesaikan"
              icon={<ArrowDownRight size={21} />}
              iconClass="bg-amber-500/10 text-amber-600 dark:text-amber-400"
            />

            <StatCard
              title="Pending Verification"
              value={formatNumber(
                dashboard.pending_verifications
              )}
              description="Transaksi menunggu verifikasi"
              icon={<Clock3 size={21} />}
              iconClass="bg-violet-500/10 text-violet-600 dark:text-violet-400"
            />
          </div>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div className="rounded-xl border border-border bg-card p-5 shadow-sm xl:col-span-2">
              <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                  <h2 className="font-semibold text-card-foreground">
                    Tren Penerimaan
                  </h2>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Pembayaran, refund, dan penerimaan bersih per bulan
                  </p>
                </div>
                <span className="text-xs font-medium text-muted-foreground">
                  {activeCurrency}
                </span>
              </div>

              <div className="mt-5 h-[300px] w-full">
                {revenueChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart
                      data={revenueChartData}
                      margin={{
                        top: 10,
                        right: 8,
                        left: 8,
                        bottom: 0,
                      }}
                    >
                      <defs>
                        <linearGradient
                          id="paymentGradient"
                          x1="0"
                          y1="0"
                          x2="0"
                          y2="1"
                        >
                          <stop
                            offset="0%"
                            stopColor="#3b82f6"
                            stopOpacity={0.22}
                          />
                          <stop
                            offset="95%"
                            stopColor="#3b82f6"
                            stopOpacity={0}
                          />
                        </linearGradient>
                        <linearGradient
                          id="netGradient"
                          x1="0"
                          y1="0"
                          x2="0"
                          y2="1"
                        >
                          <stop
                            offset="0%"
                            stopColor="#10b981"
                            stopOpacity={0.2}
                          />
                          <stop
                            offset="95%"
                            stopColor="#10b981"
                            stopOpacity={0}
                          />
                        </linearGradient>
                      </defs>

                      <CartesianGrid
                        strokeDasharray="3 3"
                        vertical={false}
                        stroke="var(--border)"
                      />

                      <XAxis
                        dataKey="month_label"
                        tick={{ fontSize: 11 }}
                        tickLine={false}
                        axisLine={false}
                        stroke="var(--muted-foreground)"
                      />

                      <YAxis
                        tickFormatter={(value: number) =>
                          formatCurrency(
                            value,
                            activeCurrency,
                            true
                          )
                        }
                        tick={{ fontSize: 10 }}
                        tickLine={false}
                        axisLine={false}
                        width={85}
                        stroke="var(--muted-foreground)"
                      />

                      <Tooltip
                        formatter={(value) =>
                          formatCurrency(
                            Number(value ?? 0),
                            activeCurrency
                          )
                        }
                        contentStyle={{
                          background: "var(--card)",
                          border: "1px solid var(--border)",
                          borderRadius: "10px",
                          color: "var(--card-foreground)",
                          fontSize: "12px",
                        }}
                        labelStyle={{
                          color: "var(--muted-foreground)",
                          marginBottom: "6px",
                        }}
                      />

                      <Area
                        type="monotone"
                        dataKey="total_payments"
                        name="Pembayaran"
                        stroke="#3b82f6"
                        strokeWidth={2}
                        fill="url(#paymentGradient)"
                        connectNulls
                      />

                      <Area
                        type="monotone"
                        dataKey="net_receipts"
                        name="Penerimaan Bersih"
                        stroke="#10b981"
                        strokeWidth={2}
                        fill="url(#netGradient)"
                        connectNulls
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                    Belum ada data pendapatan untuk periode ini.
                  </div>
                )}
              </div>

              <div className="mt-3 flex flex-wrap gap-5 border-t border-border pt-4">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <span className="h-2.5 w-2.5 rounded-full bg-blue-500" />
                  Pembayaran
                </div>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                  Penerimaan Bersih
                </div>
              </div>
            </div>

            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <h2 className="font-semibold text-card-foreground">
                Ringkasan Penerimaan
              </h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Akumulasi selama periode terpilih
              </p>

              <div className="mt-6 space-y-5">
                <div>
                  <p className="text-xs text-muted-foreground">
                    Total Pembayaran
                  </p>
                  <p className="mt-1 text-lg font-bold text-card-foreground">
                    {formatCurrency(
                      revenueTotals?.payments ?? 0,
                      activeCurrency
                    )}
                  </p>
                </div>

                <div className="border-t border-border pt-4">
                  <p className="text-xs text-muted-foreground">
                    Total Refund
                  </p>
                  <p className="mt-1 flex items-center gap-2 text-lg font-bold text-danger">
                    <ArrowDownRight size={17} />
                    {formatCurrency(
                      revenueTotals?.refunds ?? 0,
                      activeCurrency
                    )}
                  </p>
                </div>

                <div className="border-t border-border pt-4">
                  <p className="text-xs text-muted-foreground">
                    Penerimaan Bersih
                  </p>
                  <p className="mt-1 flex items-center gap-2 text-xl font-bold text-success">
                    <ArrowUpRight size={19} />
                    {formatCurrency(
                      revenueTotals?.net_receipts ?? 0,
                      activeCurrency
                    )}
                  </p>
                </div>
              </div>

              <div className="mt-6 rounded-lg bg-secondary/70 p-3">
                <div className="flex items-center gap-2">
                  <CheckCircle2
                    size={16}
                    className="text-success"
                  />
                  <p className="text-xs font-medium text-card-foreground">
                    Data pembayaran terverifikasi
                  </p>
                </div>
                <p className="mt-1 text-[11px] text-muted-foreground">
                  Perhitungan penerimaan bersih memperhitungkan refund
                  yang telah selesai.
                </p>
              </div>
            </div>
          </section>

          <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <h2 className="font-semibold text-card-foreground">
                Ringkasan Piutang
              </h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Posisi piutang berdasarkan mata uang terpilih
              </p>

              <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="rounded-lg bg-secondary/70 p-4">
                  <p className="text-xs text-muted-foreground">
                    Outstanding
                  </p>
                  <p className="mt-2 break-words text-lg font-bold text-card-foreground">
                    {formatCurrency(
                      receivablesSummary?.outstanding_amount ?? 0,
                      activeCurrency
                    )}
                  </p>
                </div>

                <div className="rounded-lg bg-danger-background p-4">
                  <p className="text-xs text-danger">
                    Overdue
                  </p>
                  <p className="mt-2 break-words text-lg font-bold text-danger">
                    {formatCurrency(
                      receivablesSummary?.overdue_amount ?? 0,
                      activeCurrency
                    )}
                  </p>
                </div>
              </div>

              <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                <span className="text-sm text-muted-foreground">
                  Invoice melewati jatuh tempo
                </span>
                <span className="text-sm font-bold text-foreground">
                  {formatNumber(
                    receivablesSummary?.overdue_invoice_count ?? 0
                  )}
                </span>
              </div>
            </div>

            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <h2 className="font-semibold text-card-foreground">
                Informasi Dashboard
              </h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Informasi periode dan pembaruan data
              </p>

              <div className="mt-5 space-y-4">
                <div className="flex items-center justify-between gap-3">
                  <span className="text-sm text-muted-foreground">
                    Periode laporan
                  </span>
                  <span className="text-right text-sm font-medium text-foreground">
                    {formatDate(dashboard.period.from)} –{" "}
                    {formatDate(dashboard.period.to)}
                  </span>
                </div>

                <div className="flex items-center justify-between gap-3">
                  <span className="text-sm text-muted-foreground">
                    Mata uang aktif
                  </span>
                  <span className="text-sm font-medium text-foreground">
                    {activeCurrency}
                  </span>
                </div>

                <div className="flex items-center justify-between gap-3">
                  <span className="text-sm text-muted-foreground">
                    Verifikasi tertunda
                  </span>
                  <span className="text-sm font-semibold text-warning">
                    {formatNumber(
                      dashboard.pending_verifications
                    )}{" "}
                    transaksi
                  </span>
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                  <span className="text-sm text-muted-foreground">
                    Data dibuat
                  </span>
                  <span className="text-right text-xs text-muted-foreground">
                    {new Date(dashboard.generated_at).toLocaleString(
                      "id-ID",
                      {
                        dateStyle: "medium",
                        timeStyle: "short",
                      }
                    )}
                  </span>
                </div>
              </div>
            </div>
          </section>
        </>
      ) : (
        <div className="rounded-xl border border-border bg-card p-10 text-center">
          <AlertCircle
            size={30}
            className="mx-auto text-muted-foreground"
          />
          <h2 className="mt-3 font-semibold text-card-foreground">
            Data dashboard belum tersedia
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Periksa koneksi server dan coba muat ulang data.
          </p>
          <button
            type="button"
            onClick={() => void loadDashboard()}
            className="mt-4 inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary-hover"
          >
            <RefreshCw size={15} />
            Muat Ulang
          </button>
        </div>
      )}
    </div>
  );
}
