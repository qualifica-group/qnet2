import { useCallback, useContext, useState } from 'react'
import { AuthContext } from '@/features/auth/auth-context'
import {
  readTableLocalFilters,
  tableLocalFiltersKey,
  writeTableLocalFilters,
  type TableLocalFilters,
} from '@/features/table/table-local-filters-storage'

interface UseTableLocalFiltersArgs {
  domain: string
  productCategoryId?: number
  opportunityId?: number
  quoteId?: number
  registryId?: number
}

export interface UseTableLocalFiltersResult {
  /** The filters stored for this table, read once at mount (the table remounts on a scope change). */
  initial: TableLocalFilters
  /** Stores the current filters; a no-op when no user is signed in. */
  persist: (filters: TableLocalFilters) => void
}

/**
 * Browser persistence of a table's quick search and active custom filter, so
 * both survive a reload next to the server-persisted column/advanced filters.
 * Reads the auth context directly (not `useAuth`) so a table rendered without
 * an `AuthProvider` simply skips persistence instead of throwing.
 */
export function useTableLocalFilters({
  domain,
  productCategoryId,
  opportunityId,
  quoteId,
  registryId,
}: UseTableLocalFiltersArgs): UseTableLocalFiltersResult {
  const userId = useContext(AuthContext)?.user?.id
  const storageKey =
    userId === undefined
      ? null
      : tableLocalFiltersKey({ userId, domain, productCategoryId, opportunityId, quoteId, registryId })

  const [initial] = useState(() => readTableLocalFilters(storageKey))

  const persist = useCallback(
    (filters: TableLocalFilters) => writeTableLocalFilters(storageKey, filters),
    [storageKey],
  )

  return { initial, persist }
}
