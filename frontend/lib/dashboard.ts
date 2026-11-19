import type {
  ApiResponse,
  DashboardData,
  RevenueData,
} from "@/types/dashboard";

interface DashboardFilters {
  from: string;
  to: string;
}

function buildQuery(filters: DashboardFilters): string {
  const params = new URLSearchParams({
    from: filters.from,
    to: filters.to,
  });

  return params.toString();
}

async function fetchReport<T>(endpoint: string): Promise<T> {
  const response = await fetch(endpoint, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
    credentials: "same-origin",
    cache: "no-store",
  });

  const result = (await response.json()) as ApiResponse<T> & {
    message?: string;
  };

  if (!response.ok) {
    throw new Error(
      result.message || "Gagal mengambil data laporan."
    );
  }

  if (result.success !== true || result.data === undefined) {
    throw new Error("Format respons laporan dari server tidak valid.");
  }

  return result.data;
}

export const dashboardService = {
  async getDashboard(
    filters: DashboardFilters
  ): Promise<DashboardData> {
    const query = buildQuery(filters);

    return fetchReport<DashboardData>(
      `/api/reports/dashboard?${query}`
    );
  },

  async getRevenue(
    filters: DashboardFilters
  ): Promise<RevenueData> {
    const query = buildQuery(filters);

    return fetchReport<RevenueData>(
      `/api/reports/revenue?${query}`
    );
  },
};
