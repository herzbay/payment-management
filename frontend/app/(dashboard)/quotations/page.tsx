"use client";

import {
  useCallback,
  useEffect,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import {
  FileText,
  Building2,
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  Eye,
  LoaderCircle,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  X,
  ReceiptText,
  Calculator,
  ClipboardList,
  Send,
} from "lucide-react";

import { quotationService } from "@/lib/quotation";
import { customerService, serviceService } from "@/lib/master-data";
import SubmitApprovalDialog from "@/components/quotations/submit-approval-dialog";

import type {
  Customer,
  Service,
  PaginationMeta,
} from "@/types/master-data";

import type {
  Quotation,
  QuotationFilters,
  QuotationItemPayload,
  QuotationPayload,
  QuotationStatus,
} from "@/types/quotation";

type StatusFilter = QuotationStatus | "all";

interface QuotationFormItem {
  service_id: string;
  description: string;
  quantity: string;
  unit: string;
  unit_price: string;
  discount_amount: string;
  tax_percentage: string;
}

interface QuotationFormTerm {
  title: string;
  content: string;
  is_required: boolean;
}

interface QuotationFormState {
  customer_id: string;
  subject: string;
  description: string;
  issue_date: string;
  valid_until: string;
  currency: string;
  internal_notes: string;
  customer_notes: string;
  items: QuotationFormItem[];
  terms: QuotationFormTerm[];
}

interface ApprovalQuotation {
  id: number;
  quotation_number: string;
}

const EMPTY_PAGINATION: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: null,
  to: null,
};

const createEmptyItem = (): QuotationFormItem => ({
  service_id: "",
  description: "",
  quantity: "1",
  unit: "unit",
  unit_price: "",
  discount_amount: "0",
  tax_percentage: "0",
});

const createEmptyForm = (): QuotationFormState => ({
  customer_id: "",
  subject: "",
  description: "",
  issue_date: new Date().toISOString().slice(0, 10),
  valid_until: "",
  currency: "IDR",
  internal_notes: "",
  customer_notes: "",
  items: [createEmptyItem()],
  terms: [],
});

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Terjadi kesalahan. Silakan coba kembali.";
}

