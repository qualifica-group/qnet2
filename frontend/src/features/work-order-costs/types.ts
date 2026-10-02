/**
 * Work order costs types. Source of truth: spec 0190 frozen `data_contract`
 * (`WorkOrderCostLine`, `WorkOrderCostOverview`). Decimals travel as numeric
 * STRINGS; convert with `Number(...)` only at the point of calculation.
 */

import type { QuoteLine, QuoteLineProductRef, QuoteLineUnitOfMeasureRef, QuoteLineVatRateRef } from '@/features/quotes/types'

/** One persisted real cost row of the work order. */
export interface WorkOrderCostLine {
  id: number
  product_id: number
  product: QuoteLineProductRef
  quantity: string
  unit_of_measure: QuoteLineUnitOfMeasureRef | null
  unit_price: string
  vat_rate_id: number | null
  vat_rate: QuoteLineVatRateRef | null
  net_amount: string
  vat_amount: string
  total_amount: string
  quote_line_id: number | null
  /** `YYYY-MM-DD`. */
  incurred_on: string
  supplier_id: number | null
  supplier: { id: number; name: string } | null
  document_reference: string | null
  additional_description: string | null
  sort_order: number
}

/** One revenue line of the work order with its budget/actual cost comparison. */
export interface WorkOrderCostComparisonRow {
  quote_line_id: number
  product: { id: number; code: string; name: string }
  revenue_net: string
  budget_cost_net: string
  actual_cost_net: string
  delta_net: string
}

export interface WorkOrderCostTotals {
  revenue_net: string
  budget_cost_net: string
  actual_cost_net: string
  delta_net: string
  budget_margin_net: string
  actual_margin_net: string
}

export interface WorkOrderCostComparison {
  rows: WorkOrderCostComparisonRow[]
  unattributed_actual_cost_net: string
  totals: WorkOrderCostTotals
}

export interface WorkOrderCostBudget {
  allocated_lines: QuoteLine[]
  unallocated_lines: QuoteLine[]
}

/** `data` of GET/PUT `/work-orders/{id}/costs`. */
export interface WorkOrderCostOverview {
  lines: WorkOrderCostLine[]
  budget: WorkOrderCostBudget
  comparison: WorkOrderCostComparison
}

/** One row of the PUT body: only inputs travel, amounts and unit of measure are server-side. */
export interface WorkOrderCostLineInput {
  id?: number
  product_id: number
  quantity: number
  unit_price: number
  vat_rate_id: number | null
  quote_line_id: number | null
  incurred_on: string
  supplier_id: number | null
  document_reference: string | null
  additional_description: string | null
}

export interface SyncWorkOrderCostsPayload {
  lines: WorkOrderCostLineInput[]
}
