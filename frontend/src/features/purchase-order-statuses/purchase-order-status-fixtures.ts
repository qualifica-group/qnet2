import type { PurchaseOrderStatusCatalogEntry } from '@/features/purchase-order-statuses/for-select-api'

/** Test fixture: the seeded catalog (spec 0248 D-1) with English names, so suites asserting copy stay readable. */
export const PURCHASE_ORDER_STATUS_CATALOG_FIXTURE: PurchaseOrderStatusCatalogEntry[] = [
  { id: 1, systemKey: 'draft', name: 'Draft', color: 'slate', icon: 'pen-line' },
  { id: 2, systemKey: 'in_validation', name: 'In validation', color: 'amber', icon: 'clock' },
  { id: 3, systemKey: 'approved', name: 'Approved', color: 'green', icon: 'check-circle-2' },
  { id: 4, systemKey: 'sent_to_supplier', name: 'Sent to supplier', color: 'blue', icon: 'send' },
  { id: 5, systemKey: 'partially_fulfilled', name: 'Partially fulfilled', color: 'teal', icon: 'package' },
  { id: 6, systemKey: 'fulfilled', name: 'Fulfilled', color: 'emerald', icon: 'badge-check' },
  { id: 7, systemKey: 'cancelled', name: 'Cancelled', color: 'red', icon: 'flag' },
]

/** The same catalog in the `for-select` wire shape, for suites that stub `fetchForSelect`. */
export const PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS = PURCHASE_ORDER_STATUS_CATALOG_FIXTURE.map((entry, index) => ({
  id: entry.id,
  label: entry.name,
  meta: { system_key: entry.systemKey, color: entry.color, icon: entry.icon, sort_order: index },
}))
