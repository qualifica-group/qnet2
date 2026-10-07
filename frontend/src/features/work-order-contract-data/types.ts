/**
 * Work order contract data types. Source of truth: spec 0201 frozen
 * `data_contract`. Amounts travel as decimal STRINGS ("1000.00"); the frontend
 * only formats them, every figure is computed server-side.
 */

import type { SupplierCommissionDirection } from '@/features/product-typologies/types'

export type ContractCommissionType = 'PERCENTAGE' | 'FIXED_AMOUNT'

export type ContractDataWarning = 'missing_supplier_commission' | 'stale_commission_base'

/** The Supplier commission of a line; `null` on the line when absent or not visible. */
export interface ContractSupplierCommission {
  commission_type: ContractCommissionType
  /** "15.0000": a percentage or a fixed amount depending on `commission_type`. */
  value: string
  /** Margin the percentage was applied to; `null` for FIXED_AMOUNT. */
  base_amount: string | null
  amount: string
  is_stale: boolean
}

export interface ContractPaymentStatusRef {
  id: number
  name: string
  color: string
  allows_delivery: boolean
}

export interface ContractLinePayment {
  status: ContractPaymentStatusRef | null
  payment_agreement: string | null
  has_unpaid: boolean
}

export interface ContractDataLine {
  quote_line_id: number
  product: { id: number; code: string; name: string }
  typology: { id: number; code: string; name: string } | null
  /** Spec 0202 D-11: read from the row's frozen snapshot; null = Supplier commission not calculated. */
  supplier_commission_direction: SupplierCommissionDirection | null
  quantity: string
  unit_price: string
  net_amount: string
  supplier_commission: ContractSupplierCommission | null
  commissions_amount: string | null
  net_of_commissions: string | null
  effective_revenue: string
  warnings: ContractDataWarning[]
  payment: ContractLinePayment
}

export interface ContractTypologyTotal {
  id: number
  name: string
  net_amount: string
  effective_revenue: string
}

export interface ContractDataTotals {
  net_amount: string
  /** One entry per configured product typology, already ordered and zero-filled by the server. */
  typologies: ContractTypologyTotal[]
  effective_revenue: string
  commissions_amount: string | null
  net_of_commissions: string | null
}

export interface WorkOrderContractData {
  lines: ContractDataLine[]
  totals: ContractDataTotals
  commissions_visible: boolean
}

/** PATCH body: only the keys sent change. */
export interface UpdateContractLinePayload {
  work_order_payment_status_id?: number | null
  payment_agreement?: string | null
  has_unpaid?: boolean
}

/** A for-select item of `work-order-payment-statuses` (flat projection, spec 0201). */
export interface PaymentStatusOption {
  id: number
  name: string
  color: string
  allows_delivery: boolean
}
