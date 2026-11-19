import type { Customer, Service, PaginationMeta } from "@/types/master-data";

export type QuotationStatus =
  | "draft"
  | "pending_approval"
  | "approved"
  | "rejected";

export interface QuotationFilters {
  search?: string;
  status?: QuotationStatus;
  customer_id?: number;
  page?: number;
  per_page?: number;
}

export interface QuotationItem {
  id: number;
  quotation_id: number;
  service_id: number;
  line_number: number;
  description: string;
  quantity: number;
  unit: string;
  unit_price: number;
  discount_amount: number;
  tax_percentage: number;
  line_total: number;
  service?: Service;
}

export interface QuotationTerm {
  id?: number;
  quotation_id?: number;
  sort_order?: number;
  title: string;
  content: string;
  is_required: boolean;
}

export interface QuotationApproval {
  id: number;
  quotation_id: number;
  approver_id: number;
  approval_level: number;
  status: string;
  comments: string | null;
  decided_at: string | null;
  approver?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface Quotation {
  id: number;
  quotation_number: string;
  customer_id: number;
  subject: string;
  description: string | null;
  issue_date: string;
  valid_until: string;
  currency: string;
  subtotal: number;
  total_discount: number;
  tax_percentage: number;
  tax_amount: number;
  total_amount: number;
  status: QuotationStatus;
  internal_notes: string | null;
  customer_notes: string | null;
  sent_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  customer?: Customer;
  items?: QuotationItem[];
  terms?: QuotationTerm[];
  approvals?: QuotationApproval[];
  created_by_user?: {
    id: number;
    name: string;
    email: string;
  };
  agreement?: {
    id: number;
    agreement_number: string;
    title: string;
    status: string;
  } | null;
}

export interface QuotationItemPayload {
  service_id: number;
  description: string;
  quantity: number;
  unit: string;
  unit_price: number;
  discount_amount?: number;
  tax_percentage?: number;
}

export interface QuotationPayload {
  customer_id: number;
  subject: string;
  description?: string | null;
  issue_date: string;
  valid_until: string;
  currency?: string;
  tax_percentage?: number;
  internal_notes?: string | null;
  customer_notes?: string | null;
  items: QuotationItemPayload[];
  terms?: QuotationTerm[];
}

export interface PaginatedQuotations {
  data: Quotation[];
  meta: PaginationMeta;
}
