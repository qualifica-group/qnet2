import type { QuoteLine } from '@/features/quotes/types'
import type { WorkOrderCostLine, WorkOrderCostOverview } from '@/features/work-order-costs/types'

const PRODUCT = { id: 7, code: 'CST-0007', name: 'Trasferta', category: { id: 1, name: 'Spese' } }

export const COST_LINE: WorkOrderCostLine = {
  id: 5,
  product_id: 7,
  product: { ...PRODUCT, product_typology: null, business_function: null },
  quantity: '2.00',
  unit_of_measure: { id: 1, name: 'Pezzo', symbol: 'pz' },
  unit_price: '50.00',
  vat_rate_id: 3,
  vat_rate: { id: 3, name: 'IVA 22%', rate: '22.00' },
  net_amount: '100.00',
  vat_amount: '22.00',
  total_amount: '122.00',
  quote_line_id: 11,
  incurred_on: '2026-09-15',
  supplier_id: 40,
  supplier: { id: 40, name: 'Fornitore Uno' },
  document_reference: 'FT-12',
  additional_description: null,
  sort_order: 0,
}

/** A budget COST line of the offer, as `QuoteLineResource` exposes it. */
export function budgetLine(id: number, net: string): QuoteLine {
  return {
    id,
    product_id: 7,
    product: { ...PRODUCT, product_typology: null, business_function: null },
    quantity: '1.00',
    unit_of_measure: null,
    additional_description: null,
    unit_price: net,
    vat_rate_id: null,
    vat_rate: null,
    net_amount: net,
    vat_amount: '0.00',
    total_amount: net,
    sort_order: 0,
  }
}

export const OVERVIEW: WorkOrderCostOverview = {
  lines: [COST_LINE],
  budget: {
    allocated_lines: [budgetLine(21, '80.00')],
    unallocated_lines: [budgetLine(22, '30.00')],
  },
  comparison: {
    rows: [
      {
        quote_line_id: 11,
        product: { id: 1, code: 'PRD-0001', name: 'Consulenza' },
        revenue_net: '500.00',
        budget_cost_net: '80.00',
        actual_cost_net: '100.00',
        delta_net: '20.00',
      },
      {
        quote_line_id: 12,
        product: { id: 2, code: 'PRD-0002', name: 'Installazione' },
        revenue_net: '300.00',
        budget_cost_net: '0.00',
        actual_cost_net: '0.00',
        delta_net: '0.00',
      },
    ],
    unattributed_actual_cost_net: '0.00',
    totals: {
      revenue_net: '800.00',
      budget_cost_net: '80.00',
      actual_cost_net: '100.00',
      delta_net: '20.00',
      budget_margin_net: '720.00',
      actual_margin_net: '700.00',
    },
  },
}
