import { useCallback, useMemo, useState } from 'react'

const STORAGE_KEY = 'request-management.dashboard-collapse'

/** The three independently collapsible parts of the dashboard (user directive 2026-09-08). */
export type DashboardCollapseBlock = 'section' | 'tiles' | 'charts'

/** Section key of the overall tiles, kept out of the branch-key namespace on purpose. */
export const OVERALL_SECTION_KEY = '__overall__'

/**
 * First-open state: sections and their tiles expanded, charts folded away —
 * the panel opens on the numbers, and the (much taller) charts are opted into.
 */
const DEFAULT_OPEN: Record<DashboardCollapseBlock, boolean> = {
  section: true,
  tiles: true,
  charts: false,
}

/** Flat `${sectionKey}:${block}` -> open map: one entry per block the user actually touched. */
type CollapseState = Record<string, boolean>

function entryKey(sectionKey: string, block: DashboardCollapseBlock): string {
  return `${sectionKey}:${block}`
}

function readStoredState(): CollapseState {
  if (typeof window === 'undefined') {
    return {}
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored === null) {
      return {}
    }
    const parsed: unknown = JSON.parse(stored)
    if (typeof parsed !== 'object' || parsed === null) {
      return {}
    }

    // Anything hand-edited or written by an older shape is dropped entry by
    // entry: a bad value must never decide whether a section renders.
    return Object.fromEntries(
      Object.entries(parsed as Record<string, unknown>).filter(([, open]) => typeof open === 'boolean'),
    ) as CollapseState
  } catch {
    return {}
  }
}

export interface RequestDashboardCollapse {
  isOpen: (sectionKey: string, block: DashboardCollapseBlock) => boolean
  setOpen: (sectionKey: string, block: DashboardCollapseBlock, open: boolean) => void
}

/**
 * Open/closed state of every dashboard section and of the two blocks inside
 * it, persisted across sessions in ONE localStorage entry (user directive
 * 2026-09-08: "tutto questo cachato"). Same convention as `useStatsPanel`: an
 * unavailable storage (private mode, quota) degrades to a session-only toggle
 * instead of crashing, and an untouched block falls back to `DEFAULT_OPEN`
 * rather than being written out eagerly.
 */
export function useRequestDashboardCollapse(): RequestDashboardCollapse {
  const [state, setState] = useState<CollapseState>(readStoredState)

  const isOpen = useCallback(
    (sectionKey: string, block: DashboardCollapseBlock): boolean =>
      state[entryKey(sectionKey, block)] ?? DEFAULT_OPEN[block],
    [state],
  )

  const setOpen = useCallback((sectionKey: string, block: DashboardCollapseBlock, open: boolean): void => {
    setState((current) => {
      const next = { ...current, [entryKey(sectionKey, block)]: open }
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
      } catch {
        // Storage can be unavailable: the toggle still works for this session.
      }

      return next
    })
  }, [])

  return useMemo(() => ({ isOpen, setOpen }), [isOpen, setOpen])
}
