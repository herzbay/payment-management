"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AlertCircle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Package,
  Pencil,
  Plus,
  Power,
  RefreshCw,
  Search,
  X,
} from "lucide-react";

import { serviceService } from "@/lib/master-data";
import type {
  Service,
  ServicePayload,
  MasterDataStatus,
} from "@/types/master-data";

type ServiceForm = Omit<ServicePayload, "default_unit_price"> & {
  default_unit_price: string;
};

const initialForm: ServiceForm = {
  service_code: "",
  name: "",
  category: "",
  description: "",
  default_unit: "Unit",
  default_unit_price: "",
  is_active: true,
};

export default function ServicesPage() {
  const [services, setServices] = useState<Service[]>([]);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<MasterDataStatus>("all");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [updatingStatusId, setUpdatingStatusId] = useState<number | null>(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const [modalOpen, setModalOpen] = useState(false);
  const [editingService, setEditingService] = useState<Service | null>(null);
  const [form, setForm] = useState<ServiceForm>(initialForm);

  const loadServices = useCallback(
    async (showLoading = true) => {
      if (showLoading) {
        setLoading(true);
      }

      setError("");

      try {
        const result = await serviceService.getAll({
          search: search.trim() || undefined,
          is_active: status === "all" ? undefined : status === "active",
          page,
          per_page: 10,
        });

        setServices(result.data);
        setLastPage(result.meta.last_page);
        setTotal(result.meta.total);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : "Gagal mengambil data layanan."
        );
      } finally {
        if (showLoading) {
          setLoading(false);
        }
      }
    },
    [search, status, page]
  );

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadServices(true);
    }, 250);

    return () => window.clearTimeout(timeoutId);
  }, [loadServices]);

  function openCreateModal() {
    setEditingService(null);
    setForm({ ...initialForm });
    setError("");
    setModalOpen(true);
  }

  function openEditModal(service: Service) {
    setEditingService(service);
    setForm({
      service_code: service.service_code,
      name: service.name,
      category: service.category ?? "",
      description: service.description ?? "",
      default_unit: service.default_unit,
      default_unit_price: String(service.default_unit_price),
      is_active: service.is_active,
    });
    setError("");
    setModalOpen(true);
  }

  function closeModal() {
    if (saving) return;

    setModalOpen(false);
    setEditingService(null);
    setForm({ ...initialForm });
    setError("");
  }

  function updateField<K extends keyof ServiceForm>(
    field: K,
    value: ServiceForm[K]
  ) {
    setForm((previous) => ({
      ...previous,
      [field]: value,
    }));
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const price = Number(form.default_unit_price);

    if (
      form.default_unit_price.trim() === "" ||
      !Number.isFinite(price) ||
      price < 0
    ) {
      setError("Harga satuan wajib diisi dan tidak boleh negatif.");
      return;
    }

    if (!form.name.trim()) {
      setError("Nama layanan wajib diisi.");
      return;
    }

    if (!form.default_unit.trim()) {
      setError("Satuan wajib diisi.");
      return;
    }

    setSaving(true);
    setError("");
    setSuccess("");

    try {
      const payload: ServicePayload = {
        service_code: form.service_code?.trim() || undefined,
        name: form.name.trim(),
        category: form.category?.trim() || null,
        description: form.description?.trim() || null,
        default_unit: form.default_unit.trim(),
        default_unit_price: price,
        is_active: form.is_active,
      };

      if (editingService) {
        await serviceService.update(editingService.id, payload);
        setSuccess("Layanan berhasil diperbarui.");
      } else {
        await serviceService.create(payload);
        setSuccess("Layanan berhasil ditambahkan.");
      }

      setModalOpen(false);
      setEditingService(null);
      setForm({ ...initialForm });

      await loadServices(false);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Gagal menyimpan data layanan."
      );
    } finally {
      setSaving(false);
    }
  }

  async function handleStatusToggle(service: Service) {
    const action = service.is_active ? "menonaktifkan" : "mengaktifkan";

    if (
      !window.confirm(
        `Apakah kamu yakin ingin ${action} layanan ${service.name}?`
      )
    ) {
      return;
    }

    setError("");
    setSuccess("");
    setUpdatingStatusId(service.id);

    try {
      await serviceService.updateStatus(service.id, !service.is_active);

      setSuccess(
        `Layanan berhasil ${
          service.is_active ? "dinonaktifkan" : "diaktifkan"
        }.`
      );

      await loadServices(false);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Gagal mengubah status layanan."
      );
    } finally {
      setUpdatingStatusId(null);
    }
  }

  function formatCurrency(value: number) {
    return new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    }).format(value);
  }

  function formatDate(value: string) {
    return new Intl.DateTimeFormat("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(new Date(value));
  }

  const inputClass =
    "w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20";

  const labelClass = "mb-1.5 block text-sm font-medium text-foreground";

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div className="flex items-center gap-2">
            <Package className="h-6 w-6 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">
              Service Management
            </h1>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Kelola daftar layanan sertifikasi dan harga satuan.
          </p>
        </div>

        <button
          type="button"
          onClick={openCreateModal}
          className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary-hover"
        >
          <Plus className="h-4 w-4" />
          Tambah Layanan
        </button>
      </div>

      {success && (
        <div className="fixed bottom-5 right-5 z-[70] flex w-[calc(100%-2.5rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-card p-4 shadow-lg">
          <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-success" />
          <p className="flex-1 text-sm font-medium text-foreground">
            {success}
          </p>
          <button
            type="button"
            onClick={() => setSuccess("")}
            aria-label="Tutup notifikasi"
            className="rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {error && (
        <div className="flex items-center gap-2 rounded-lg border border-danger/30 bg-danger-background px-4 py-3 text-sm text-danger">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-4">
          <p className="text-sm text-muted-foreground">Total Layanan</p>
          <p className="mt-2 text-2xl font-bold tabular-nums">{total}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-4">
          <p className="text-sm text-muted-foreground">
            Layanan Aktif di Halaman Ini
          </p>
          <p className="mt-2 text-2xl font-bold tabular-nums">
            {services.filter((service) => service.is_active).length}
          </p>
        </div>
      </div>

      <div className="rounded-xl border border-border bg-card">
        <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              type="search"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              placeholder="Cari kode atau nama layanan..."
              className={`${inputClass} pl-9`}
            />
          </div>

          <select
            value={status}
            onChange={(event) => {
              setStatus(event.target.value as MasterDataStatus);
              setPage(1);
            }}
            className={`${inputClass} sm:w-48`}
          >
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>

          <button
            type="button"
            onClick={() => void loadServices(true)}
            disabled={loading}
            className="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted disabled:opacity-50"
          >
            <RefreshCw
              className={`h-4 w-4 ${loading ? "animate-spin" : ""}`}
            />
            Refresh
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="bg-muted/50 text-muted-foreground">
              <tr>
                <th className="px-4 py-3 font-medium">Kode</th>
                <th className="px-4 py-3 font-medium">Nama Layanan</th>
                <th className="px-4 py-3 font-medium">Kategori</th>
                <th className="px-4 py-3 font-medium">Harga Satuan</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Dibuat</th>
                <th className="px-4 py-3 text-right font-medium">Aksi</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center">
                    <Loader2 className="mx-auto h-6 w-6 animate-spin text-primary" />
                    <p className="mt-2 text-muted-foreground">
                      Memuat data layanan...
                    </p>
                  </td>
                </tr>
              ) : services.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center">
                    <Package className="mx-auto h-8 w-8 text-muted-foreground" />
                    <p className="mt-2 font-medium">Belum ada data layanan</p>
                    <p className="mt-1 text-muted-foreground">
                      Tambahkan layanan untuk mulai menggunakan master data.
                    </p>
                  </td>
                </tr>
              ) : (
                services.map((service) => (
                  <tr
                    key={service.id}
                    className="transition hover:bg-muted/30"
                  >
                    <td className="px-4 py-3 font-medium">
                      {service.service_code}
                    </td>
                    <td className="px-4 py-3">
                      <p className="font-medium">{service.name}</p>
                      {service.description && (
                        <p className="mt-1 max-w-xs truncate text-xs text-muted-foreground">
                          {service.description}
                        </p>
                      )}
                    </td>
                    <td className="px-4 py-3">{service.category || "-"}</td>
                    <td className="px-4 py-3 tabular-nums">
                      {formatCurrency(service.default_unit_price)}
                      <span className="ml-1 text-xs text-muted-foreground">
                        / {service.default_unit}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                          service.is_active
                            ? "bg-success-background text-success"
                            : "bg-muted text-muted-foreground"
                        }`}
                      >
                        {service.is_active ? "Aktif" : "Nonaktif"}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {formatDate(service.created_at)}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => openEditModal(service)}
                          title="Edit layanan"
                          className="rounded-lg border border-border p-2 transition hover:bg-muted"
                        >
                          <Pencil className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => void handleStatusToggle(service)}
                          disabled={updatingStatusId === service.id}
                          title={
                            service.is_active ? "Nonaktifkan" : "Aktifkan"
                          }
                          className="rounded-lg border border-border p-2 transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                        >
                          {updatingStatusId === service.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Power
                              className={`h-4 w-4 ${
                                service.is_active
                                  ? "text-success"
                                  : "text-muted-foreground"
                              }`}
                            />
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

        <div className="flex flex-col justify-between gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center">
          <p className="text-sm text-muted-foreground">
            Total {total} layanan
          </p>

          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={() =>
                setPage((previous) => Math.max(1, previous - 1))
              }
              disabled={page <= 1 || loading}
              className="rounded-lg border border-border p-2 transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Halaman sebelumnya"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>

            <span className="text-sm tabular-nums">
              Halaman {page} dari {lastPage}
            </span>

            <button
              type="button"
              onClick={() =>
                setPage((previous) => Math.min(lastPage, previous + 1))
              }
              disabled={page >= lastPage || loading}
              className="rounded-lg border border-border p-2 transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Halaman berikutnya"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
          <div className="my-auto w-full max-w-xl rounded-xl border border-border bg-card shadow-xl">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <div>
                <h2 className="text-lg font-semibold">
                  {editingService ? "Edit Layanan" : "Tambah Layanan"}
                </h2>
                <p className="mt-1 text-xs text-muted-foreground">
                  Lengkapi informasi layanan sertifikasi.
                </p>
              </div>

              <button
                type="button"
                onClick={closeModal}
                disabled={saving}
                className="rounded-lg p-2 transition hover:bg-muted"
                aria-label="Tutup modal"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4 p-5">
              {error && (
                <div className="rounded-lg border border-danger/30 bg-danger-background p-3 text-sm text-danger">
                  {error}
                </div>
              )}

              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Kode Layanan</label>
                  <input
                    value={form.service_code ?? ""}
                    onChange={(event) =>
                      updateField("service_code", event.target.value)
                    }
                    placeholder="Contoh: SERT-001"
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className={labelClass}>Nama Layanan *</label>
                  <input
                    required
                    value={form.name}
                    onChange={(event) =>
                      updateField("name", event.target.value)
                    }
                    placeholder="Nama layanan"
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className={labelClass}>Kategori</label>
                  <input
                    value={form.category ?? ""}
                    onChange={(event) =>
                      updateField("category", event.target.value)
                    }
                    placeholder="Contoh: Sertifikasi"
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className={labelClass}>Satuan *</label>
                  <input
                    required
                    value={form.default_unit}
                    onChange={(event) =>
                      updateField("default_unit", event.target.value)
                    }
                    placeholder="Contoh: Unit"
                    className={inputClass}
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className={labelClass}>
                    Harga Satuan (IDR) *
                  </label>
                  <input
                    required
                    type="number"
                    min="0"
                    step="0.01"
                    value={form.default_unit_price}
                    onChange={(event) =>
                      updateField("default_unit_price", event.target.value)
                    }
                    placeholder="Masukkan harga satuan"
                    className={inputClass}
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className={labelClass}>Deskripsi</label>
                  <textarea
                    rows={3}
                    value={form.description ?? ""}
                    onChange={(event) =>
                      updateField("description", event.target.value)
                    }
                    placeholder="Deskripsi layanan..."
                    className={inputClass}
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={form.is_active ?? true}
                      onChange={(event) =>
                        updateField("is_active", event.target.checked)
                      }
                      className="h-4 w-4 rounded border-border accent-primary"
                    />
                    Layanan aktif
                  </label>
                </div>
              </div>

              <div className="flex justify-end gap-3 border-t border-border pt-4">
                <button
                  type="button"
                  onClick={closeModal}
                  disabled={saving}
                  className="rounded-lg border border-border px-4 py-2.5 text-sm font-medium transition hover:bg-muted disabled:opacity-50"
                >
                  Batal
                </button>

                <button
                  type="submit"
                  disabled={saving}
                  className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary-hover disabled:opacity-50"
                >
                  {saving && (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  )}
                  {editingService ? "Simpan Perubahan" : "Simpan Layanan"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
