import type {
  LineStatus,
  PurchaseRequest,
  PurchaseRequestLine,
} from '@/features/purchase-requests/types'

/** Test fixture: one persisted line, `pending_approval` and fully editable by default. */
export function makeLine(overrides: Partial<PurchaseRequestLine> = {}): PurchaseRequestLine {
  return {
    id: 11,
    position: 1,
    product: null,
    description: 'Laptop',
    reason: null,
    unit_of_measure: { id: 1, name: 'Piece', symbol: 'pc' },
    quantity: '2.000',
    unit_price: '100.00',
    vat_rate: { id: 1, name: 'IVA 22%', rate: '22.00' },
    taxable_amount: '200.00',
    vat_amount: '44.00',
    total_amount: '244.00',
    status: 'pending_approval',
    approved_by: null,
    approved_at: null,
    oda_reference: null,
    abilities: { update: true, delete: true, transitions: ['approved', 'rejected'] satisfies LineStatus[], capabilities: ['approve'] },
    ...overrides,
  }
}

/** Test fixture: an open RDA with one pending line. */
export function makeRequest(overrides: Partial<PurchaseRequest> = {}): PurchaseRequest {
  return {
    id: 5,
    subject: 'Office equipment',
    requested_at: '2026-10-09',
    priority: 'medium',
    requester: { id: 3, name: 'Mario Rossi' },
    function_manager: { id: 4, name: 'Anna Verdi' },
    customer: null,
    supplier: null,
    work_order: null,
    company: { id: 1, name: 'Qualifica Srl' },
    company_site: { id: 2, name: 'Napoli' },
    operational_site: { id: 3, name: 'Sede Napoli' },
    business_function: { id: 6, name: 'IT' },
    created_by: { id: 3, name: 'Mario Rossi' },
    notes: null,
    delivery_terms: null,
    procurement_plan: null,
    technical_requirements: null,
    special_conditions: null,
    taxable_total: '200.00',
    vat_total: '44.00',
    grand_total: '244.00',
    status: 'open',
    closed_by: null,
    closed_at: null,
    close_reason: null,
    line_status_counts: { pending_approval: 1 },
    lines: [makeLine()],
    created_at: '2026-10-09T08:00:00Z',
    updated_at: '2026-10-09T08:00:00Z',
    abilities: { update: true, delete: true, close: true, notify_manager: true, view_activity: true },
    field_permissions: {},
    ...overrides,
  }
}
