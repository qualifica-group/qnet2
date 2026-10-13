import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  fetchPurchaseOrderStatusCatalog,
  type PurchaseOrderStatusCatalogEntry,
} from '@/features/purchase-order-statuses/for-select-api'
import { PURCHASE_ORDER_STATUS_CATALOG_KEY } from '@/features/purchase-order-statuses/api'

/** Renames are rare: the catalog stays fresh for five minutes, and edits/reorders invalidate it. */
const CATALOG_STALE_TIME_MS = 5 * 60 * 1000

const EMPTY_ENTRIES: PurchaseOrderStatusCatalogEntry[] = []

/**
 * The ODA status catalog (spec 0248 D-4): name, color and icon of every status
 * in catalog order. Documents keep carrying the raw enum value in `status`;
 * `byKey` resolves it to its presentation. Loaded once and shared by badge,
 * tabs, status dialog and merge candidates.
 */
export function usePurchaseOrderStatusCatalog() {
  const { data, isLoading } = useQuery({
    queryKey: PURCHASE_ORDER_STATUS_CATALOG_KEY,
    queryFn: fetchPurchaseOrderStatusCatalog,
    staleTime: CATALOG_STALE_TIME_MS,
  })
  const entries = data ?? EMPTY_ENTRIES
  const byKey = useMemo(() => new Map(entries.map((entry) => [entry.systemKey, entry])), [entries])

  /** Current name of a status, the raw enum value while the catalog is not there. */
  const nameOf = (status: string) => byKey.get(status)?.name ?? status

  return { entries, byKey, isLoading, nameOf }
}
