import type {
  Customer,
  CustomerPayload,
  MasterDataFilters,
  PaginatedResult,
  PaginationMeta,
  Service,
  ServicePayload,
} from "@/types/master-data";

interface ApiResult<T> {
  success?: boolean;
  message?: string;
  errors?: Record<string, string[]>;
  data?: T;
}

const DEFAULT_PAGINATION: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: null,
  to: null,
};

async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const response = await fetch(endpoint, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...options.headers,
    },
    credentials: "same-origin",
    cache: "no-store",
  });

  const result = (await response.json().catch(() => null)) as
    | ApiResult<T>
    | T
    | null;

  if (!response.ok) {
    const errorResult = result as ApiResult<T> | null;

    const validationMessages = errorResult?.errors
      ? Object.values(errorResult.errors).flat().join(" ")
      : "";

    throw new Error(
      validationMessages ||
        errorResult?.message ||
        `Request gagal dengan status ${response.status}.`,
    );
  }

  if (!result) {
    throw new Error("Server mengembalikan respons kosong.");
  }

  if (
    typeof result === "object" &&
    "success" in result &&
    result.success === false
  ) {
    throw new Error(
      (result as ApiResult<T>).message || "Request gagal.",
    );
  }

  if (
    typeof result === "object" &&
    "data" in result &&
    result.data !== undefined
  ) {
    return result.data as T;
  }

  return result as T;
}

function buildQuery(filters: MasterDataFilters = {}): string {
  const params = new URLSearchParams();

  if (filters.search?.trim()) {
    params.set("search", filters.search.trim());
  }

  // Laravel boolean validation accepts 1 and 0,
  // but does not accept the query strings "true" and "false".
  if (filters.is_active !== undefined) {
    params.set("is_active", filters.is_active ? "1" : "0");
  }

  if (filters.page !== undefined) {
    params.set("page", String(filters.page));
  }

  if (filters.per_page !== undefined) {
    params.set("per_page", String(filters.per_page));
  }

  const query = params.toString();

  return query ? `?${query}` : "";
}

function normalizePaginatedResult<T>(
  result: unknown,
): PaginatedResult<T> {
  if (Array.isArray(result)) {
    return {
      data: result as T[],
      meta: {
        ...DEFAULT_PAGINATION,
        total: result.length,
        from: result.length > 0 ? 1 : null,
        to: result.length > 0 ? result.length : null,
      },
    };
  }

  if (!result || typeof result !== "object") {
    return {
      data: [],
      meta: DEFAULT_PAGINATION,
    };
  }

  const response = result as Record<string, unknown>;

  const rows = Array.isArray(response.data)
    ? (response.data as T[])
    : Array.isArray(response.items)
      ? (response.items as T[])
      : [];

  const metaSource =
    response.meta && typeof response.meta === "object"
      ? (response.meta as Record<string, unknown>)
      : response;

  const numberValue = (
    value: unknown,
    fallback: number,
  ): number => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  return {
    data: rows,
    meta: {
      current_page: numberValue(metaSource.current_page, 1),
      last_page: numberValue(metaSource.last_page, 1),
      per_page: numberValue(metaSource.per_page, rows.length || 10),
      total: numberValue(metaSource.total, rows.length),
      from:
        metaSource.from === null
          ? null
          : numberValue(metaSource.from, rows.length > 0 ? 1 : 0),
      to:
        metaSource.to === null
          ? null
          : numberValue(metaSource.to, rows.length),
    },
  };
}

function getResource<T>(result: unknown): T {
  if (
    result &&
    typeof result === "object" &&
    "data" in result &&
    (result as { data?: unknown }).data !== undefined
  ) {
    return (result as { data: T }).data;
  }

  return result as T;
}

export const customerService = {
  async getAll(
    filters: MasterDataFilters = {},
  ): Promise<PaginatedResult<Customer>> {
    const result = await apiRequest<unknown>(
      `/api/customers${buildQuery(filters)}`,
    );

    return normalizePaginatedResult<Customer>(result);
  },

  async getById(id: number): Promise<Customer> {
    const result = await apiRequest<unknown>(`/api/customers/${id}`);
    return getResource<Customer>(result);
  },

  async create(payload: CustomerPayload): Promise<Customer> {
    const result = await apiRequest<unknown>("/api/customers", {
      method: "POST",
      body: JSON.stringify(payload),
    });

    return getResource<Customer>(result);
  },

  async update(
    id: number,
    payload: CustomerPayload,
  ): Promise<Customer> {
    const result = await apiRequest<unknown>(`/api/customers/${id}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });

    return getResource<Customer>(result);
  },

  async updateStatus(
    id: number,
    isActive: boolean,
  ): Promise<Customer> {
    const result = await apiRequest<unknown>(
      `/api/customers/${id}/status`,
      {
        method: "PATCH",
        body: JSON.stringify({ is_active: isActive }),
      },
    );

    return getResource<Customer>(result);
  },
};

export const serviceService = {
  async getAll(
    filters: MasterDataFilters = {},
  ): Promise<PaginatedResult<Service>> {
    const result = await apiRequest<unknown>(
      `/api/services${buildQuery(filters)}`,
    );

    return normalizePaginatedResult<Service>(result);
  },

  async getById(id: number): Promise<Service> {
    const result = await apiRequest<unknown>(`/api/services/${id}`);
    return getResource<Service>(result);
  },

  async create(payload: ServicePayload): Promise<Service> {
    const result = await apiRequest<unknown>("/api/services", {
      method: "POST",
      body: JSON.stringify(payload),
    });

    return getResource<Service>(result);
  },

  async update(
    id: number,
    payload: ServicePayload,
  ): Promise<Service> {
    const result = await apiRequest<unknown>(`/api/services/${id}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });

    return getResource<Service>(result);
  },

  async updateStatus(
    id: number,
    isActive: boolean,
  ): Promise<Service> {
    const result = await apiRequest<unknown>(
      `/api/services/${id}/status`,
      {
        method: "PATCH",
        body: JSON.stringify({ is_active: isActive }),
      },
    );

    return getResource<Service>(result);
  },
};
