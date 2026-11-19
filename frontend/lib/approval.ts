import type { Quotation } from "@/types/quotation";

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ApprovalQueueResult {
  data: Quotation[];
  meta: PaginationMeta;
}

export interface Approver {
  id: number;
  name: string;
  email: string;
  role_name: string;
}

async function approvalRequest<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const response = await fetch(`/api${endpoint}`, {
    ...options,
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...options.headers,
    },
  });

  const result = await response.json().catch(() => null);

  if (!response.ok) {
    const validationErrors = result?.errors
      ? Object.values(result.errors).flat().join(" ")
      : "";

    throw new Error(
      validationErrors ||
        result?.message ||
        "Terjadi kesalahan saat menghubungi server.",
    );
  }

  return result as T;
}

export const approvalService = {
  async getPending(
    page = 1,
    perPage = 10,
    search = "",
  ): Promise<ApprovalQueueResult> {
    const params = new URLSearchParams({
      status: "pending_approval",
      page: String(page),
      per_page: String(perPage),
    });

    if (search.trim()) {
      params.set("search", search.trim());
    }

    const response = await approvalRequest<
      ApiResponse<ApprovalQueueResult>
    >(`/quotations?${params.toString()}`);

    return response.data;
  },

  async getDetail(id: number): Promise<Quotation> {
    const response = await approvalRequest<ApiResponse<Quotation>>(
      `/quotations/${id}`,
    );

    return response.data;
  },

  async approve(id: number, comments = ""): Promise<void> {
    await approvalRequest<ApiResponse<unknown>>(
      `/quotations/${id}/approve`,
      {
        method: "POST",
        body: JSON.stringify({ comments }),
      },
    );
  },

  async reject(id: number, comments: string): Promise<void> {
    await approvalRequest<ApiResponse<unknown>>(
      `/quotations/${id}/reject`,
      {
        method: "POST",
        body: JSON.stringify({ comments }),
      },
    );
  },
    async getApprovers(): Promise<Approver[]> {
    const response = await approvalRequest<ApiResponse<Approver[]>>(
        "/approvers",
    );

    return response.data;
    },

    async submit(id: number, approverId: number): Promise<void> {
    await approvalRequest<ApiResponse<unknown>>(
        `/quotations/${id}/submit`,
        {
        method: "POST",
        body: JSON.stringify({
            approver_id: approverId,
        }),
        },
    );
    },
};
