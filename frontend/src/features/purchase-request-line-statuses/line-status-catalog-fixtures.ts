import type { QueryClient } from '@tanstack/react-query'
import {
  PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY,
  type LineStatusPresentation,
} from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'

/** The six system rows with the seeded defaults (English names), in the seeded order. Test helper. */
export const LINE_STATUS_CATALOG_FIXTURE: readonly LineStatusPresentation[] = [
  { id: 1, systemKey: 'pending_approval', name: 'To approve', color: 'amber', icon: 'clock', sortOrder: 1 },
  { id: 2, systemKey: 'approved', name: 'Approved', color: 'green', icon: 'check', sortOrder: 2 },
  { id: 3, systemKey: 'ordered', name: 'Ordered', color: 'blue', icon: 'shopping-cart', sortOrder: 3 },
  { id: 4, systemKey: 'received', name: 'Received', color: 'teal', icon: 'package', sortOrder: 4 },
  { id: 5, systemKey: 'rejected', name: 'Rejected', color: 'red', icon: 'x', sortOrder: 5 },
  { id: 6, systemKey: 'on_hold', name: 'On hold', color: 'gray', icon: 'pause-circle', sortOrder: 6 },
]

/** Pre-fills the shared catalog query so components draw statuses without a request. Test helper. */
export function seedLineStatusCatalog(
  client: QueryClient,
  statuses: readonly LineStatusPresentation[] = LINE_STATUS_CATALOG_FIXTURE,
): QueryClient {
  client.setQueryData(PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY, [...statuses])
  return client
}
