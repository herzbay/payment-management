"use client";

import {
  useCallback,
  useEffect,
  useState,
  type FormEvent,
} from "react";
import {
  Building2,
  Check,
  ChevronLeft,
  ChevronRight,
  Eye,
  LoaderCircle,
  Mail,
  MapPin,
  Pencil,
  Phone,
  Plus,
  RefreshCw,
  Search,
  ToggleLeft,
  ToggleRight,
  UserRound,
  X,
} from "lucide-react";

import { customerService } from "@/lib/master-data";
import type {
  Customer,
  CustomerPayload,
  MasterDataStatus,
  PaginationMeta,
} from "@/types/master-data";

const EMPTY_PAGINATION: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: null,
  to: null,
};

const EMPTY_FORM: CustomerPayload = {
  customer_code: "",
  company_name: "",
  contact_person: "",
  email: "",
  phone: "",
  tax_identification_number: "",
  billing_address: "",
  city: "",
  province: "",
  postal_code: "",
  country: "Indonesia",
  notes: "",
  is_active: true,
};

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Terjadi kesalahan. Silakan coba kembali.";
}

function formatDate(value: string): string {
  if (!value) return "-";

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(new Date(value));
}

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${
        active
          ? "bg-success-background text-success"
          : "bg-secondary text-muted-foreground"
      }`}
    >
      <span
        className={`h-1.5 w-1.5 rounded-full ${
          active ? "bg-success" : "bg-muted-foreground"
        }`}
      />
      {active ? "Aktif" : "Nonaktif"}
    </span>
  );
}

function Field({
  label,
  children,
  required = false,
}: {
  label: string;
  children: React.ReactNode;
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

export default function CustomersPage() {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [pagination, setPagination] =
    useState<PaginationMeta>(EMPTY_PAGINATION);

  const [searchInput, setSearchInput] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const [statusFilter, setStatusFilter] =
    useState<MasterDataStatus>("all");
  const [page, setPage] = useState(1);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingCustomer, setEditingCustomer] =
    useState<Customer | null>(null);
  const [formData, setFormData] =
    useState<CustomerPayload>(EMPTY_FORM);
  const [isSaving, setIsSaving] = useState(false);
  const [formError, setFormError] = useState("");

  const [selectedCustomer, setSelectedCustomer] =
    useState<Customer | null>(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState<number | null>(
    null,
  );

  const [notice, setNotice] = useState<{
    type: "success" | "error";
    message: string;
  } | null>(null);

  const loadCustomers = useCallback(async () => {
    setIsLoading(true);
    setError("");

    try {
      const result = await customerService.getAll({
        search: appliedSearch || undefined,
        is_active:
          statusFilter === "all"
            ? undefined
            : statusFilter === "active",
        page,
        per_page: 10,
      });

      setCustomers(result.data);
      setPagination(result.meta);
    } catch (err) {
      setError(getErrorMessage(err));
      setCustomers([]);
      setPagination(EMPTY_PAGINATION);
    } finally {
      setIsLoading(false);
    }
  }, [appliedSearch, statusFilter, page]);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadCustomers();
    }, 0);

    return () => window.clearTimeout(timeoutId);
  }, [loadCustomers]);

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPage(1);
    setAppliedSearch(searchInput.trim());
  };

  const handleStatusFilter = (value: MasterDataStatus) => {
    setStatusFilter(value);
    setPage(1);
  };

  const openCreateForm = () => {
    setEditingCustomer(null);
    setFormData({ ...EMPTY_FORM });
    setFormError("");
    setIsFormOpen(true);
  };

  const openEditForm = (customer: Customer) => {
    setEditingCustomer(customer);
    setFormData({
      customer_code: customer.customer_code ?? "",
      company_name: customer.company_name ?? "",
      contact_person: customer.contact_person ?? "",
      email: customer.email ?? "",
      phone: customer.phone ?? "",
      tax_identification_number:
        customer.tax_identification_number ?? "",
      billing_address: customer.billing_address ?? "",
      city: customer.city ?? "",
      province: customer.province ?? "",
      postal_code: customer.postal_code ?? "",
      country: customer.country ?? "Indonesia",
      notes: customer.notes ?? "",
      is_active: customer.is_active,
    });
    setFormError("");
    setIsFormOpen(true);
  };

  const updateFormField = <K extends keyof CustomerPayload>(
    key: K,
    value: CustomerPayload[K],
  ) => {
    setFormData((previous) => ({
      ...previous,
      [key]: value,
    }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSaving(true);
    setFormError("");

    const payload: CustomerPayload = {
      ...formData,
      customer_code: formData.customer_code?.trim() || undefined,
      company_name: formData.company_name.trim(),
      contact_person: formData.contact_person?.trim() || null,
      email: formData.email?.trim() || null,
      phone: formData.phone?.trim() || null,
      tax_identification_number:
        formData.tax_identification_number?.trim() || null,
      billing_address: formData.billing_address?.trim() || null,
      city: formData.city?.trim() || null,
      province: formData.province?.trim() || null,
      postal_code: formData.postal_code?.trim() || null,
      country: formData.country?.trim() || null,
      notes: formData.notes?.trim() || null,
    };

    try {
      if (editingCustomer) {
        await customerService.update(editingCustomer.id, payload);
        setNotice({
          type: "success",
          message: "Data customer berhasil diperbarui.",
        });
      } else {
        await customerService.create(payload);
        setNotice({
          type: "success",
          message: "Customer baru berhasil ditambahkan.",
        });
      }

      setIsFormOpen(false);
      await loadCustomers();
    } catch (err) {
      setFormError(getErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  };

  const handleViewDetail = async (customer: Customer) => {
    setSelectedCustomer(customer);
    setIsDetailLoading(true);

    try {
      const result = await customerService.getById(customer.id);
      setSelectedCustomer(result);
    } catch (err) {
      setNotice({
        type: "error",
        message: getErrorMessage(err),
      });
    } finally {
      setIsDetailLoading(false);
    }
  };

  const handleToggleStatus = async (customer: Customer) => {
    const nextStatus = !customer.is_active;
    const confirmation = window.confirm(
      `Apakah Anda yakin ingin ${
        nextStatus ? "mengaktifkan" : "menonaktifkan"
      } customer ${customer.company_name}?`,
    );

    if (!confirmation) return;

    setIsUpdatingStatus(customer.id);

    try {
      await customerService.updateStatus(customer.id, nextStatus);

      setNotice({
        type: "success",
        message: `Customer berhasil ${
          nextStatus ? "diaktifkan" : "dinonaktifkan"
        }.`,
      });

      await loadCustomers();

      if (selectedCustomer?.id === customer.id) {
        setSelectedCustomer({
          ...selectedCustomer,
          is_active: nextStatus,
        });
      }
    } catch (err) {
      setNotice({
        type: "error",
        message: getErrorMessage(err),
      });
    } finally {
      setIsUpdatingStatus(null);
    }
  };

  const closeNotice = () => setNotice(null);

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span>Master Data</span>
            <ChevronRight size={13} />
            <span className="text-foreground">Customer Management</span>
          </div>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-foreground">
            Customer Management
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Kelola informasi perusahaan dan data pelanggan.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => void loadCustomers()}
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
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover"
          >
            <Plus size={17} />
            Tambah Customer
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-4">
          <div className="flex items-center justify-between">
            <p className="text-xs font-medium text-muted-foreground">
              Total Customer
            </p>
            <Building2 size={18} className="text-primary" />
          </div>
          <p className="mt-3 text-2xl font-bold tabular-nums text-foreground">
            {pagination.total}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Seluruh data customer
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-4">
          <div className="flex items-center justify-between">
            <p className="text-xs font-medium text-muted-foreground">
              Customer Ditampilkan
            </p>
            <UserRound size={18} className="text-info" />
          </div>
          <p className="mt-3 text-2xl font-bold tabular-nums text-foreground">
            {customers.length}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Pada halaman saat ini
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-4">
          <div className="flex items-center justify-between">
            <p className="text-xs font-medium text-muted-foreground">
              Halaman
            </p>
            <Building2 size={18} className="text-success" />
          </div>
          <p className="mt-3 text-2xl font-bold tabular-nums text-foreground">
            {pagination.current_page} / {pagination.last_page}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Navigasi data customer
          </p>
        </div>
      </div>

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
                placeholder="Cari kode, perusahaan, email..."
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
            {(
              [
                { value: "all", label: "Semua" },
                { value: "active", label: "Aktif" },
                { value: "inactive", label: "Nonaktif" },
              ] as const
            ).map((item) => (
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
              onClick={() => void loadCustomers()}
              className="text-sm font-semibold text-danger underline"
            >
              Coba lagi
            </button>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left">
            <thead>
              <tr className="border-b border-border bg-secondary/50">
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Customer
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Kontak
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Lokasi
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Status
                </th>
                <th className="px-5 py-3 text-xs font-semibold text-muted-foreground">
                  Dibuat
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
                      Memuat data customer...
                    </p>
                  </td>
                </tr>
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-16 text-center">
                    <Building2
                      size={30}
                      className="mx-auto text-muted-foreground"
                    />
                    <p className="mt-3 text-sm font-semibold text-foreground">
                      Data customer tidak ditemukan
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Coba ubah kata kunci pencarian atau tambahkan customer
                      baru.
                    </p>
                  </td>
                </tr>
              ) : (
                customers.map((customer) => (
                  <tr
                    key={customer.id}
                    className="transition hover:bg-secondary/40"
                  >
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                          <Building2 size={17} />
                        </div>
                        <div className="min-w-0">
                          <p className="max-w-[230px] truncate text-sm font-semibold text-foreground">
                            {customer.company_name}
                          </p>
                          <p className="mt-0.5 text-xs text-muted-foreground">
                            {customer.customer_code || `CUS-${customer.id}`}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td className="px-5 py-4">
                      <p className="text-sm text-foreground">
                        {customer.contact_person || "-"}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {customer.email || customer.phone || "-"}
                      </p>
                    </td>

                    <td className="px-5 py-4">
                      <p className="text-sm text-foreground">
                        {customer.city || "-"}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {customer.province || customer.country || "-"}
                      </p>
                    </td>

                    <td className="px-5 py-4">
                      <StatusBadge active={customer.is_active} />
                    </td>

                    <td className="px-5 py-4 text-xs text-muted-foreground">
                      {formatDate(customer.created_at)}
                    </td>

                    <td className="px-5 py-4">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          type="button"
                          title="Lihat detail"
                          onClick={() => void handleViewDetail(customer)}
                          className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        >
                          <Eye size={16} />
                        </button>
                        <button
                          type="button"
                          title="Edit customer"
                          onClick={() => openEditForm(customer)}
                          className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-primary"
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          type="button"
                          title={
                            customer.is_active
                              ? "Nonaktifkan customer"
                              : "Aktifkan customer"
                          }
                          disabled={isUpdatingStatus === customer.id}
                          onClick={() => void handleToggleStatus(customer)}
                          className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground disabled:opacity-50"
                        >
                          {isUpdatingStatus === customer.id ? (
                            <LoaderCircle size={16} className="animate-spin" />
                          ) : customer.is_active ? (
                            <ToggleRight size={18} className="text-success" />
                          ) : (
                            <ToggleLeft size={18} />
                          )}
                        </button>
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
            customer
          </p>

          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              disabled={page <= 1 || isLoading}
              onClick={() => setPage((previous) => Math.max(1, previous - 1))}
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

      {isFormOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6">
          <div className="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <div>
                <h2 className="text-lg font-bold text-foreground">
                  {editingCustomer ? "Edit Customer" : "Tambah Customer"}
                </h2>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Lengkapi informasi customer di bawah ini.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIsFormOpen(false)}
                disabled={isSaving}
                className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
              >
                <X size={19} />
              </button>
            </div>

            <form
              onSubmit={handleSubmit}
              className="flex min-h-0 flex-1 flex-col"
            >
              <div className="flex-1 space-y-5 overflow-y-auto p-5">
                {formError && (
                  <div className="rounded-lg border border-danger/30 bg-danger-background px-3 py-2.5 text-sm text-danger">
                    {formError}
                  </div>
                )}

                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Informasi Perusahaan
                  </h3>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Kode Customer">
                      <input
                        value={formData.customer_code ?? ""}
                        onChange={(event) =>
                          updateFormField("customer_code", event.target.value)
                        }
                        placeholder="Kosongkan jika dibuat otomatis"
                        className={inputClass}
                      />
                    </Field>
                    <Field label="Nama Perusahaan" required>
                      <input
                        required
                        value={formData.company_name}
                        onChange={(event) =>
                          updateFormField("company_name", event.target.value)
                        }
                        placeholder="Nama perusahaan"
                        className={inputClass}
                      />
                    </Field>
                    <Field label="NPWP / Tax Identification Number">
                      <input
                        value={formData.tax_identification_number ?? ""}
                        onChange={(event) =>
                          updateFormField(
                            "tax_identification_number",
                            event.target.value,
                          )
                        }
                        placeholder="Nomor identifikasi pajak"
                        className={inputClass}
                      />
                    </Field>
                    <Field label="Status Customer">
                      <select
                        value={formData.is_active ? "active" : "inactive"}
                        onChange={(event) =>
                          updateFormField(
                            "is_active",
                            event.target.value === "active",
                          )
                        }
                        className={inputClass}
                      >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                      </select>
                    </Field>
                  </div>
                </section>

                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Informasi Kontak
                  </h3>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Nama Contact Person">
                      <input
                        value={formData.contact_person ?? ""}
                        onChange={(event) =>
                          updateFormField("contact_person", event.target.value)
                        }
                        placeholder="Nama kontak"
                        className={inputClass}
                      />
                    </Field>
                    <Field label="Email">
                      <input
                        type="email"
                        value={formData.email ?? ""}
                        onChange={(event) =>
                          updateFormField("email", event.target.value)
                        }
                        placeholder="email@perusahaan.com"
                        className={inputClass}
                      />
                    </Field>
                    <Field label="Nomor Telepon">
                      <input
                        value={formData.phone ?? ""}
                        onChange={(event) =>
                          updateFormField("phone", event.target.value)
                        }
                        placeholder="+62..."
                        className={inputClass}
                      />
                    </Field>
                  </div>
                </section>

                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Alamat Penagihan
                  </h3>
                  <div className="space-y-4">
                    <Field label="Alamat Lengkap">
                      <textarea
                        rows={2}
                        value={formData.billing_address ?? ""}
                        onChange={(event) =>
                          updateFormField("billing_address", event.target.value)
                        }
                        placeholder="Alamat penagihan perusahaan"
                        className={textareaClass}
                      />
                    </Field>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <Field label="Kota">
                        <input
                          value={formData.city ?? ""}
                          onChange={(event) =>
                            updateFormField("city", event.target.value)
                          }
                          placeholder="Kota"
                          className={inputClass}
                        />
                      </Field>
                      <Field label="Provinsi">
                        <input
                          value={formData.province ?? ""}
                          onChange={(event) =>
                            updateFormField("province", event.target.value)
                          }
                          placeholder="Provinsi"
                          className={inputClass}
                        />
                      </Field>
                      <Field label="Kode Pos">
                        <input
                          value={formData.postal_code ?? ""}
                          onChange={(event) =>
                            updateFormField("postal_code", event.target.value)
                          }
                          placeholder="Kode pos"
                          className={inputClass}
                        />
                      </Field>
                      <Field label="Negara">
                        <input
                          value={formData.country ?? ""}
                          onChange={(event) =>
                            updateFormField("country", event.target.value)
                          }
                          placeholder="Negara"
                          className={inputClass}
                        />
                      </Field>
                    </div>
                  </div>
                </section>

                <section className="space-y-3">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                    Catatan
                  </h3>
                  <Field label="Catatan Tambahan">
                    <textarea
                      rows={3}
                      value={formData.notes ?? ""}
                      onChange={(event) =>
                        updateFormField("notes", event.target.value)
                      }
                      placeholder="Informasi tambahan mengenai customer..."
                      className={textareaClass}
                    />
                  </Field>
                </section>
              </div>

              <div className="flex justify-end gap-2 border-t border-border bg-secondary/30 px-5 py-4">
                <button
                  type="button"
                  onClick={() => setIsFormOpen(false)}
                  disabled={isSaving}
                  className="h-10 rounded-lg border border-border bg-card px-4 text-sm font-semibold text-foreground transition hover:bg-secondary disabled:opacity-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={isSaving}
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover disabled:opacity-60"
                >
                  {isSaving ? (
                    <LoaderCircle size={16} className="animate-spin" />
                  ) : (
                    <Check size={16} />
                  )}
                  {isSaving ? "Menyimpan..." : "Simpan Customer"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {selectedCustomer && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-6">
          <div className="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
            <div className="flex items-start justify-between border-b border-border p-5">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Building2 size={21} />
                </div>
                <div>
                  <h2 className="text-lg font-bold text-foreground">
                    Detail Customer
                  </h2>
                  <p className="text-xs text-muted-foreground">
                    {selectedCustomer.customer_code || `CUS-${selectedCustomer.id}`}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setSelectedCustomer(null)}
                className="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary"
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
                  Memuat detail customer...
                </p>
              </div>
            ) : (
              <div className="space-y-5 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 className="text-base font-bold text-foreground">
                      {selectedCustomer.company_name}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                      NPWP: {selectedCustomer.tax_identification_number || "-"}
                    </p>
                  </div>
                  <StatusBadge active={selectedCustomer.is_active} />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div className="flex gap-3">
                    <UserRound
                      size={17}
                      className="mt-0.5 shrink-0 text-muted-foreground"
                    />
                    <div>
                      <p className="text-xs text-muted-foreground">
                        Contact Person
                      </p>
                      <p className="mt-1 text-sm font-medium text-foreground">
                        {selectedCustomer.contact_person || "-"}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <Mail
                      size={17}
                      className="mt-0.5 shrink-0 text-muted-foreground"
                    />
                    <div>
                      <p className="text-xs text-muted-foreground">Email</p>
                      <p className="mt-1 break-all text-sm font-medium text-foreground">
                        {selectedCustomer.email || "-"}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <Phone
                      size={17}
                      className="mt-0.5 shrink-0 text-muted-foreground"
                    />
                    <div>
                      <p className="text-xs text-muted-foreground">
                        Nomor Telepon
                      </p>
                      <p className="mt-1 text-sm font-medium text-foreground">
                        {selectedCustomer.phone || "-"}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <MapPin
                      size={17}
                      className="mt-0.5 shrink-0 text-muted-foreground"
                    />
                    <div>
                      <p className="text-xs text-muted-foreground">
                        Lokasi
                      </p>
                      <p className="mt-1 text-sm font-medium text-foreground">
                        {[
                          selectedCustomer.city,
                          selectedCustomer.province,
                          selectedCustomer.country,
                        ]
                          .filter(Boolean)
                          .join(", ") || "-"}
                      </p>
                    </div>
                  </div>
                </div>

                <div className="rounded-lg bg-secondary/60 p-3">
                  <p className="text-xs font-semibold text-muted-foreground">
                    Alamat Penagihan
                  </p>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">
                    {selectedCustomer.billing_address || "-"}
                  </p>
                  {selectedCustomer.postal_code && (
                    <p className="mt-1 text-xs text-muted-foreground">
                      Kode Pos: {selectedCustomer.postal_code}
                    </p>
                  )}
                </div>

                {selectedCustomer.notes && (
                  <div>
                    <p className="text-xs font-semibold text-muted-foreground">
                      Catatan
                    </p>
                    <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">
                      {selectedCustomer.notes}
                    </p>
                  </div>
                )}

                <div className="border-t border-border pt-3 text-xs text-muted-foreground">
                  <p>Dibuat: {formatDate(selectedCustomer.created_at)}</p>
                  <p className="mt-1">
                    Terakhir diperbarui:{" "}
                    {formatDate(selectedCustomer.updated_at)}
                  </p>
                </div>

                <div className="flex flex-wrap justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      const customer = selectedCustomer;
                      setSelectedCustomer(null);
                      openEditForm(customer);
                    }}
                    className="inline-flex h-9 items-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-foreground transition hover:bg-secondary"
                  >
                    <Pencil size={15} />
                    Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => void handleToggleStatus(selectedCustomer)}
                    disabled={isUpdatingStatus === selectedCustomer.id}
                    className="h-9 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary-hover disabled:opacity-50"
                  >
                    {selectedCustomer.is_active
                      ? "Nonaktifkan"
                      : "Aktifkan"}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {notice && (
        <div className="fixed bottom-5 right-5 z-[120] flex max-w-sm items-start gap-3 rounded-xl border border-border bg-card p-4 shadow-xl">
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
              {notice.type === "success" ? "Berhasil" : "Terjadi Kesalahan"}
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
