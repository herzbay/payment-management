"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import {
  AlertCircle,
  ArrowLeft,
  Building2,
  CalendarDays,
  ClipboardCheck,
  FileText,
  LoaderCircle,
  UserRound,
} from "lucide-react";

import { approvalService } from "@/lib/approval";

interface Customer {
  company_name?: string;
  customer_code?: string;
  contact_person?: string | null;
  email?: string | null;
  phone?: string | null;
  billing_address?: string | null;
}

interface QuotationItem {
  id: number;
  description: string;
  quantity: number;
  unit: string;
  unit_price: number;
  discount_amount: number;
  tax_percentage: number;
  line_total: number;
  service?: {
    name?: string;
    service_code?: string;
  } | null;
}

interface QuotationTerm {
  id: number;
  title: string;
  content: string;
  is_required: boolean;
}

interface ApprovalRecord {
  id: number;
  status: string;
  comments?: string | null;
  approval_level?: number;
  approver?: {
    name?: string;
  } | null;
}

interface QuotationDetail {
  id: number;
  quotation_number: string;
  subject: string;
  description?: string | null;
  issue_date: string;
  valid_until: string;
  sent_at?: string | null;
  currency: string;
  subtotal: number;
  total_discount: number;
  tax_percentage: number;
  tax_amount: number;
  total_amount: number;
  status: string;
  internal_notes?: string | null;
  customer_notes?: string | null;
  customer?: Customer | null;
  items?: QuotationItem[];
  terms?: QuotationTerm[];
  approvals?: ApprovalRecord[];
  created_by?: {
    name?: string;
  } | null;
}

function formatCurrency(amount: number, currency = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(amount) || 0);
}

function formatDate(date?: string | null) {
  if (!date) return "-";

  const parsed = new Date(date);

  if (Number.isNaN(parsed.getTime())) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  }).format(parsed);
}

function formatNumber(value: number) {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(Number(value) || 0);
}

