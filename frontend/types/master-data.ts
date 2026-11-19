export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface PaginatedResult<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface MasterDataFilters {
  search?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
}

export interface Customer {
  id: number;
  customer_code: string;
  company_name: string;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  tax_identification_number: string | null;
  billing_address: string | null;
  city: string | null;
  province: string | null;
  postal_code: string | null;
  country: string | null;
  notes: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface CustomerPayload {
  customer_code?: string;
  company_name: string;
  contact_person?: string | null;
  email?: string | null;
  phone?: string | null;
  tax_identification_number?: string | null;
  billing_address?: string | null;
  city?: string | null;
  province?: string | null;
  postal_code?: string | null;
  country?: string | null;
  notes?: string | null;
  is_active?: boolean;
}

export interface Service {
  id: number;
  service_code: string;
  name: string;
  category: string | null;
  description: string | null;
  default_unit: string;
  default_unit_price: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface ServicePayload {
  service_code?: string;
  name: string;
  category?: string | null;
  description?: string | null;
  default_unit: string;
  default_unit_price: number;
  is_active?: boolean;
}

export type MasterDataStatus = "all" | "active" | "inactive";
