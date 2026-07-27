import { useMutation } from '@tanstack/react-query'
import { bulkMoveProductCategories } from '@/features/product-categories/api'
import type {
  BulkMoveCategoriesPayload,
  BulkMoveCategoriesResult,
} from '@/features/product-categories/types'

interface UseBulkMoveCategoriesOptions {
  /** Ran after a successful move; the caller drives its own refresh/toast. */
  onSuccess?: (result: BulkMoveCategoriesResult) => void
}

/**
 * Thin `useMutation` wrapper over `bulkMoveProductCategories` (spec 0063).
 * Deliberately generic: it neither invalidates a query nor toasts — the
 * consumer owns its post-success refresh, since the grid, the stats panel and
 * the cached category tree are all its to invalidate.
 */
export function useBulkMoveCategories({ onSuccess }: UseBulkMoveCategoriesOptions = {}) {
  return useMutation({
    mutationFn: (payload: BulkMoveCategoriesPayload) => bulkMoveProductCategories(payload),
    onSuccess,
  })
}
