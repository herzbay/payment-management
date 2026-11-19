export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface DashboardPeriod {
  from: string;
  to: string;
}

export interface FinancialSummary {
  currency: string;
  total_invoiced: number;
  verified_payments: number;
  completed_refunds: number;
  net_receipts: number;
  invoice_count: number;
  verified_payment_count: number;
  completed_refund_count: number;
}

export interface ReceivablesSummary {
  currency: string;
  outstanding_amount: number;
  overdue_amount: number;
  overdue_invoice_count: number;
}

export interface DashboardData {
  period: DashboardPeriod;
  financial_summary: FinancialSummary[];
  receivables_summary: ReceivablesSummary[];
  pending_verifications: number;
  generated_at: string;
}

export interface RevenueItem {
  period: string;
  currency: string;
  total_payments: number;
  payment_count: number;
  total_refunds: number;
  refund_count: number;
  net_receipts: number;
}

export interface RevenueTotal {
  currency: string;
  payments: number;
  refunds: number;
  net_receipts: number;
}

export interface RevenueData {
  period: DashboardPeriod;
  totals: RevenueTotal[];
  data: RevenueItem[];
}
