import type { PurchaseOrder, PurchaseOrderLine } from '@/features/purchase-orders/types'

/** Test fixture: one persisted line of Collaboratori. */
export function makeOrderLine(overrides: Partial<PurchaseOrderLine> = {}): PurchaseOrderLine {
  return {
    id: 11,
    position: 1,
    product: null,
    description: 'Consulting',
    cost_type: { id: 1, name: 'Collaboratori' },
    unit_of_measure: { id: 1, name: 'Hour', symbol: 'h' },
    quantity: '2.000',
    unit_price: '100.00',
    vat_rate: { id: 1, name: 'IVA 22%', rate: '22.00' },
    taxable_amount: '200.00',
    vat_amount: '44.00',
    total_amount: '244.00',
    work_order: null,
    purchase_request_line: null,
    ...overrides,
  }
}

/** Test fixture: a draft ODA with one line, fully editable by default. */
export function makePurchaseOrder(overrides: Partial<PurchaseOrder> = {}): PurchaseOrder {
  return {
    id: 5,
    number: 3,
    year: 2026,
    code: '3/2026',
    status: 'draft',
    supplier: { id: 8, name: 'Acme Supplies', email: 'orders@acme.test', is_qualified_supplier: true },
    company: { id: 1, name: 'Qualifica Srl' },
    company_site: { id: 2, name: 'Napoli' },
    operational_site: null,
    order_date: '2026-10-12',
    description: 'Monthly consulting',
    general_notes: null,
    payment_method: null,
    is_internal_user: false,
    customer: null,
    work_order: null,
    quote: null,
    opportunity: null,
    sectors: [],
    sector_notes: null,
    taxable_total: '200.00',
    vat_total: '44.00',
    grand_total: '244.00',
    copy_group_id: null,
    created_by: { id: 3, name: 'Mario Rossi' },
    lines: [makeOrderLine()],
    merge_logs_count: 0,
    created_at: '2026-10-12T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
    abilities: {
      update: true,
      delete: true,
      merge: true,
      send: true,
      view_activity: true,
      transitions: ['in_validation', 'approved', 'cancelled'],
    },
    field_permissions: {},
    ...overrides,
  }
}
