import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'

/** Resource segment of the catalog's for-select endpoint. */
export const PURCHASE_REQUEST_LINE_STATUSES_RESOURCE = 'purchase-request-line-statuses'

/** Query key of the whole catalog: invalidated when a status is renamed, recolored or reordered. */
export const PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY = [PURCHASE_REQUEST_LINE_STATUSES_RESOURCE, 'catalog'] as const

/** The catalog holds the 6 enum values; the server caps a for-select page at 100. */
const CATALOG_LIMIT = 100
const CATALOG_STALE_MS = 5 * 60 * 1000

/** Presentation of one line status, keyed by the enum value the APIs expose as `status`. */
export interface LineStatusPresentation {
  id: number
  systemKey: string
  name: string
  color: string
  icon: string | null
  sortOrder: number
}

interface CatalogMeta {
  system_key?: unknown
  color?: unknown
  icon?: unknown
  sort_order?: unknown
}

const EMPTY_STATUSES: readonly LineStatusPresentation[] = []
const EMPTY_BY_KEY: ReadonlyMap<string, LineStatusPresentation> = new Map()

function toPresentation(item: ForSelectItem & { meta?: CatalogMeta }): LineStatusPresentation | null {
  const meta = item.meta
  if (typeof meta?.system_key !== 'string') {
    return null
  }
  return {
    id: item.id,
    systemKey: meta.system_key,
    name: item.label,
    color: typeof meta.color === 'string' ? meta.color : '',
    icon: typeof meta.icon === 'string' ? meta.icon : null,
    sortOrder: typeof meta.sort_order === 'number' ? meta.sort_order : 0,
  }
}

async function fetchCatalog(): Promise<LineStatusPresentation[]> {
  const response = await fetchForSelect(PURCHASE_REQUEST_LINE_STATUSES_RESOURCE, { limit: CATALOG_LIMIT, includeTotal: false })
  return response.items
    .map(toPresentation)
    .filter((status): status is LineStatusPresentation => status !== null)
    .sort((a, b) => a.sortOrder - b.sortOrder)
}

/**
 * The line status catalog (name, color, icon, order), loaded once and shared
 * by every surface that draws a line status. Logic keeps using the raw enum
 * value; this is presentation only (spec 0248 D-4).
 */
export function usePurchaseRequestLineStatusCatalog() {
  const query = useQuery({
    queryKey: PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY,
    queryFn: fetchCatalog,
    staleTime: CATALOG_STALE_MS,
  })
  const statuses = query.data ?? EMPTY_STATUSES
  const byKey = useMemo(
    () => (statuses.length === 0 ? EMPTY_BY_KEY : new Map(statuses.map((status) => [status.systemKey, status]))),
    [statuses],
  )

  return { statuses, byKey, isLoading: query.isPending }
}
