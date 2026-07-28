import { useQuery } from '@tanstack/react-query'
import { fetchTableRows } from '@/features/table/api'
import type { CustomFieldType } from '@/features/custom-fields/types'

/** A minimal attribute projection for pickers (assignment editors, dynamic forms). */
export interface AttributeCatalogEntry {
  id: number
  code: string
  name: string
  type: CustomFieldType
}

/**
 * Size of ONE picker page. Capped at the generic rows endpoint's max block size
 * (MAX_LIMIT = 100): a single `/tables/attributes/rows` request rejects a block
 * larger than 100 (422), so a higher value here silently empties the picker.
 * It is a WINDOW, not the catalogue's size — anything past it is reached by
 * typing, which narrows the query server-side (see below).
 */
const ATTRIBUTE_CATALOG_LIMIT = 100

/**
 * Loads attributes for assignment pickers (e.g. the product-category
 * attribute-assignment editor) through the already-frozen generic table rows
 * endpoint (`POST /tables/attributes/rows`) instead of a dedicated
 * `for-select` one — out of scope for spec 0017.
 *
 * SEARCH-DRIVEN, not a full download: the catalogue outgrew the 100-row window
 * (the q-crm legacy import alone contributes over a hundred rows), and a
 * client-side filter over a truncated first page cannot reach what the window
 * left out. `search` is the endpoint's global quick-search (spec 0009), a
 * bound OR-LIKE over the domain's searchable columns (`code`, `name`), so the
 * caller must render the options unfiltered (`SearchableSelect filter={false}`)
 * — they are already narrowed server-side. An empty term = the first page by
 * name, which is what an untouched picker shows.
 *
 * The rows an assignment editor ALREADY holds must never depend on this
 * window: their labels travel with the assignment itself, this hook only feeds
 * the "add" picker.
 */
export function useAttributeCatalog(search = '') {
  const term = search.trim()

  return useQuery({
    queryKey: ['attributes', 'catalog', term],
    queryFn: async (): Promise<AttributeCatalogEntry[]> => {
      const response = await fetchTableRows('attributes', {
        startRow: 0,
        endRow: ATTRIBUTE_CATALOG_LIMIT,
        sortModel: [{ colId: 'name', sort: 'asc' }],
        filterModel: {},
        ...(term === '' ? {} : { search: term }),
      })
      return response.items.map((item) => ({
        id: item.id,
        code: String(item.code),
        name: String(item.name),
        type: item.type as CustomFieldType,
      }))
    },
    placeholderData: (previous) => previous,
  })
}