export default function ApprovalDetailPage() {
  const params = useParams<{ id: string }>();
  const quotationId = Number(params.id);

  const [quotation, setQuotation] = useState<QuotationDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadDetail = useCallback(async () => {
    if (!Number.isInteger(quotationId) || quotationId <= 0) {
      setError("Quotation ID tidak valid.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError("");

    try {
      const result = await approvalService.getDetail(quotationId);
      setQuotation(result as unknown as QuotationDetail);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Gagal memuat detail quotation.",
      );
    } finally {
      setLoading(false);
    }
  }, [quotationId]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadDetail();
    }, 0);

    return () => window.clearTimeout(timer);
  }, [loadDetail]);

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <div className="flex items-center gap-3 text-sm text-muted-foreground">
          <LoaderCircle className="h-5 w-5 animate-spin" />
          Loading quotation details...
        </div>
      </div>
    );
  }

  if (error || !quotation) {
    return (
      <div className="space-y-5">
        <Link
          href="/approvals"
          className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Approval Queue
        </Link>

        <div className="flex items-start gap-3 rounded-xl border border-red-500/20 bg-red-500/5 p-5 text-sm text-red-600">
          <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
          <div>
            <p className="font-semibold">Failed to load quotation</p>
            <p className="mt-1">{error || "Quotation tidak ditemukan."}</p>
          </div>
        </div>
      </div>
    );
  }

  const items = quotation.items ?? [];
  const terms = quotation.terms ?? [];
  const approvals = quotation.approvals ?? [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link
            href="/approvals"
            className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to Approval Queue
          </Link>

          <div className="flex items-center gap-2">
            <ClipboardCheck className="h-6 w-6 text-primary" />
            <h1 className="text-2xl font-semibold tracking-tight">
              Approval Detail
            </h1>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Review quotation information before making an approval decision.
          </p>
        </div>

        <span className="inline-flex w-fit items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-400">
          <span className="h-2 w-2 rounded-full bg-amber-500" />
          {quotation.status.replaceAll("_", " ")}
        </span>
      </div>

      {/* Quotation Overview */}
      <section className="rounded-xl border border-border bg-card p-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="text-sm text-muted-foreground">Quotation Number</p>
            <h2 className="mt-1 text-xl font-semibold">
              {quotation.quotation_number}
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
              {quotation.subject}
            </p>
          </div>

          <div className="rounded-lg bg-primary/5 px-4 py-3 sm:text-right">
            <p className="text-xs text-muted-foreground">Total Amount</p>
            <p className="mt-1 text-lg font-semibold text-primary">
              {formatCurrency(quotation.total_amount, quotation.currency)}
            </p>
          </div>
        </div>

        {quotation.description && (
          <div className="mt-5 border-t border-border pt-4">
            <p className="text-sm font-medium">Description</p>
            <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
              {quotation.description}
            </p>
          </div>
        )}
      </section>

      {/* Customer and Dates */}
      <div className="grid gap-5 lg:grid-cols-2">
        <section className="rounded-xl border border-border bg-card p-5">
          <div className="mb-4 flex items-center gap-2">
            <Building2 className="h-5 w-5 text-primary" />
            <h2 className="font-semibold">Customer Information</h2>
          </div>

          <div className="space-y-4">
            <div>
              <p className="text-xs text-muted-foreground">Company Name</p>
              <p className="mt-1 text-sm font-medium">
                {quotation.customer?.company_name || "-"}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Customer Code</p>
              <p className="mt-1 text-sm">
                {quotation.customer?.customer_code || "-"}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Contact Person</p>
              <p className="mt-1 text-sm">
                {quotation.customer?.contact_person || "-"}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Email / Phone</p>
              <p className="mt-1 text-sm">
                {quotation.customer?.email || "-"}
                {quotation.customer?.phone
                  ? ` / ${quotation.customer.phone}`
                  : ""}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Billing Address</p>
              <p className="mt-1 whitespace-pre-wrap text-sm">
                {quotation.customer?.billing_address || "-"}
              </p>
            </div>
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card p-5">
          <div className="mb-4 flex items-center gap-2">
            <CalendarDays className="h-5 w-5 text-primary" />
            <h2 className="font-semibold">Quotation Dates</h2>
          </div>

          <div className="space-y-4">
            <div>
              <p className="text-xs text-muted-foreground">Issue Date</p>
              <p className="mt-1 text-sm font-medium">
                {formatDate(quotation.issue_date)}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Valid Until</p>
              <p className="mt-1 text-sm font-medium">
                {formatDate(quotation.valid_until)}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Submitted Date</p>
              <p className="mt-1 text-sm font-medium">
                {formatDate(quotation.sent_at)}
              </p>
            </div>

            <div>
              <p className="text-xs text-muted-foreground">Submitted By</p>
              <p className="mt-1 flex items-center gap-2 text-sm font-medium">
                <UserRound className="h-4 w-4 text-muted-foreground" />
                {quotation.created_by?.name || "-"}
              </p>
            </div>
          </div>
        </section>
      </div>

      {/* Quotation Items */}
      <section className="overflow-hidden rounded-xl border border-border bg-card">
        <div className="border-b border-border p-5">
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            <h2 className="font-semibold">Quotation Items</h2>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Services and pricing included in this quotation.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[750px] text-left text-sm">
            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Service / Description</th>
                <th className="px-5 py-3 text-right font-medium">Quantity</th>
                <th className="px-5 py-3 text-right font-medium">Unit Price</th>
                <th className="px-5 py-3 text-right font-medium">Discount</th>
                <th className="px-5 py-3 text-right font-medium">Tax</th>
                <th className="px-5 py-3 text-right font-medium">Line Total</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-border">
              {items.length > 0 ? (
                items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-5 py-4">
                      <p className="font-medium">
                        {item.service?.name || item.description}
                      </p>
                      {item.service?.service_code && (
                        <p className="mt-1 text-xs text-muted-foreground">
                          {item.service.service_code}
                        </p>
                      )}
                      {item.service?.name && (
                        <p className="mt-1 text-xs text-muted-foreground">
                          {item.description}
                        </p>
                      )}
                    </td>
                    <td className="px-5 py-4 text-right tabular-nums">
                      {formatNumber(item.quantity)} {item.unit}
                    </td>
                    <td className="px-5 py-4 text-right tabular-nums">
                      {formatCurrency(item.unit_price, quotation.currency)}
                    </td>
                    <td className="px-5 py-4 text-right tabular-nums">
                      {formatCurrency(item.discount_amount, quotation.currency)}
                    </td>
                    <td className="px-5 py-4 text-right tabular-nums">
                      {formatNumber(item.tax_percentage)}%
                    </td>
                    <td className="px-5 py-4 text-right font-medium tabular-nums">
                      {formatCurrency(item.line_total, quotation.currency)}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td
                    colSpan={6}
                    className="px-5 py-10 text-center text-muted-foreground"
                  >
                    No quotation items available.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Totals */}
        <div className="flex justify-end border-t border-border p-5">
          <div className="w-full space-y-3 sm:max-w-sm">
            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Subtotal</span>
              <span className="tabular-nums">
                {formatCurrency(quotation.subtotal, quotation.currency)}
              </span>
            </div>

            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Total Discount</span>
              <span className="tabular-nums">
                -{formatCurrency(quotation.total_discount, quotation.currency)}
              </span>
            </div>

            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Tax</span>
              <span className="tabular-nums">
                {formatCurrency(quotation.tax_amount, quotation.currency)}
              </span>
            </div>

            <div className="flex justify-between gap-4 border-t border-border pt-3 font-semibold">
              <span>Total Amount</span>
              <span className="text-primary tabular-nums">
                {formatCurrency(quotation.total_amount, quotation.currency)}
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* Terms */}
      <section className="rounded-xl border border-border bg-card p-5">
        <h2 className="font-semibold">Terms & Conditions</h2>

        {terms.length > 0 ? (
          <div className="mt-4 space-y-4">
            {terms.map((term, index) => (
              <div key={term.id} className="flex gap-3">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                  {index + 1}
                </span>
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-medium">{term.title}</p>
                    {term.is_required && (
                      <span className="rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-medium text-blue-600 dark:text-blue-400">
                        Required
                      </span>
                    )}
                  </div>
                  <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                    {term.content}
                  </p>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">
            No terms and conditions provided.
          </p>
        )}
      </section>

      {/* Notes */}
      {(quotation.customer_notes || quotation.internal_notes) && (
        <section className="grid gap-5 sm:grid-cols-2">
          {quotation.customer_notes && (
            <div className="rounded-xl border border-border bg-card p-5">
              <h2 className="font-semibold">Customer Notes</h2>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                {quotation.customer_notes}
              </p>
            </div>
          )}

          {quotation.internal_notes && (
            <div className="rounded-xl border border-border bg-card p-5">
              <h2 className="font-semibold">Internal Notes</h2>
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                {quotation.internal_notes}
              </p>
            </div>
          )}
        </section>
      )}

      {/* Approval History */}
      <section className="rounded-xl border border-border bg-card p-5">
        <h2 className="font-semibold">Approval History</h2>

        {approvals.length > 0 ? (
          <div className="mt-4 space-y-4">
            {approvals.map((approval) => (
              <div
                key={approval.id}
                className="flex flex-col gap-2 border-l-2 border-primary/30 pl-4"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-sm font-medium">
                    {approval.approver?.name || "Assigned Approver"}
                  </span>
                  <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                    {approval.status}
                  </span>
                </div>
                <p className="text-xs text-muted-foreground">
                  Approval Level {approval.approval_level ?? 1}
                </p>
                {approval.comments && (
                  <p className="text-sm text-muted-foreground">
                    {approval.comments}
                  </p>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">
            No approval history available.
          </p>
        )}
      </section>

      {/* Decision actions will be added in the next step */}
      <div className="rounded-xl border border-dashed border-border p-5">
        <p className="text-sm font-medium">Approval Decision</p>
        <p className="mt-1 text-sm text-muted-foreground">
          Approve and Reject actions will be available here in the next step.
        </p>
      </div>
    </div>
  );
}