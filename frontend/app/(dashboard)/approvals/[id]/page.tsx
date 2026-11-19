"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import {
  ArrowLeft,
  Check,
  X,
  RefreshCw,
  LoaderCircle,
  FileText,
  User,
  CalendarDays,
  Building2,
  MessageSquare,
  AlertCircle,
} from "lucide-react";

import { approvalService } from "@/lib/approval";

type ApprovalDetail = {
  id: number;
  quotation_number: string;
  subject: string;
  description?: string | null;
  issue_date: string;
  valid_until: string;
  sent_at?: string | null;
  currency: string;
  subtotal: number;
  discount_amount?: number;
  tax_percentage?: number;
  tax_amount: number;
  total_amount: number;
  status: string;
  customer_notes?: string | null;
  customer?: {
    company_name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    billing_address?: string | null;
  } | null;
  created_by?: {
    name: string;
    email?: string;
  } | null;
  items?: {
    id: number;
    description: string;
    quantity: number;
    unit: string;
    unit_price: number;
    discount_amount: number;
    tax_percentage: number;
    line_total: number;
    service?: {
      name: string;
    } | null;
  }[];
  terms?: {
    id: number;
    title: string;
    content: string;
    is_required: boolean;
  }[];
  approvals?: {
    id: number;
    status: string;
    comments?: string | null;
    approver?: {
      name: string;
    } | null;
  }[];
};

type Decision = "approve" | "reject";

function formatCurrency(value: number, currency = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatDate(value?: string | null) {
  if (!value) return "-";

  return new Date(value).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  });
}

function getStatusLabel(status: string) {
  const normalizedStatus = String(status ?? "")
    .toLowerCase()
    .trim()
    .replace(/[\s-]+/g, "_");

  const labels: Record<string, string> = {
    draft: "Draft",
    pending_approval: "Menunggu Approval",
    approved: "Disetujui",
    rejected: "Ditolak",
  };

  return labels[normalizedStatus] ?? status;
}

