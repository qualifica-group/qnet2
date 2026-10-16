import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import type { OfferCatalogProduct } from './types'

/** Spec 0142: the offer rows only ever take SALE products. */
const CATALOG_USAGE = 'SALE'

/** Starts the next page a little before the sentinel is actually visible. */
const SENTINEL_ROOT_MARGIN = '0px 0px 80px 0px'

interface UseOfferLinesCatalogArgs {
  /** The categories of the classification: the chips, and the scope of "Tutte" (D-4). */
  categoryIds: number[]
  /** `false` keeps the catalogue silent: no category yet, or a read-only field (AC-011). */
  enabled: boolean
  /** How many more rows the card may take: caps the multi-selection (spec 0077 / MAX_LINES_PER_TAB). */
  remainingSlots: number
  /** Products already on a row: never selectable (D-3). */
  addedProductIds: Set<number>
}

/**
 * Data and selection state of the catalogue pane (spec 0268): the debounced
 * search, the active category chip, the infinite `products/for-select` list
 * scoped to the classification, and the multi-selection waiting for
 * "Aggiungi N selezionati".
 */
export function useOfferLinesCatalog({ categoryIds, enabled, remainingSlots, addedProductIds }: UseOfferLinesCatalogArgs) {
  const [search, setSearch] = useState('')
  const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null)
  const [selected, setSelected] = useState<Map<number, OfferCatalogProduct>>(() => new Map())
  const debouncedSearch = useDebouncedValue(search.trim())

  // A chip whose category left the classification falls back to "Tutte".
  const effectiveCategoryId =
    activeCategoryId !== null && categoryIds.includes(activeCategoryId) ? activeCategoryId : null

  const params = useMemo(
    () => ({
      usage: CATALOG_USAGE,
      category_ids: effectiveCategoryId === null ? categoryIds : [effectiveCategoryId],
    }),
    [categoryIds, effectiveCategoryId],
  )

  const query = useForSelect({
    resource: PRODUCTS_FOR_SELECT_RESOURCE,
    search: debouncedSearch,
    params,
    enabled: enabled && categoryIds.length > 0,
  })
  const items = useMemo(
    () => flattenForSelectPages(query.data?.pages) as OfferCatalogProduct[],
    [query.data?.pages],
  )

  // A selected product that reached a row meanwhile (autofill) no longer waits to be added.
  const selectedItems = useMemo(
    () => [...selected.values()].filter((item) => !addedProductIds.has(item.id)),
    [selected, addedProductIds],
  )

  const toggle = (item: OfferCatalogProduct) => {
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

  /** Hands the selection over (to be added as rows) and empties it. */
  const takeSelection = (): OfferCatalogProduct[] => {
    setSelected(new Map())
    return selectedItems
  }

  return {
    search,
    setSearch,
    activeCategoryId: effectiveCategoryId,
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
    takeSelection,
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
