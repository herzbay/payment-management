"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import {
  AlertCircle,
  Building2,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  Clock3,
  FileText,
  RefreshCw,
  Search,
} from "lucide-react";

import { approvalService } from "@/lib/approval";
import Link from "next/link";

interface ApprovalCustomer {
  company_name: string;
}

interface ApprovalQuotation {
  id: number;
  quotation_number: string;
  subject: string;
  issue_date: string;
  sent_at: string | null;
  total_amount: number;
  currency: string;
  status: string;
  customer?: ApprovalCustomer | null;
}

interface ApprovalPagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

const INITIAL_META: ApprovalPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: null,
  to: null,
};

function formatCurrency(amount: number, currency = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0);
}

function formatDate(date: string | null) {
  if (!date) return "-";

  const parsedDate = new Date(date);

  if (Number.isNaN(parsedDate.getTime())) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(parsedDate);
}

export default function ApprovalQueuePage() {
  const [quotations, setQuotations] = useState<ApprovalQuotation[]>([]);
  const [meta, setMeta] = useState<ApprovalPagination>(INITIAL_META);

  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadApprovals = useCallback(async () => {
    setLoading(true);
    setError("");

    try {
      const result = await approvalService.getPending(page, 10, search);

      setQuotations(result.data as ApprovalQuotation[]);
      setMeta(result.meta);
    } catch (err) {
      setQuotations([]);
      setMeta(INITIAL_META);
      setError(
        err instanceof Error
          ? err.message
          : "Gagal memuat antrean approval.",
      );
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadApprovals();
    }, 0);

    return () => window.clearTimeout(timer);
  }, [loadApprovals]);

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  }

  function handleRefresh() {
    void loadApprovals();
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <ClipboardCheck className="h-6 w-6 text-primary" />
            <h1 className="text-2xl font-semibold tracking-tight">
              Approval Queue
            </h1>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Review and manage quotations waiting for approval.
          </p>
        </div>

        <button
          type="button"
          onClick={handleRefresh}
          disabled={loading}
          className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
        >
          <RefreshCw
            className={`h-4 w-4 ${loading ? "animate-spin" : ""}`}
          />
          Refresh
        </button>
      </div>

      {/* Summary */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-muted-foreground">
                Pending Approvals
              </p>
              <p className="mt-2 text-2xl font-semibold">
                {loading ? "—" : meta.total}
              </p>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-500/10">
              <Clock3 className="h-5 w-5 text-amber-600" />
            </div>
          </div>
          <p className="mt-3 text-xs text-muted-foreground">
            Quotations waiting for a decision
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-muted-foreground">
                Current Page
              </p>
              <p className="mt-2 text-2xl font-semibold">
                {loading ? "—" : `${meta.current_page} / ${meta.last_page}`}
              </p>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-500/10">
              <FileText className="h-5 w-5 text-blue-600" />
            </div>
          </div>
          <p className="mt-3 text-xs text-muted-foreground">
            Approval queue pagination
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-muted-foreground">
                Items Displayed
              </p>
              <p className="mt-2 text-2xl font-semibold">
                {loading ? "—" : quotations.length}
              </p>
            </div>
            <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-emerald-500/10">
              <Building2 className="h-5 w-5 text-emerald-600" />
            </div>
          </div>
          <p className="mt-3 text-xs text-muted-foreground">
            Quotations on this page
          </p>
        </div>
      </div>

      {/* Approval Table */}
      <div className="overflow-hidden rounded-xl border border-border bg-card">
        <div className="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="font-semibold">Quotations Waiting for Approval</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Review the submitted quotations in this queue.
            </p>
          </div>

          <form
            onSubmit={handleSearch}
            className="flex w-full gap-2 sm:max-w-sm"
          >
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search quotation or customer..."
                className="h-10 w-full rounded-lg border border-border bg-background pl-9 pr-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="h-10 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-60"
            >
              Search
            </button>
          </form>
        </div>

        {error && (
          <div className="m-5 flex items-start gap-3 rounded-lg border border-red-500/20 bg-red-500/5 p-4 text-sm text-red-600">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            <div>
              <p className="font-medium">Failed to load approval queue</p>
              <p className="mt-1">{error}</p>
            </div>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-5 py-4 font-medium">Quotation</th>
                <th className="px-5 py-4 font-medium">Customer</th>
                <th className="px-5 py-4 font-medium">Submitted Date</th>
                <th className="px-5 py-4 text-right font-medium">
                  Total Amount
                </th>
                <th className="px-5 py-4 text-center font-medium">
                  Status
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-border">
              {loading ? (
                Array.from({ length: 5 }).map((_, index) => (
                  <tr key={index}>
                    {Array.from({ length: 5 }).map((__, cellIndex) => (
                      <td key={cellIndex} className="px-5 py-5">
                        <div className="h-4 w-28 animate-pulse rounded bg-muted" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : quotations.length > 0 ? (
                quotations.map((quotation) => (
                  <tr
                    key={quotation.id}
                    className="transition-colors hover:bg-muted/30"
                  >
                    <td className="px-5 py-4">
                    <Link
                        href={`/approvals/${quotation.id}`}
                        className="font-medium text-primary transition-colors hover:underline"
                    >
                        {quotation.quotation_number}
                    </Link>
                    <p className="mt-1 max-w-[240px] truncate text-xs text-muted-foreground">
                        {quotation.subject}
                    </p>
                    </td>

                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span>
                          {quotation.customer?.company_name || "-"}
                        </span>
                      </div>
                    </td>

                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <CalendarDays className="h-4 w-4" />
                        {formatDate(quotation.sent_at || quotation.issue_date)}
                      </div>
                    </td>

                    <td className="px-5 py-4 text-right font-medium tabular-nums">
                      {formatCurrency(
                        quotation.total_amount,
                        quotation.currency || "IDR",
                      )}
                    </td>

                    <td className="px-5 py-4 text-center">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                        Pending Approval
                      </span>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="px-5 py-16 text-center">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                      <ClipboardCheck className="h-6 w-6 text-muted-foreground" />
                    </div>
                    <p className="mt-4 font-medium">
                      No pending approvals
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {search
                        ? "No quotations match your search."
                        : "There are currently no quotations waiting for approval."}
                    </p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">
            {meta.total > 0
              ? `Showing ${meta.from ?? 0}–${meta.to ?? 0} of ${meta.total} quotations`
              : "No quotations to display"}
          </p>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              disabled={loading || page <= 1}
              className="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
            >
              <ChevronLeft className="h-4 w-4" />
              Previous
            </button>

            <span className="px-2 text-sm text-muted-foreground">
              {meta.current_page} / {meta.last_page}
            </span>

            <button
              type="button"
              onClick={() =>
                setPage((current) =>
                  Math.min(meta.last_page, current + 1),
                )
              }
              disabled={loading || page >= meta.last_page}
              className="inline-flex h-9 items-center gap-1 rounded-lg border border-border px-3 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
            >
              Next
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}