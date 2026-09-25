import { useCallback, useMemo, useRef, useState } from 'react'
import type { FilterRules } from '@/features/table/types'

/** The active custom filter's rules plus, when it came from a saved view, its identity. */
export interface CustomFilterState {
  rules: FilterRules
  viewId?: number
  name?: string
}

export interface UseCustomFilterStateResult {
  /** The active custom filter, or `null` when none is applied. */
  state: CustomFilterState | null
  /** Activates a custom filter (spec 0158 D-2): `mutateOthers` runs FIRST, while
   * this hook suppresses the deactivation its own side effects would otherwise
   * trigger (e.g. clearing the grid's filterModel fires `notifyExternalChange`). */
  activate: (rules: FilterRules, meta: { viewId?: number; name?: string }, mutateOthers: () => void) => void
  /** Clears the active custom filter without touching column/advanced filters. */
  deactivate: () => void
  /** Stable getter read lazily by the SSRM datasource; never rebuilds it. */
  getActive: () => FilterRules | null
  /**
   * Called when the user changes a column or advanced filter (D-2: "vince
   * l'ultimo"): deactivates the active custom filter, UNLESS the change was
   * itself caused by `activate`'s own `mutateOthers` (suppressed).
   */
  notifyExternalChange: () => void
}

/**
 * Owns the in-memory (never persisted) state of the domain's active custom
 * filter (spec 0158 "DETTAGLIO CONGELATO" FE section): activating one clears
 * the column/advanced filters via the caller-supplied `mutateOthers`, and a
 * later, independent column/advanced filter change deactivates it again.
 */
export function useCustomFilterState(): UseCustomFilterStateResult {
  const [state, setState] = useState<CustomFilterState | null>(null)
  const stateRef = useRef<CustomFilterState | null>(null)
  const suppressRef = useRef(false)

  const activate = useCallback(
    (rules: FilterRules, meta: { viewId?: number; name?: string }, mutateOthers: () => void) => {
      suppressRef.current = true
      try {
        mutateOthers()
      } finally {
        suppressRef.current = false
      }
      const next: CustomFilterState = { rules, viewId: meta.viewId, name: meta.name }
      stateRef.current = next
      setState(next)
    },
    [],
  )

  const deactivate = useCallback(() => {
    stateRef.current = null
    setState(null)
  }, [])

  const notifyExternalChange = useCallback(() => {
    if (suppressRef.current || stateRef.current === null) {
      return
    }
    deactivate()
  }, [deactivate])

  const getActive = useCallback(() => stateRef.current?.rules ?? null, [])

  return useMemo(
    () => ({ state, activate, deactivate, getActive, notifyExternalChange }),
    [state, activate, deactivate, getActive, notifyExternalChange],
  )
}