export default function ApprovalDetailPage() {
  const params = useParams();
  const router = useRouter();
  const quotationId = Number(params.id);

  const [quotation, setQuotation] = useState<ApprovalDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [decision, setDecision] = useState<Decision | null>(null);
  const [comments, setComments] = useState("");

  const loadDetail = useCallback(async () => {
    if (!Number.isInteger(quotationId) || quotationId <= 0) {
      setError("ID quotation tidak valid.");
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError("");

      const result = await approvalService.getDetail(quotationId);
      setQuotation(result as unknown as ApprovalDetail);
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
    void loadDetail();
  }, [loadDetail]);

  const handleDecision = async () => {
    if (!quotation || !decision) return;

    if (decision === "reject" && comments.trim().length < 3) {
      setError("Komentar penolakan minimal 3 karakter.");
      return;
    }

    try {
      setProcessing(true);
      setError("");
      setNotice("");

      if (decision === "approve") {
        await approvalService.approve(quotation.id, comments.trim());
        setNotice("Quotation berhasil disetujui.");
      } else {
        await approvalService.reject(quotation.id, comments.trim());
        setNotice("Quotation berhasil ditolak.");
      }

      setDecision(null);
      setComments("");

      await loadDetail();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Keputusan approval gagal diproses.",
      );
    } finally {
      setProcessing(false);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <LoaderCircle className="h-7 w-7 animate-spin text-primary" />
        <span className="ml-3 text-sm text-muted-foreground">
          Memuat detail quotation...
        </span>
      </div>
    );
  }

  if (error && !quotation) {
    return (
      <div className="space-y-5">
        <Link
          href="/approvals"
          className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali ke Approval Queue
        </Link>

        <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-6">
          <div className="flex items-center gap-2 font-medium text-destructive">
            <AlertCircle className="h-5 w-5" />
            Gagal memuat quotation
          </div>
          <p className="mt-2 text-sm text-muted-foreground">{error}</p>
          <button
            onClick={() => void loadDetail()}
            className="mt-4 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm hover:bg-muted"
          >
            <RefreshCw className="h-4 w-4" />
            Coba Lagi
          </button>
        </div>
      </div>
    );
  }

  if (!quotation) return null;

const quotationStatus = String(quotation.status ?? "")
  .toLowerCase()
  .trim()
  .replace(/[\s-]+/g, "_");

const canDecide = quotationStatus === "pending_approval";
const isFinalized = ["approved", "rejected"].includes(quotationStatus);

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <Link
            href="/approvals"
            className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="h-4 w-4" />
            Approval Queue
          </Link>

          <h1 className="text-2xl font-semibold tracking-tight">
            Approval Detail
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Tinjau informasi quotation sebelum memberikan keputusan.
          </p>
        </div>

        <button
          onClick={() => void loadDetail()}
          disabled={loading}
          className="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
        >
          <RefreshCw className="h-4 w-4" />
          Refresh
        </button>
      </div>

      {notice && (
        <div className="rounded-lg border border-green-500/30 bg-green-500/5 px-4 py-3 text-sm text-green-700 dark:text-green-400">
          {notice}
        </div>
      )}

      {error && (
        <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {error}
        </div>
      )}

      <div className="rounded-xl border bg-card p-5">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
          <div>
            <p className="text-sm text-muted-foreground">Quotation Number</p>
            <h2 className="mt-1 text-xl font-semibold">
              {quotation.quotation_number}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {quotation.subject}
            </p>
          </div>

            <span
            className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-medium ${
                quotationStatus === "pending_approval"
                ? "bg-amber-500/10 text-amber-600"
                : quotationStatus === "approved"
                    ? "bg-green-500/10 text-green-600"
                    : quotationStatus === "rejected"
                    ? "bg-red-500/10 text-red-600"
                    : "bg-muted text-muted-foreground"
            }`}
            >
            {getStatusLabel(quotation.status)}
            </span>
        </div>
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <section className="rounded-xl border bg-card p-5">
          <div className="mb-4 flex items-center gap-2">
            <Building2 className="h-5 w-5 text-muted-foreground" />
            <h3 className="font-semibold">Customer Information</h3>
          </div>

          <div className="space-y-3 text-sm">
            <div>
              <p className="text-muted-foreground">Company Name</p>
              <p className="mt-1 font-medium">
                {quotation.customer?.company_name ?? "-"}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">Contact Person</p>
              <p className="mt-1">
                {quotation.customer?.contact_person ?? "-"}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">Email</p>
              <p className="mt-1">{quotation.customer?.email ?? "-"}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Phone</p>
              <p className="mt-1">{quotation.customer?.phone ?? "-"}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Billing Address</p>
              <p className="mt-1">
                {quotation.customer?.billing_address ?? "-"}
              </p>
            </div>
          </div>
        </section>

        <section className="rounded-xl border bg-card p-5">
          <div className="mb-4 flex items-center gap-2">
            <FileText className="h-5 w-5 text-muted-foreground" />
            <h3 className="font-semibold">Quotation Information</h3>
          </div>

          <div className="space-y-3 text-sm">
            <div>
              <p className="text-muted-foreground">Issue Date</p>
              <p className="mt-1 font-medium">
                {formatDate(quotation.issue_date)}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">Valid Until</p>
              <p className="mt-1">{formatDate(quotation.valid_until)}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Submitted At</p>
              <p className="mt-1">{formatDate(quotation.sent_at)}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Submitted By</p>
              <p className="mt-1">
                {quotation.created_by?.name ?? "-"}
              </p>
            </div>
            {quotation.description && (
              <div>
                <p className="text-muted-foreground">Description</p>
                <p className="mt-1 whitespace-pre-wrap">
                  {quotation.description}
                </p>
              </div>
            )}
          </div>
        </section>
      </div>

      <section className="overflow-hidden rounded-xl border bg-card">
        <div className="border-b p-5">
          <h3 className="font-semibold">Quotation Items</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Rincian layanan dan biaya quotation.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Description</th>
                <th className="px-5 py-3 text-right font-medium">Qty</th>
                <th className="px-5 py-3 text-right font-medium">Unit Price</th>
                <th className="px-5 py-3 text-right font-medium">Discount</th>
                <th className="px-5 py-3 text-right font-medium">Tax</th>
                <th className="px-5 py-3 text-right font-medium">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {quotation.items?.map((item) => (
                <tr key={item.id}>
                  <td className="px-5 py-4">
                    <p className="font-medium">{item.description}</p>
                    {item.service?.name && (
                      <p className="mt-1 text-xs text-muted-foreground">
                        {item.service.name}
                      </p>
                    )}
                  </td>
                  <td className="px-5 py-4 text-right">
                    {item.quantity} {item.unit}
                  </td>
                  <td className="px-5 py-4 text-right">
                    {formatCurrency(item.unit_price, quotation.currency)}
                  </td>
                  <td className="px-5 py-4 text-right">
                    {formatCurrency(item.discount_amount, quotation.currency)}
                  </td>
                  <td className="px-5 py-4 text-right">
                    {item.tax_percentage}%
                  </td>
                  <td className="px-5 py-4 text-right font-medium">
                    {formatCurrency(item.line_total, quotation.currency)}
                  </td>
                </tr>
              ))}

              {!quotation.items?.length && (
                <tr>
                  <td
                    colSpan={6}
                    className="px-5 py-8 text-center text-muted-foreground"
                  >
                    Tidak ada item quotation.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="flex justify-end border-t p-5">
          <div className="w-full space-y-2 sm:max-w-xs">
            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Subtotal</span>
              <span>{formatCurrency(quotation.subtotal, quotation.currency)}</span>
            </div>
            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Discount</span>
              <span>
                {formatCurrency(
                  quotation.discount_amount ?? 0,
                  quotation.currency,
                )}
              </span>
            </div>
            <div className="flex justify-between gap-4 text-sm">
              <span className="text-muted-foreground">Tax</span>
              <span>
                {formatCurrency(quotation.tax_amount, quotation.currency)}
              </span>
            </div>
            <div className="flex justify-between gap-4 border-t pt-3 font-semibold">
              <span>Total Amount</span>
              <span className="text-primary">
                {formatCurrency(quotation.total_amount, quotation.currency)}
              </span>
            </div>
          </div>
        </div>
      </section>

      {quotation.terms && quotation.terms.length > 0 && (
        <section className="rounded-xl border bg-card p-5">
          <h3 className="mb-4 font-semibold">Terms & Conditions</h3>
          <div className="space-y-4">
            {quotation.terms.map((term, index) => (
              <div key={term.id}>
                <p className="text-sm font-medium">
                  {index + 1}. {term.title}
                  {term.is_required && (
                    <span className="ml-2 text-xs text-muted-foreground">
                      (Required)
                    </span>
                  )}
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">
                  {term.content}
                </p>
              </div>
            ))}
          </div>
        </section>
      )}

      {quotation.customer_notes && (
        <section className="rounded-xl border bg-card p-5">
          <h3 className="mb-2 font-semibold">Customer Notes</h3>
          <p className="whitespace-pre-wrap text-sm text-muted-foreground">
            {quotation.customer_notes}
          </p>
        </section>
      )}

    <section className="rounded-xl border bg-card p-5">
    <div className="mb-4 flex items-center gap-2">
        <MessageSquare className="h-5 w-5 text-muted-foreground" />
        <h3 className="font-semibold">Approval Decision</h3>
    </div>

    <p className="mb-4 text-sm text-muted-foreground">
        Periksa seluruh informasi quotation sebelum memberikan keputusan.
    </p>

    {!canDecide ? (
        <div className="rounded-lg bg-muted/40 p-4 text-sm">
        <p className="font-medium">
            Status: {getStatusLabel(quotation.status)}
        </p>
        <p className="mt-1 text-muted-foreground">
            {isFinalized
            ? "Quotation ini sudah memiliki keputusan approval."
            : `Status API saat ini: "${quotation.status}". Quotation belum terdeteksi sebagai menunggu approval.`}
        </p>
        </div>
    ) : (
        <>
        <div className="flex flex-col gap-3 sm:flex-row">
            <button
            onClick={() => {
                setDecision("approve");
                setComments("");
                setError("");
            }}
            className="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700"
            >
            <Check className="h-4 w-4" />
            Approve
            </button>

            <button
            onClick={() => {
                setDecision("reject");
                setComments("");
                setError("");
            }}
            className="inline-flex items-center justify-center gap-2 rounded-lg bg-destructive px-5 py-2.5 text-sm font-medium text-white hover:opacity-90"
            >
            <X className="h-4 w-4" />
            Reject
            </button>
        </div>

        {decision && (
            <div className="mt-5 space-y-4 rounded-lg border bg-muted/20 p-4">
            <div>
                <h4 className="font-medium">
                {decision === "approve"
                    ? "Confirm Approval"
                    : "Confirm Rejection"}
                </h4>
                <p className="mt-1 text-sm text-muted-foreground">
                {decision === "approve"
                    ? "Komentar bersifat opsional."
                    : "Komentar wajib diisi untuk menolak quotation."}
                </p>
            </div>

            <textarea
                value={comments}
                onChange={(event) => setComments(event.target.value)}
                rows={3}
                maxLength={2000}
                placeholder="Tulis komentar keputusan..."
                className="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/30"
            />

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                onClick={() => {
                    setDecision(null);
                    setComments("");
                    setError("");
                }}
                disabled={processing}
                className="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                >
                Batal
                </button>

                <button
                onClick={() => void handleDecision()}
                disabled={
                    processing ||
                    (decision === "reject" && comments.trim().length < 3)
                }
                className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50 ${
                    decision === "approve"
                    ? "bg-green-600 hover:bg-green-700"
                    : "bg-destructive hover:opacity-90"
                }`}
                >
                {processing ? (
                    <LoaderCircle className="h-4 w-4 animate-spin" />
                ) : decision === "approve" ? (
                    <Check className="h-4 w-4" />
                ) : (
                    <X className="h-4 w-4" />
                )}
                {processing
                    ? "Memproses..."
                    : decision === "approve"
                    ? "Konfirmasi Approval"
                    : "Konfirmasi Reject"}
                </button>
            </div>
            </div>
        )}
        </>
    )}
    </section>

      <div className="flex justify-start">
        <Link
          href="/approvals"
          className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
        >
          <ArrowLeft className="h-4 w-4" />
          Kembali ke Approval Queue
        </Link>
      </div>
    </div>
  );
}
