import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { GridApi } from 'ag-grid-community'

/** Debounce window for firing a server-side reload after the user stops typing. */
const SEARCH_DEBOUNCE_MS = 350

/** Platform-aware label for the search focus shortcut (⌘K on mac, Ctrl K else). */
function searchShortcutLabel(): string {
  const platform =
    typeof navigator !== 'undefined' ? (navigator.platform ?? '') : ''
  return /Mac|iPhone|iPad|iPod/.test(platform) ? '⌘K' : 'Ctrl K'
}

interface UseTableToolbarStateArgs {
  /** The grid API once ready (null while loading); needed to purge-reload. */
  gridApi: GridApi | null
  /** Whether the domain exposes a global quick-search (drives the ⌘K binding). */
  searchEnabled: boolean
}

export interface TableToolbarState {
  /** Fullscreen state + toggle (owns scroll-lock and Escape-to-exit). */
  fullscreen: boolean
  toggleFullscreen: () => void
  /** Live total row count reported by the grid, or null before first load. */
  rowCount: number | null
  setRowCount: (count: number) => void
  /** Controlled search input value + setter (immediate, pre-debounce). */
  searchInput: string
  setSearchInput: (value: string) => void
  /** Ref bound to the search field so the ⌘K shortcut can focus it. */
  searchInputRef: React.RefObject<HTMLInputElement | null>
  /** Localized shortcut hint shown inside the search field. */
  searchShortcut: string
  /** Reads the applied (debounced, trimmed) term — passed to the datasource. */
  getSearchTerm: () => string
  /** Whether the advanced-filters panel (spec 0032) is shown/hidden. */
  advancedFiltersOpen: boolean
  toggleAdvancedFilters: () => void
}

/**
 * Client-only state for the unified table toolbar (spec 0009): the global
 * quick-search (debounced, with a ⌘K focus shortcut), the live row counter, and
 * fullscreen (with background scroll-lock and Escape-to-exit). Extracted from
 * TableView so the component stays a thin orchestrator (engineering.md §6) and
 * this behavior is unit-nameable.
 *
 * The applied search term lives in a ref (not state) so the SSRM datasource can
 * read it lazily at request time without the datasource being rebuilt on every
 * keystroke; typing only debounces a `refreshServerSide({ purge: true })`.
 */
export function useTableToolbarState({
  gridApi,
  searchEnabled,
}: UseTableToolbarStateArgs): TableToolbarState {
  const [fullscreen, setFullscreen] = useState(false)
  const [rowCount, setRowCount] = useState<number | null>(null)
  const [searchInput, setSearchInput] = useState('')
  const [advancedFiltersOpen, setAdvancedFiltersOpen] = useState(false)

  const searchTermRef = useRef('')
  const searchInputRef = useRef<HTMLInputElement>(null)

  const searchShortcut = useMemo(() => searchShortcutLabel(), [])

  // Debounce the global search: after the user stops typing, apply the trimmed
  // term and purge-reload the grid once. Guards the initial empty→empty case so
  // mount does not trigger a redundant reload.
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  useEffect(() => {
    const next = searchInput.trim()
    if (next === searchTermRef.current) {
      return
    }
    if (searchDebounceRef.current) {
      clearTimeout(searchDebounceRef.current)
    }
    searchDebounceRef.current = setTimeout(() => {
      searchTermRef.current = next
      gridApi?.refreshServerSide({ purge: true })
    }, SEARCH_DEBOUNCE_MS)
    return () => {
      if (searchDebounceRef.current) {
        clearTimeout(searchDebounceRef.current)
      }
    }
  }, [searchInput, gridApi])

  // ⌘K / Ctrl+K focuses the search field (only when the domain has a search).
  useEffect(() => {
    if (!searchEnabled) {
      return
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        searchInputRef.current?.focus()
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [searchEnabled])

  // While fullscreen: lock background scroll and let Escape exit.
  useEffect(() => {
    if (!fullscreen) {
      return
    }
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setFullscreen(false)
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => {
      document.body.style.overflow = previousOverflow
      window.removeEventListener('keydown', onKeyDown)
    }
  }, [fullscreen])

  const toggleFullscreen = useCallback(() => setFullscreen((value) => !value), [])
  const getSearchTerm = useCallback(() => searchTermRef.current, [])
  const toggleAdvancedFilters = useCallback(
    () => setAdvancedFiltersOpen((value) => !value),
    [],
  )

  return {
    fullscreen,
    toggleFullscreen,
    rowCount,
    setRowCount,
    searchInput,
    setSearchInput,
    searchInputRef,
    searchShortcut,
    getSearchTerm,
    advancedFiltersOpen,
    toggleAdvancedFilters,
  }
}
