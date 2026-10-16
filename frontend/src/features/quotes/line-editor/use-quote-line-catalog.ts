import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import type { ProductUsage } from '@/features/products/types'
import type { QuoteCatalogProduct, QuoteCatalogScope } from './types'

/** Starts the next page a little before the sentinel is actually visible. */
const SENTINEL_ROOT_MARGIN = '0px 0px 80px 0px'

interface UseQuoteLineCatalogArgs {
  scope: QuoteCatalogScope
  /** Spec 0142: SALE products on revenue rows, COST products on cost rows. */
  usage: ProductUsage
  /** `false` keeps the catalogue silent (no category to scope by yet). */
  enabled: boolean
  /** How many more rows the card may take: caps the multi-selection (spec 0077 / MAX_LINES_PER_TAB). */
  remainingSlots: number
  /** Products already on a row where the channel forbids repeats: never selectable. */
  addedProductIds: Set<number>
}

/** The `category_ids` the list sends: the chip, the whole scope, or none (whole catalogue). */
function categoryParam(scope: QuoteCatalogScope, chipCategoryId: number | null): number[] | null {
  if (scope.kind === 'all' || scope.fullCatalog?.active) {
    return null
  }
  return chipCategoryId === null ? scope.categoryIds : [chipCategoryId]
}

/**
 * Data and selection state of the catalogue pane (spec 0268, 0269): the
 * debounced search, the active category chip, the infinite
 * `products/for-select` list, and the multi-selection waiting for
 * "Aggiungi N selezionati".
 */
export function useQuoteLineCatalog({ scope, usage, enabled, remainingSlots, addedProductIds }: UseQuoteLineCatalogArgs) {
  const [search, setSearch] = useState('')
  const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null)
  const [selected, setSelected] = useState<Map<number, QuoteCatalogProduct>>(() => new Map())
  const debouncedSearch = useDebouncedValue(search.trim())

  // A chip whose category left the scope falls back to "Tutte".
  const chipCategoryId =
    scope.kind === 'categories' && activeCategoryId !== null && scope.categoryIds.includes(activeCategoryId)
      ? activeCategoryId
      : null

  const params = useMemo((): Record<string, string | number[]> => {
    const categoryIds = categoryParam(scope, chipCategoryId)
    return categoryIds === null ? { usage } : { usage, category_ids: categoryIds }
  }, [scope, chipCategoryId, usage])

  const query = useForSelect({ resource: PRODUCTS_FOR_SELECT_RESOURCE, search: debouncedSearch, params, enabled })
  const items = useMemo(
    () => flattenForSelectPages(query.data?.pages) as QuoteCatalogProduct[],
    [query.data?.pages],
  )

  // A selected product that reached a row meanwhile (autofill) no longer waits to be added.
  const selectedItems = useMemo(
    () => [...selected.values()].filter((item) => !addedProductIds.has(item.id)),
    [selected, addedProductIds],
  )

  const toggle = (item: QuoteCatalogProduct) => {
    setSelected((previous) => {
      const next = new Map(previous)
      if (next.has(item.id)) {
        next.delete(item.id)
      } else if (next.size < remainingSlots) {
        next.set(item.id, item)
      }
      return next
    })
  }

  return {
    search,
    setSearch,
    activeCategoryId: chipCategoryId,
    setActiveCategoryId,
    items,
    isPending: query.isPending && query.fetchStatus !== 'idle',
    isError: query.isError,
    isFetchingNextPage: query.isFetchingNextPage,
    hasNextPage: query.hasNextPage,
    fetchNextPage: query.fetchNextPage,
    retry: () => void query.refetch(),
    isSelected: (id: number) => selected.has(id),
    selectedItems,
    canSelectMore: selectedItems.length < remainingSlots,
    toggle,
    /** Emptied only once the rows landed: a failed add keeps the selection to retry (spec 0269 D-6). */
    clearSelection: () => setSelected(new Map()),
  }
}

export interface PagingState {
  hasNextPage: boolean
  isFetchingNextPage: boolean
  fetchNextPage: () => Promise<unknown>
}

/**
 * Infinite scroll on the list's own scroller: the same sentinel +
 * IntersectionObserver `AsyncPaginatedSelect` uses, with the paging state read
 * through a ref so the observer is attached once per sentinel mount. Both
 * nodes come in through callback refs: no ref object leaves this hook, and
 * the component calls it directly (a ref-touching callback returned inside
 * the catalogue's state object would taint that whole object for the React
 * Compiler).
 */
export function useNextPageSentinel({ hasNextPage, isFetchingNextPage, fetchNextPage }: PagingState) {
  const [scrollRoot, setScrollRoot] = useState<HTMLDivElement | null>(null)
  const observerRef = useRef<IntersectionObserver | null>(null)
  const pagingRef = useRef<PagingState>({ hasNextPage, isFetchingNextPage, fetchNextPage })

  useEffect(() => {
    pagingRef.current = { hasNextPage, isFetchingNextPage, fetchNextPage }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage])

  const setSentinel = useCallback(
    (node: HTMLDivElement | null) => {
      observerRef.current?.disconnect()
      observerRef.current = null
      if (!node || typeof IntersectionObserver === 'undefined') {
        return
      }
      const observer = new IntersectionObserver(
        (entries) => {
          const paging = pagingRef.current
          if (entries[0]?.isIntersecting && paging.hasNextPage && !paging.isFetchingNextPage) {
            void paging.fetchNextPage()
          }
        },
        { root: scrollRoot, rootMargin: SENTINEL_ROOT_MARGIN },
      )
      observer.observe(node)
      observerRef.current = observer
    },
    [scrollRoot],
  )

  useEffect(() => () => observerRef.current?.disconnect(), [])

  return { setScrollRoot, setSentinel }
}
