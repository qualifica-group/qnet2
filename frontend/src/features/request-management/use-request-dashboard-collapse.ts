import { useCallback, useMemo, useState } from 'react'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import { useRequestModule } from '@/features/request-management/request-module'

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

function storageKey(moduleKey: string): string {
  return `${moduleKey}.dashboard-collapse`
}

function readStoredState(moduleKey: string): CollapseState {
  if (typeof window === 'undefined') {
    return {}
  }
  try {
    const stored = window.localStorage.getItem(storageKey(moduleKey))
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

function writeStoredState(moduleKey: string, state: CollapseState): void {
  try {
    window.localStorage.setItem(storageKey(moduleKey), JSON.stringify(state))
  } catch {
    // Storage can be unavailable: the toggle still works for this session.
  }
}

/** The blocks one rendered section actually has: an empty tiles/charts block renders no toggle. */
export interface DashboardCollapseTarget {
  sectionKey: string
  blocks: DashboardCollapseBlock[]
}

/**
 * Every collapsible block the loaded dashboard renders, mirroring the
 * conditions of `DashboardOverallSection`/`DashboardCategorySection`, so
 * "everything expanded" is never judged on a block the user cannot see.
 */
export function dashboardCollapseTargets(data: RequestDashboardData | undefined): DashboardCollapseTarget[] {
  if (!data) {
    return []
  }

  const overall: DashboardCollapseTarget[] =
    data.summary.length > 0 ? [{ sectionKey: OVERALL_SECTION_KEY, blocks: ['section'] }] : []
  const categories = data.categories.map((category) => ({
    sectionKey: category.key,
    blocks: [
      'section' as const,
      ...(category.summary.length > 0 ? (['tiles'] as const) : []),
      ...(category.charts.length > 0 ? (['charts'] as const) : []),
    ],
  }))

  return [...overall, ...categories]
}

export interface RequestDashboardCollapse {
  isOpen: (sectionKey: string, block: DashboardCollapseBlock) => boolean
  setOpen: (sectionKey: string, block: DashboardCollapseBlock, open: boolean) => void
  /** True when every block of every target is expanded (false for no target). */
  areAllOpen: (targets: DashboardCollapseTarget[]) => boolean
  /**
   * Expanding opens every block; collapsing folds only the sections, so a
   * section reopened by hand afterwards still shows everything inside it.
   */
  setAllOpen: (targets: DashboardCollapseTarget[], open: boolean) => void
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
  const { key: moduleKey } = useRequestModule()
  const [state, setState] = useState<CollapseState>(() => readStoredState(moduleKey))

  const isOpen = useCallback(
    (sectionKey: string, block: DashboardCollapseBlock): boolean =>
      state[entryKey(sectionKey, block)] ?? DEFAULT_OPEN[block],
    [state],
  )

  const setOpen = useCallback(
    (sectionKey: string, block: DashboardCollapseBlock, open: boolean): void => {
      setState((current) => {
        const next = { ...current, [entryKey(sectionKey, block)]: open }
        writeStoredState(moduleKey, next)

        return next
      })
    },
    [moduleKey],
  )

  const areAllOpen = useCallback(
    (targets: DashboardCollapseTarget[]): boolean =>
      targets.length > 0 &&
      targets.every((target) => target.blocks.every((block) => isOpen(target.sectionKey, block))),
    [isOpen],
  )

  const setAllOpen = useCallback(
    (targets: DashboardCollapseTarget[], open: boolean): void => {
      setState((current) => {
        const next = { ...current }
        for (const target of targets) {
          for (const block of open ? target.blocks : (['section'] as const)) {
            next[entryKey(target.sectionKey, block)] = open
          }
        }
        writeStoredState(moduleKey, next)

        return next
      })
    },
    [moduleKey],
  )

  return useMemo(() => ({ isOpen, setOpen, areAllOpen, setAllOpen }), [isOpen, setOpen, areAllOpen, setAllOpen])
}