function formatCurrency(value: number, currency = "IDR"): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatDate(value: string | null | undefined): string {
  if (!value) return "-";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function getStatusLabel(status: QuotationStatus): string {
  const labels: Record<QuotationStatus, string> = {
    draft: "Draft",
    pending_approval: "Menunggu Approval",
    approved: "Disetujui",
    rejected: "Ditolak",
  };

  return labels[status] ?? status;
}

function StatusBadge({ status }: { status: QuotationStatus }) {
  const styles: Record<QuotationStatus, string> = {
    draft: "bg-secondary text-muted-foreground",
    pending_approval: "bg-warning-background text-warning",
    approved: "bg-success-background text-success",
    rejected: "bg-danger-background text-danger",
  };

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${styles[status]}`}
    >
      <span
        className={`h-1.5 w-1.5 rounded-full ${
          status === "approved"
            ? "bg-success"
            : status === "rejected"
              ? "bg-danger"
              : status === "pending_approval"
                ? "bg-warning"
                : "bg-muted-foreground"
        }`}
      />
      {getStatusLabel(status)}
    </span>
  );
}

function Field({
  label,
  children,
  required = false,
}: {
  label: string;
  children: ReactNode;
  required?: boolean;
}) {
  return (
    <label className="block space-y-1.5">
      <span className="text-xs font-semibold text-foreground">
        {label}
        {required && <span className="ml-1 text-danger">*</span>}
      </span>
      {children}
    </label>
  );
}

const inputClass =
  "h-10 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-60";

const textareaClass =
  "min-h-20 w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-ring/20";

function calculateItemTotal(item: QuotationFormItem): number {
  const quantity = Math.max(0, Number(item.quantity) || 0);
  const unitPrice = Math.max(0, Number(item.unit_price) || 0);
  const discount = Math.max(0, Number(item.discount_amount) || 0);
  const tax = Math.max(0, Number(item.tax_percentage) || 0);

  const baseAmount = Math.max(0, quantity * unitPrice - discount);

  return baseAmount + (baseAmount * tax) / 100;
}

function calculateQuotationTotals(items: QuotationFormItem[]) {
  const subtotal = items.reduce((total, item) => {
    return (
      total +
      Math.max(0, Number(item.quantity) || 0) *
        Math.max(0, Number(item.unit_price) || 0)
    );
  }, 0);

  const totalDiscount = items.reduce((total, item) => {
    return total + Math.max(0, Number(item.discount_amount) || 0);
  }, 0);

  const taxAmount = items.reduce((total, item) => {
    const quantity = Math.max(0, Number(item.quantity) || 0);
    const unitPrice = Math.max(0, Number(item.unit_price) || 0);
    const discount = Math.max(0, Number(item.discount_amount) || 0);
    const tax = Math.max(0, Number(item.tax_percentage) || 0);
    const taxableAmount = Math.max(0, quantity * unitPrice - discount);

    return total + (taxableAmount * tax) / 100;
  }, 0);

  return {
    subtotal,
    totalDiscount,
    taxAmount,
    total: subtotal - totalDiscount + taxAmount,
  };
}

function mapQuotationToForm(
  quotation: Quotation,
): QuotationFormState {
  return {
    customer_id: String(quotation.customer_id),
    subject: quotation.subject ?? "",
    description: quotation.description ?? "",
    issue_date: quotation.issue_date?.slice(0, 10) ?? "",
    valid_until: quotation.valid_until?.slice(0, 10) ?? "",
    currency: quotation.currency || "IDR",
    internal_notes: quotation.internal_notes ?? "",
    customer_notes: quotation.customer_notes ?? "",
    items:
      quotation.items?.map((item) => ({
        service_id: String(item.service_id),
        description: item.description ?? "",
        quantity: String(item.quantity ?? 1),
        unit: item.unit ?? "unit",
        unit_price: String(item.unit_price ?? 0),
        discount_amount: String(item.discount_amount ?? 0),
        tax_percentage: String(item.tax_percentage ?? 0),
      })) ?? [createEmptyItem()],
    terms:
      quotation.terms?.map((term) => ({
        title: term.title,
        content: term.content,
        is_required: term.is_required,
      })) ?? [],
  };
}

export default function QuotationsPage() {
  const [quotations, setQuotations] = useState<Quotation[]>([]);
  const [pagination, setPagination] =
    useState<PaginationMeta>(EMPTY_PAGINATION);

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [isMasterDataLoading, setIsMasterDataLoading] = useState(true);

  const [searchInput, setSearchInput] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [statusFilter, setStatusFilter] =
    useState<StatusFilter>("all");
  const [page, setPage] = useState(1);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingQuotation, setEditingQuotation] =
    useState<Quotation | null>(null);
  const [formData, setFormData] =
    useState<QuotationFormState>(createEmptyForm());
  const [isSaving, setIsSaving] = useState(false);
  const [formError, setFormError] = useState("");

  const [selectedQuotation, setSelectedQuotation] =
    useState<Quotation | null>(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);

  // State approval harus berada di dalam komponen React.
  const [selectedApprovalQuotation, setSelectedApprovalQuotation] =
    useState<ApprovalQuotation | null>(null);

  const [notice, setNotice] = useState<{
    type: "success" | "error";
    message: string;
  } | null>(null);

  const loadQuotations = useCallback(
    async (showLoading = true) => {
      if (showLoading) {
        setIsLoading(true);
      }

      setError("");

      const filters: QuotationFilters = {
        search: appliedSearch || undefined,
        status: statusFilter === "all" ? undefined : statusFilter,
        page,
        per_page: 10,
      };

      try {
        const result = await quotationService.getAll(filters);
        setQuotations(result.data);
        setPagination(result.meta);
      } catch (err) {
        setError(getErrorMessage(err));

        if (showLoading) {
          setQuotations([]);
          setPagination(EMPTY_PAGINATION);
        }
      } finally {
        if (showLoading) {
          setIsLoading(false);
        }
      }
    },
    [appliedSearch, statusFilter, page],
  );

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadQuotations(true);
    }, 200);

    return () => window.clearTimeout(timeoutId);
  }, [loadQuotations]);

  const loadMasterData = useCallback(async () => {
    setIsMasterDataLoading(true);

    try {
      const [customerResult, serviceResult] = await Promise.all([
        customerService.getAll({
          is_active: true,
          page: 1,
          per_page: 100,
        }),
        serviceService.getAll({
          is_active: true,
          page: 1,
          per_page: 100,
        }),
      ]);

      setCustomers(customerResult.data);
      setServices(serviceResult.data);
    } catch (err) {
      setNotice({
        type: "error",
        message: `Gagal memuat customer atau service: ${getErrorMessage(err)}`,
      });
    } finally {
      setIsMasterDataLoading(false);
    }
  }, []);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadMasterData();
    }, 200);

    return () => window.clearTimeout(timeoutId);
  }, [loadMasterData]);

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setAppliedSearch(searchInput.trim());
  };

  const handleStatusFilter = (value: StatusFilter) => {
    setStatusFilter(value);
    setPage(1);
  };

  const updateFormField = <K extends keyof QuotationFormState>(
    key: K,
    value: QuotationFormState[K],
  ) => {
    setFormData((previous) => ({
      ...previous,
      [key]: value,
    }));
  };

  const updateItemField = (
    index: number,
    key: keyof QuotationFormItem,
    value: string,
  ) => {
    setFormData((previous) => ({
      ...previous,
      items: previous.items.map((item, itemIndex) =>
        itemIndex === index
          ? { ...item, [key]: value }
          : item,
      ),
    }));
  };

  const handleServiceChange = (index: number, serviceId: string) => {
    const selectedService = services.find(
      (service) => service.id === Number(serviceId),
    );

    setFormData((previous) => ({
      ...previous,
      items: previous.items.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              service_id: serviceId,
              description: selectedService?.name ?? item.description,
              unit: selectedService?.default_unit ?? item.unit,
              unit_price:
                selectedService?.default_unit_price !== undefined
                  ? String(selectedService.default_unit_price)
                  : item.unit_price,
            }
          : item,
      ),
    }));
  };

  const addItem = () => {
    setFormData((previous) => ({
      ...previous,
      items: [...previous.items, createEmptyItem()],
    }));
  };

  const removeItem = (index: number) => {
    setFormData((previous) => ({
      ...previous,
      items:
        previous.items.length > 1
          ? previous.items.filter((_, itemIndex) => itemIndex !== index)
          : previous.items,
    }));
  };

  const addTerm = () => {
    setFormData((previous) => ({
      ...previous,
      terms: [
        ...previous.terms,
        {
          title: "",
          content: "",
          is_required: true,
        },
      ],
    }));
  };

  const updateTermField = (
    index: number,
    key: keyof QuotationFormTerm,
    value: string | boolean,
  ) => {
    setFormData((previous) => ({
      ...previous,
      terms: previous.terms.map((term, termIndex) =>
        termIndex === index
          ? { ...term, [key]: value }
          : term,
      ),
    }));
  };

  const removeTerm = (index: number) => {
    setFormData((previous) => ({
      ...previous,
      terms: previous.terms.filter(
        (_, termIndex) => termIndex !== index,
      ),
    }));
  };

  const openCreateForm = () => {
    setEditingQuotation(null);
    setFormData(createEmptyForm());
    setFormError("");
    setIsFormOpen(true);
  };

  const openEditForm = async (quotation: Quotation) => {
    setFormError("");
    setIsSaving(false);

    try {
      const detail = await quotationService.getById(quotation.id);

      if (detail.status !== "draft") {
        setNotice({
          type: "error",
          message: "Hanya quotation berstatus Draft yang dapat diedit.",
        });
        return;
      }

      setEditingQuotation(detail);
      setFormData(mapQuotationToForm(detail));
      setSelectedQuotation(null);
      setIsFormOpen(true);
    } catch (err) {
      setNotice({
        type: "error",
        message: getErrorMessage(err),
      });
    }
  };

  const closeForm = () => {
    if (isSaving) return;

    setIsFormOpen(false);
    setEditingQuotation(null);
    setFormData(createEmptyForm());
    setFormError("");
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError("");

    if (!formData.customer_id) {
      setFormError("Customer wajib dipilih.");
      return;
    }

    if (!formData.subject.trim()) {
      setFormError("Subject quotation wajib diisi.");
      return;
    }

    if (!formData.issue_date || !formData.valid_until) {
      setFormError("Tanggal penerbitan dan masa berlaku wajib diisi.");
      return;
    }

    if (formData.valid_until < formData.issue_date) {
      setFormError(
        "Tanggal masa berlaku tidak boleh sebelum tanggal penerbitan.",
      );
      return;
    }

    if (formData.items.length === 0) {
      setFormError("Tambahkan minimal satu item quotation.");
      return;
    }

    const invalidItem = formData.items.findIndex(
      (item) =>
        !item.service_id ||
        !item.description.trim() ||
        !item.unit.trim() ||
        !Number.isFinite(Number(item.quantity)) ||
        Number(item.quantity) <= 0 ||
        !Number.isFinite(Number(item.unit_price)) ||
        Number(item.unit_price) < 0 ||
        !Number.isFinite(Number(item.discount_amount)) ||
        Number(item.discount_amount) < 0 ||
        !Number.isFinite(Number(item.tax_percentage)) ||
        Number(item.tax_percentage) < 0 ||
        Number(item.tax_percentage) > 100,
    );

    if (invalidItem !== -1) {
      setFormError(
        `Periksa kembali item ${invalidItem + 1}. Pastikan service, deskripsi, jumlah, satuan, harga, diskon, dan pajak sudah benar.`,
      );
      return;
    }

    const invalidTerm = formData.terms.findIndex(
      (term) => !term.title.trim() || !term.content.trim(),
    );

    if (invalidTerm !== -1) {
      setFormError(
        `Judul dan isi term ${invalidTerm + 1} wajib diisi atau hapus term tersebut.`,
      );
      return;
    }

    const items: QuotationItemPayload[] = formData.items.map(
      (item) => ({
        service_id: Number(item.service_id),
        description: item.description.trim(),
        quantity: Number(item.quantity),
        unit: item.unit.trim(),
        unit_price: Number(item.unit_price),
        discount_amount: Number(item.discount_amount) || 0,
        tax_percentage: Number(item.tax_percentage) || 0,
      }),
    );

    const payload: QuotationPayload = {
      customer_id: Number(formData.customer_id),
      subject: formData.subject.trim(),
      description: formData.description.trim() || null,
      issue_date: formData.issue_date,
      valid_until: formData.valid_until,
      currency: formData.currency || "IDR",
      tax_percentage: 0,
      internal_notes: formData.internal_notes.trim() || null,
      customer_notes: formData.customer_notes.trim() || null,
      items,
      terms: formData.terms.map((term) => ({
        title: term.title.trim(),
        content: term.content.trim(),
        is_required: term.is_required,
      })),
    };

    setIsSaving(true);

    try {
      if (editingQuotation) {
        await quotationService.update(editingQuotation.id, payload);

        setNotice({
          type: "success",
          message: "Quotation berhasil diperbarui.",
        });
      } else {
        await quotationService.create(payload);

        setNotice({
          type: "success",
          message: "Quotation berhasil dibuat.",
        });
      }

      setIsFormOpen(false);
      setEditingQuotation(null);
      setFormData(createEmptyForm());

      if (page !== 1) {
        setPage(1);
      } else {
        await loadQuotations(false);
      }
    } catch (err) {
      setFormError(getErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  };

  const handleViewDetail = async (quotation: Quotation) => {
    setSelectedQuotation(quotation);
    setIsDetailLoading(true);

    try {
      const detail = await quotationService.getById(quotation.id);
      setSelectedQuotation(detail);
    } catch (err) {
      setNotice({
        type: "error",
        message: getErrorMessage(err),
      });
    } finally {
      setIsDetailLoading(false);
    }
  };

  // Membuka dialog hanya untuk quotation berstatus Draft.
  const openApprovalDialog = (quotation: Quotation) => {
    if (quotation.status !== "draft") {
      setNotice({
        type: "error",
        message: "Hanya quotation berstatus Draft yang dapat diajukan untuk approval.",
      });
      return;
    }

    setSelectedApprovalQuotation({
      id: quotation.id,
      quotation_number: quotation.quotation_number,
    });
  };

  // Dipanggil setelah pengajuan approval berhasil.
  const handleApprovalSubmitted = () => {
    const submittedQuotation = selectedApprovalQuotation;

    setSelectedApprovalQuotation(null);
    setSelectedQuotation((current) => {
    if (!current || current.id !== submittedQuotation?.id) {
        return current;
    }

    return {
        ...current,
        status: "pending_approval",
    };
    });

    setNotice({
      type: "success",
      message: "Quotation berhasil diajukan untuk approval.",
    });

    void loadQuotations(false);
  };

  const totals = calculateQuotationTotals(formData.items);

  const closeNotice = () => setNotice(null);

  const statusOptions: {
    value: StatusFilter;
    label: string;
  }[] = [
    { value: "all", label: "Semua" },
    { value: "draft", label: "Draft" },
    { value: "pending_approval", label: "Menunggu Approval" },
    { value: "approved", label: "Disetujui" },
    { value: "rejected", label: "Ditolak" },
  ];

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span>Payment Management</span>
            <ChevronRight size={13} />
            <span className="text-foreground">Quotation Management</span>
          </div>

          <h1 className="mt-2 text-2xl font-bold tracking-tight text-foreground">
            Quotation Management
          </h1>

          <p className="mt-1 text-sm text-muted-foreground">
            Kelola penawaran harga, rincian layanan, dan masa berlaku quotation.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => void loadQuotations(true)}
            disabled={isLoading}
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-50"
          >
            <RefreshCw
              size={16}
              className={isLoading ? "animate-spin" : ""}
            />
            <span className="hidden sm:inline">Refresh</span>
          </button>

          <button
            type="button"
            onClick={openCreateForm}
            disabled={isMasterDataLoading}
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover disabled:opacity-50"
          >
            <Plus size={17} />
            Buat Quotation
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {[
          {
            label: "Total Quotation",
            value: pagination.total,
            description: "Sesuai filter yang dipilih",
            icon: FileText,
            iconClass: "text-primary",
          },
          {
            label: "Quotation Ditampilkan",
            value: quotations.length,
            description: "Pada halaman saat ini",
            icon: ClipboardList,
            iconClass: "text-info",
          },
          {
            label: "Halaman",
            value: `${pagination.current_page} / ${pagination.last_page}`,
            description: "Navigasi data quotation",
            icon: CalendarDays,
            iconClass: "text-success",
          },
          {
            label: "Customer Aktif",
            value: customers.length,
            description: "Customer tersedia untuk quotation",
            icon: Building2,
            iconClass: "text-warning",
          },
        ].map((item) => {
          const Icon = item.icon;

          return (
            <div
              key={item.label}
              className="rounded-xl border border-border bg-card p-4"
            >
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">
                  {item.label}
                </p>
                <Icon size={18} className={item.iconClass} />
              </div>

              <p className="mt-3 text-2xl font-bold tabular-nums text-foreground">
                {item.value}
              </p>

              <p className="mt-1 text-xs text-muted-foreground">
                {item.description}
              </p>
            </div>
          );
        })}
      </div>

      {/* Quotation Table */}
      <div className="rounded-xl border border-border bg-card">
        <div className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-center lg:justify-between">
          <form
            onSubmit={handleSearch}
            className="flex w-full gap-2 lg:max-w-md"
          >
            <div className="relative min-w-0 flex-1">
              <Search
                size={17}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
              />
              <input
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Cari nomor, subject, customer..."
                className={`${inputClass} pl-9`}
              />
            </div>

            <button
              type="submit"
              className="h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover"
            >
              Cari
            </button>
          </form>

          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-medium text-muted-foreground">
              Status:
            </span>

            {statusOptions.map((item) => (
              <button
                key={item.value}
                type="button"
                onClick={() => handleStatusFilter(item.value)}
                className={`h-8 rounded-lg px-3 text-xs font-semibold transition ${
                  statusFilter === item.value
                    ? "bg-primary text-primary-foreground"
                    : "bg-secondary text-secondary-foreground hover:bg-secondary-hover"
                }`}
              >
                {item.label}
              </button>
            ))}
          </div>
        </div>

        {error && (
          <div className="m-4 flex flex-col gap-3 rounded-lg border border-danger/30 bg-danger-background p-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-danger">{error}</p>
            <button
              type="button"
              onClick={() => void loadQuotations(true)}
              className="text-sm font-semibold text-danger underline"
            >
              Coba lagi
            </button>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[950px] text-left">
            <thead>
              <tr className="border-b border-border bg-secondary/50">
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Quotation
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Customer
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Tanggal
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Total
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Status
                </th>
                <th className="px-5 py-3 text-right text-xs font-semibold text-muted-foreground">
                  Aksi
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="px-5 py-16 text-center">
                    <LoaderCircle
                      size={25}
                      className="mx-auto animate-spin text-primary"
                    />
                    <p className="mt-3 text-sm text-muted-foreground">
                      Memuat data quotation...
                    </p>
                  </td>
                </tr>
              ) : quotations.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-16 text-center">
                    <FileText
                      size={30}
                      className="mx-auto text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-semibold text-foreground">
                      Data quotation tidak ditemukan
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Coba ubah pencarian atau buat quotation baru.
                    </p>
                  </td>
                </tr>
              ) : (
                quotations.map((quotation) => (
                  <tr
                    key={quotation.id}
                    className="transition hover:bg-secondary/40"
                  >
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                          <FileText size={17} />
                        </div>

                        <div className="min-w-0">
                          <p className="max-w-[230px] truncate text-sm font-semibold text-foreground">
                            {quotation.quotation_number}
                          </p>
                          <p className="mt-0.5 max-w-[230px] truncate text-xs text-muted-foreground">
                            {quotation.subject}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td className="px-5 py-4">
                      <p className="max-w-[200px] truncate text-sm font-medium text-foreground">
                        {quotation.customer?.company_name || "-"}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {quotation.customer?.customer_code || "-"}
                      </p>
                    </td>

                    <td className="px-5 py-4">
                      <p className="text-sm text-foreground">
                        {formatDate(quotation.issue_date)}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        Berlaku hingga {formatDate(quotation.valid_until)}
                      </p>
                    </td>

                    <td className="px-5 py-4">
                      <p className="whitespace-nowrap text-sm font-semibold text-foreground">
                        {formatCurrency(
                          quotation.total_amount,
                          quotation.currency,
                        )}
                      </p>
                    </td>

                    <td className="px-5 py-4">
                      <StatusBadge status={quotation.status} />
                    </td>

                    <td className="px-5 py-4">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          type="button"
                          title="Lihat detail"
                          onClick={() => void handleViewDetail(quotation)}
                          className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        >
                          <Eye size={16} />
                        </button>

                        {quotation.status === "draft" && (
                          <>
                            <button
                              type="button"
                              title="Ajukan untuk approval"
                              onClick={() => openApprovalDialog(quotation)}
                              className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-primary"
                            >
                              <Send size={16} />
                            </button>

                            <button
                              type="button"
                              title="Edit quotation"
                              onClick={() => void openEditForm(quotation)}
                              className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-primary"
                            >
                              <Pencil size={16} />
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-muted-foreground">
            Menampilkan{" "}
            <span className="font-semibold text-foreground">
              {pagination.from ?? 0}–{pagination.to ?? 0}
            </span>{" "}
            dari{" "}
            <span className="font-semibold text-foreground">
              {pagination.total}
            </span>{" "}
            quotation
          </p>

          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              disabled={page <= 1 || isLoading}
              onClick={() =>
                setPage((previous) => Math.max(1, previous - 1))
              }
              className="inline-flex h-8 items-center gap-1 rounded-lg border border-border px-2.5 text-xs font-medium text-foreground transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
            >
              <ChevronLeft size={15} />
              Sebelumnya
            </button>

            <span className="min-w-16 text-center text-xs text-muted-foreground">
              {pagination.current_page} / {pagination.last_page}
            </span>

            <button
              type="button"
              disabled={page >= pagination.last_page || isLoading}
              onClick={() =>
                setPage((previous) =>
                  Math.min(pagination.last_page, previous + 1),
                )
              }
              className="inline-flex h-8 items-center gap-1 rounded-lg border border-border px-2.5 text-xs font-medium text-foreground transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-40"
            >
              Berikutnya
              <ChevronRight size={15} />
            </button>
          </div>
        </div>
      </div>

      {/* Create / Edit Modal */}
      {isFormOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6">
          <div className="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <div>
                <h2 className="text-lg font-bold text-foreground">
                  {editingQuotation ? "Edit Quotation" : "Buat Quotation"}
                </h2>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Lengkapi informasi penawaran dan rincian layanan.
                </p>
              </div>

              <button
                type="button"
                onClick={closeForm}
                disabled={isSaving}
                className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                aria-label="Tutup form"
              >
                <X size={19} />
              </button>
            </div>

            <form
              onSubmit={handleSubmit}
              className="flex min-h-0 flex-1 flex-col"
            >
              <div className="flex-1 space-y-6 overflow-y-auto p-5">
                {formError && (
                  <div className="rounded-lg border border-danger/30 bg-danger-background px-3 py-2.5 text-sm text-danger">
                    {formError}
                  </div>
                )}

                {isMasterDataLoading && (
                  <div className="flex items-center gap-2 rounded-lg bg-secondary/60 p-3 text-sm text-muted-foreground">
                    <LoaderCircle size={16} className="animate-spin" />
                    Memuat data customer dan service...
                  </div>
                )}

                {/* General Information */}
                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Informasi Quotation
                  </h3>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Customer" required>
                      <select
                        required
                        value={formData.customer_id}
                        onChange={(event) =>
                          updateFormField("customer_id", event.target.value)
                        }
                        disabled={isMasterDataLoading}
                        className={inputClass}
                      >
                        <option value="">Pilih customer</option>
                        {customers.map((customer) => (
                          <option key={customer.id} value={customer.id}>
                            {customer.customer_code} - {customer.company_name}
                          </option>
                        ))}
                      </select>
                    </Field>

                    <Field label="Subject Quotation" required>
                      <input
                        required
                        value={formData.subject}
                        onChange={(event) =>
                          updateFormField("subject", event.target.value)
                        }
                        placeholder="Contoh: Penawaran Sertifikasi ISO"
                        className={inputClass}
                      />
                    </Field>

                    <Field label="Tanggal Penerbitan" required>
                      <input
                        type="date"
                        required
                        value={formData.issue_date}
                        onChange={(event) =>
                          updateFormField("issue_date", event.target.value)
                        }
                        className={inputClass}
                      />
                    </Field>

                    <Field label="Berlaku Hingga" required>
                      <input
                        type="date"
                        required
                        min={formData.issue_date}
                        value={formData.valid_until}
                        onChange={(event) =>
                          updateFormField("valid_until", event.target.value)
                        }
                        className={inputClass}
                      />
                    </Field>

                    <Field label="Mata Uang">
                      <select
                        value={formData.currency}
                        onChange={(event) =>
                          updateFormField("currency", event.target.value)
                        }
                        className={inputClass}
                      >
                        <option value="IDR">IDR - Rupiah</option>
                        <option value="USD">USD - US Dollar</option>
                        <option value="SGD">SGD - Singapore Dollar</option>
                      </select>
                    </Field>

                    <div className="sm:col-span-2">
                      <Field label="Deskripsi">
                        <textarea
                          rows={2}
                          value={formData.description}
                          onChange={(event) =>
                            updateFormField("description", event.target.value)
                          }
                          placeholder="Deskripsi singkat penawaran..."
                          className={textareaClass}
                        />
                      </Field>
                    </div>
                  </div>
                </section>

                {/* Quotation Items */}
                <section className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Rincian Item
                      </h3>
                      <p className="mt-1 text-xs text-muted-foreground">
                        Pilih service dan lengkapi harga, diskon, serta pajak.
                      </p>
                    </div>

                    <button
                      type="button"
                      onClick={addItem}
                      className="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-xs font-semibold text-foreground transition hover:bg-secondary"
                    >
                      <Plus size={15} />
                      Tambah Item
                    </button>
                  </div>

                  <div className="space-y-4">
                    {formData.items.map((item, index) => (
                      <div
                        key={index}
                        className="rounded-xl border border-border bg-secondary/20 p-4"
                      >
                        <div className="mb-4 flex items-center justify-between">
                          <p className="text-sm font-semibold text-foreground">
                            Item {index + 1}
                          </p>

                          <button
                            type="button"
                            onClick={() => removeItem(index)}
                            disabled={formData.items.length === 1}
                            title="Hapus item"
                            className="rounded-lg p-2 text-muted-foreground transition hover:bg-danger-background hover:text-danger disabled:cursor-not-allowed disabled:opacity-30"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                          <Field label="Service" required>
                            <select
                              required
                              value={item.service_id}
                              onChange={(event) =>
                                handleServiceChange(index, event.target.value)
                              }
                              disabled={isMasterDataLoading}
                              className={inputClass}
                            >
                              <option value="">Pilih service</option>
                              {services.map((service) => (
                                <option key={service.id} value={service.id}>
                                  {service.service_code} - {service.name}
                                </option>
                              ))}
                            </select>
                          </Field>

                          <Field label="Deskripsi Item" required>
                            <input
                              required
                              value={item.description}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "description",
                                  event.target.value,
                                )
                              }
                              placeholder="Deskripsi layanan"
                              className={inputClass}
                            />
                          </Field>

                          <Field label="Kuantitas" required>
                            <input
                              type="number"
                              min="0.01"
                              step="any"
                              required
                              value={item.quantity}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "quantity",
                                  event.target.value,
                                )
                              }
                              className={inputClass}
                            />
                          </Field>

                          <Field label="Satuan" required>
                            <input
                              required
                              value={item.unit}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "unit",
                                  event.target.value,
                                )
                              }
                              placeholder="Contoh: paket, unit, hari"
                              className={inputClass}
                            />
                          </Field>

                          <Field label="Harga Satuan" required>
                            <input
                              type="number"
                              min="0"
                              step="any"
                              required
                              value={item.unit_price}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "unit_price",
                                  event.target.value,
                                )
                              }
                              placeholder="0"
                              className={inputClass}
                            />
                          </Field>

                          <Field label="Diskon">
                            <input
                              type="number"
                              min="0"
                              step="any"
                              value={item.discount_amount}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "discount_amount",
                                  event.target.value,
                                )
                              }
                              placeholder="0"
                              className={inputClass}
                            />
                          </Field>

                          <Field label="Pajak (%)">
                            <input
                              type="number"
                              min="0"
                              max="100"
                              step="any"
                              value={item.tax_percentage}
                              onChange={(event) =>
                                updateItemField(
                                  index,
                                  "tax_percentage",
                                  event.target.value,
                                )
                              }
                              placeholder="0"
                              className={inputClass}
                            />
                          </Field>

                          <div className="flex items-end">
                            <div className="w-full rounded-lg bg-background px-3 py-2.5">
                              <p className="text-[11px] text-muted-foreground">
                                Total Item
                              </p>
                              <p className="mt-1 text-sm font-bold text-foreground">
                                {formatCurrency(
                                  calculateItemTotal(item),
                                  formData.currency,
                                )}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </section>

                {/* Totals */}
                <section className="rounded-xl border border-border bg-secondary/30 p-4">
                  <div className="mb-3 flex items-center gap-2">
                    <Calculator size={17} className="text-primary" />
                    <h3 className="text-xs font-bold uppercase tracking-wider text-foreground">
                      Ringkasan Perhitungan
                    </h3>
                  </div>

                  <div className="ml-auto max-w-sm space-y-2">
                    <div className="flex justify-between gap-4 text-sm">
                      <span className="text-muted-foreground">Subtotal</span>
                      <span className="font-medium text-foreground">
                        {formatCurrency(totals.subtotal, formData.currency)}
                      </span>
                    </div>

                    <div className="flex justify-between gap-4 text-sm">
                      <span className="text-muted-foreground">
                        Total Diskon
                      </span>
                      <span className="font-medium text-foreground">
                        -{formatCurrency(
                          totals.totalDiscount,
                          formData.currency,
                        )}
                      </span>
                    </div>

                    <div className="flex justify-between gap-4 text-sm">
                      <span className="text-muted-foreground">
                        Total Pajak
                      </span>
                      <span className="font-medium text-foreground">
                        {formatCurrency(totals.taxAmount, formData.currency)}
                      </span>
                    </div>

                    <div className="border-t border-border pt-3">
                      <div className="flex justify-between gap-4">
                        <span className="text-sm font-bold text-foreground">
                          Grand Total
                        </span>
                        <span className="text-lg font-bold text-primary">
                          {formatCurrency(totals.total, formData.currency)}
                        </span>
                      </div>
                    </div>
                  </div>

                  <p className="mt-3 text-[11px] text-muted-foreground">
                    Perhitungan pajak dilakukan per item setelah dikurangi
                    diskon, mengikuti perhitungan backend.
                  </p>
                </section>

                {/* Terms */}
                <section className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Terms & Conditions
                      </h3>
                      <p className="mt-1 text-xs text-muted-foreground">
                        Ketentuan tambahan pada quotation (opsional).
                      </p>
                    </div>

                    <button
                      type="button"
                      onClick={addTerm}
                      className="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-xs font-semibold text-foreground transition hover:bg-secondary"
                    >
                      <Plus size={15} />
                      Tambah Term
                    </button>
                  </div>

                  {formData.terms.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border px-4 py-6 text-center">
                      <ReceiptText
                        size={23}
                        className="mx-auto text-muted-foreground"
                      />
                      <p className="mt-2 text-xs text-muted-foreground">
                        Belum ada terms & conditions.
                      </p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {formData.terms.map((term, index) => (
                        <div
                          key={index}
                          className="rounded-xl border border-border p-4"
                        >
                          <div className="mb-3 flex items-center justify-between">
                            <p className="text-sm font-semibold text-foreground">
                              Term {index + 1}
                            </p>

                            <button
                              type="button"
                              onClick={() => removeTerm(index)}
                              className="rounded-lg p-2 text-muted-foreground transition hover:bg-danger-background hover:text-danger"
                              title="Hapus term"
                            >
                              <Trash2 size={15} />
                            </button>
                          </div>

                          <div className="space-y-3">
                            <Field label="Judul Term" required>
                              <input
                                required
                                value={term.title}
                                onChange={(event) =>
                                  updateTermField(
                                    index,
                                    "title",
                                    event.target.value,
                                  )
                                }
                                placeholder="Contoh: Ketentuan Pembayaran"
                                className={inputClass}
                              />
                            </Field>

                            <Field label="Isi Term" required>
                              <textarea
                                required
                                rows={3}
                                value={term.content}
                                onChange={(event) =>
                                  updateTermField(
                                    index,
                                    "content",
                                    event.target.value,
                                  )
                                }
                                placeholder="Tuliskan ketentuan..."
                                className={textareaClass}
                              />
                            </Field>

                            <label className="inline-flex items-center gap-2 text-xs text-foreground">
                              <input
                                type="checkbox"
                                checked={term.is_required}
                                onChange={(event) =>
                                  updateTermField(
                                    index,
                                    "is_required",
                                    event.target.checked,
                                  )
                                }
                                className="h-4 w-4 rounded border-input accent-primary"
                              />
                              Ketentuan wajib
                            </label>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </section>

                {/* Notes */}
                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Catatan
                  </h3>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Catatan Internal">
                      <textarea
                        rows={3}
                        value={formData.internal_notes}
                        onChange={(event) =>
                          updateFormField(
                            "internal_notes",
                            event.target.value,
                          )
                        }
                        placeholder="Catatan internal perusahaan..."
                        className={textareaClass}
                      />
                    </Field>

                    <Field label="Catatan untuk Customer">
                      <textarea
                        rows={3}
                        value={formData.customer_notes}
                        onChange={(event) =>
                          updateFormField(
                            "customer_notes",
                            event.target.value,
                          )
                        }
                        placeholder="Catatan yang ditampilkan kepada customer..."
                        className={textareaClass}
                      />
                    </Field>
                  </div>
                </section>
              </div>

              <div className="flex justify-end gap-2 border-t border-border bg-secondary/30 px-5 py-4">
                <button
                  type="button"
                  onClick={closeForm}
                  disabled={isSaving}
                  className="h-10 rounded-lg border border-border bg-card px-4 text-sm font-semibold text-foreground transition hover:bg-secondary disabled:opacity-50"
                >
                  Batal
                </button>

                <button
                  type="submit"
                  disabled={isSaving || isMasterDataLoading}
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover disabled:opacity-60"
                >
                  {isSaving ? (
                    <LoaderCircle size={16} className="animate-spin" />
                  ) : (
                    <Check size={16} />
                  )}
                  {isSaving
                    ? "Menyimpan..."
                    : editingQuotation
                      ? "Simpan Perubahan"
                      : "Simpan Quotation"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Detail Modal */}
      {selectedQuotation && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6">
          <div className="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
            <div className="flex items-start justify-between border-b border-border p-5">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <FileText size={21} />
                </div>

                <div>
                  <h2 className="text-lg font-bold text-foreground">
                    Detail Quotation
                  </h2>
                  <p className="text-xs text-muted-foreground">
                    {selectedQuotation.quotation_number}
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setSelectedQuotation(null)}
                className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary"
                aria-label="Tutup detail"
              >
                <X size={19} />
              </button>
            </div>

            {isDetailLoading ? (
              <div className="p-12 text-center">
                <LoaderCircle
                  size={25}
                  className="mx-auto animate-spin text-primary"
                />
                <p className="mt-3 text-sm text-muted-foreground">
                  Memuat detail quotation...
                </p>
              </div>
            ) : (
              <div className="flex-1 space-y-5 overflow-y-auto p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 className="text-base font-bold text-foreground">
                      {selectedQuotation.subject}
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {selectedQuotation.customer?.company_name || "-"}
                    </p>
                  </div>

                  <StatusBadge status={selectedQuotation.status} />
                </div>

                {selectedQuotation.description && (
                  <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                    {selectedQuotation.description}
                  </p>
                )}

                <div className="grid grid-cols-1 gap-4 rounded-xl bg-secondary/50 p-4 sm:grid-cols-2">
                  <div>
                    <p className="text-xs text-muted-foreground">
                      Tanggal Penerbitan
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                      {formatDate(selectedQuotation.issue_date)}
                    </p>
                  </div>

                  <div>
                    <p className="text-xs text-muted-foreground">
                      Berlaku Hingga
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                      {formatDate(selectedQuotation.valid_until)}
                    </p>
                  </div>

                  <div>
                    <p className="text-xs text-muted-foreground">
                      Customer Code
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                      {selectedQuotation.customer?.customer_code || "-"}
                    </p>
                  </div>

                  <div>
                    <p className="text-xs text-muted-foreground">
                      Mata Uang
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                      {selectedQuotation.currency}
                    </p>
                  </div>
                </div>

                <section>
                  <h3 className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Rincian Item
                  </h3>

                  <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-[600px] text-left">
                      <thead>
                        <tr className="border-b border-border bg-secondary/50">
                          <th className="px-3 py-2.5 text-xs font-semibold text-muted-foreground">
                            Item
                          </th>
                          <th className="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">
                            Qty
                          </th>
                          <th className="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">
                            Harga
                          </th>
                          <th className="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">
                            Diskon
                          </th>
                          <th className="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground">
                            Total
                          </th>
                        </tr>
                      </thead>

                      <tbody className="divide-y divide-border">
                        {selectedQuotation.items?.map((item) => (
                          <tr key={item.id}>
                            <td className="px-3 py-3">
                              <p className="text-sm font-medium text-foreground">
                                {item.description}
                              </p>
                              <p className="mt-0.5 text-xs text-muted-foreground">
                                Pajak {item.tax_percentage}% · {item.unit}
                              </p>
                            </td>

                            <td className="px-3 py-3 text-right text-sm text-foreground">
                              {item.quantity}
                            </td>

                            <td className="px-3 py-3 text-right text-sm text-foreground">
                              {formatCurrency(
                                item.unit_price,
                                selectedQuotation.currency,
                              )}
                            </td>

                            <td className="px-3 py-3 text-right text-sm text-foreground">
                              {formatCurrency(
                                item.discount_amount,
                                selectedQuotation.currency,
                              )}
                            </td>

                            <td className="px-3 py-3 text-right text-sm font-semibold text-foreground">
                              {formatCurrency(
                                item.line_total,
                                selectedQuotation.currency,
                              )}
                            </td>
                          </tr>
                        ))}

                        {(!selectedQuotation.items ||
                          selectedQuotation.items.length === 0) && (
                          <tr>
                            <td
                              colSpan={5}
                              className="px-3 py-8 text-center text-sm text-muted-foreground"
                            >
                              Rincian item tidak tersedia.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </section>

                <div className="ml-auto max-w-sm space-y-2 rounded-xl bg-secondary/50 p-4">
                  <div className="flex justify-between gap-4 text-sm">
                    <span className="text-muted-foreground">Subtotal</span>
                    <span className="font-medium text-foreground">
                      {formatCurrency(
                        selectedQuotation.subtotal,
                        selectedQuotation.currency,
                      )}
                    </span>
                  </div>

                  <div className="flex justify-between gap-4 text-sm">
                    <span className="text-muted-foreground">Diskon</span>
                    <span className="font-medium text-foreground">
                      -{formatCurrency(
                        selectedQuotation.total_discount,
                        selectedQuotation.currency,
                      )}
                    </span>
                  </div>

                  <div className="flex justify-between gap-4 text-sm">
                    <span className="text-muted-foreground">Pajak</span>
                    <span className="font-medium text-foreground">
                      {formatCurrency(
                        selectedQuotation.tax_amount,
                        selectedQuotation.currency,
                      )}
                    </span>
                  </div>

                  <div className="flex justify-between gap-4 border-t border-border pt-3">
                    <span className="text-sm font-bold text-foreground">
                      Grand Total
                    </span>
                    <span className="text-base font-bold text-primary">
                      {formatCurrency(
                        selectedQuotation.total_amount,
                        selectedQuotation.currency,
                      )}
                    </span>
                  </div>
                </div>

                {selectedQuotation.terms &&
                  selectedQuotation.terms.length > 0 && (
                    <section>
                      <h3 className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Terms & Conditions
                      </h3>

                      <div className="space-y-3">
                        {selectedQuotation.terms.map((term) => (
                          <div
                            key={term.id ?? term.title}
                            className="rounded-lg border border-border p-3"
                          >
                            <div className="flex flex-wrap items-center gap-2">
                              <p className="text-sm font-semibold text-foreground">
                                {term.title}
                              </p>

                              {term.is_required && (
                                <span className="rounded-full bg-warning-background px-2 py-0.5 text-[10px] font-semibold text-warning">
                                  Wajib
                                </span>
                              )}
                            </div>

                            <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">
                              {term.content}
                            </p>
                          </div>
                        ))}
                      </div>
                    </section>
                  )}

                {selectedQuotation.customer_notes && (
                  <section>
                    <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                      Catatan untuk Customer
                    </h3>
                    <p className="whitespace-pre-wrap rounded-lg bg-secondary/50 p-3 text-sm text-foreground">
                      {selectedQuotation.customer_notes}
                    </p>
                  </section>
                )}

                {selectedQuotation.internal_notes && (
                  <section>
                    <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                      Catatan Internal
                    </h3>
                    <p className="whitespace-pre-wrap rounded-lg bg-secondary/50 p-3 text-sm text-foreground">
                      {selectedQuotation.internal_notes}
                    </p>
                  </section>
                )}

                <div className="border-t border-border pt-3 text-xs text-muted-foreground">
                  <p>Dibuat: {formatDate(selectedQuotation.created_at)}</p>
                  <p className="mt-1">
                    Terakhir diperbarui:{" "}
                    {formatDate(selectedQuotation.updated_at)}
                  </p>
                </div>

                <div className="flex flex-wrap justify-end gap-2">
                  {selectedQuotation.status === "draft" && (
                    <>
                      <button
                        type="button"
                        onClick={() =>
                          openApprovalDialog(selectedQuotation)
                        }
                        className="inline-flex h-9 items-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover"
                      >
                        <Send size={15} />
                        Submit for Approval
                      </button>

                      <button
                        type="button"
                        onClick={() => void openEditForm(selectedQuotation)}
                        className="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-foreground transition hover:bg-secondary"
                      >
                        <Pencil size={15} />
                        Edit Quotation
                      </button>
                    </>
                  )}

                  <button
                    type="button"
                    onClick={() => setSelectedQuotation(null)}
                    className="h-9 rounded-lg bg-secondary px-4 text-sm font-semibold text-foreground transition hover:bg-secondary-hover"
                  >
                    Tutup
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Submit Approval Dialog */}
      {selectedApprovalQuotation && (
        <SubmitApprovalDialog
          quotationId={selectedApprovalQuotation.id}
          quotationNumber={selectedApprovalQuotation.quotation_number}
          onClose={() => setSelectedApprovalQuotation(null)}
          onSubmitted={handleApprovalSubmitted}
        />
      )}

      {/* Notification */}
      {notice && (
        <div className="fixed bottom-5 right-5 z-[120] flex w-[calc(100%-2.5rem)] max-w-sm items-start gap-3 rounded-xl border border-border bg-card p-4 shadow-xl">
          <div
            className={`mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${
              notice.type === "success"
                ? "bg-success-background text-success"
                : "bg-danger-background text-danger"
            }`}
          >
            {notice.type === "success" ? (
              <Check size={15} />
            ) : (
              <X size={15} />
            )}
          </div>

          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-foreground">
              {notice.type === "success"
                ? "Berhasil"
                : "Terjadi Kesalahan"}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              {notice.message}
            </p>
          </div>

          <button
            type="button"
            onClick={closeNotice}
            className="rounded p-1 text-muted-foreground hover:bg-secondary"
            aria-label="Tutup notifikasi"
          >
            <X size={15} />
          </button>
        </div>
      )}
    </div>
  );
}