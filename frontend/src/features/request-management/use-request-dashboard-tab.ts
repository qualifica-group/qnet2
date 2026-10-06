import { useCallback, useState } from 'react'
import { useRequestModule } from '@/features/request-management/request-module'

/** Tab value of the overview, kept out of the category-key namespace on purpose. */
export const OVERVIEW_TAB = '__overview__'

function storageKey(moduleKey: string): string {
  return `${moduleKey}.dashboard-tab`
}

function readStoredTab(moduleKey: string): string {
  try {
    return window.localStorage.getItem(storageKey(moduleKey)) ?? OVERVIEW_TAB
  } catch {
    return OVERVIEW_TAB
  }
}

/**
 * The statistics tab the user last opened, remembered per module (spec 0192
 * D-1). The STORED choice survives a category disappearing from the data —
 * the shown tab falls back to the overview while `categoryKeys` lacks it, and
 * returns once the category is back (e.g. after the filters change again).
 */
export function useRequestDashboardTab(categoryKeys: string[]): {
  tab: string
  setTab: (tab: string) => void
} {
  const { key: moduleKey } = useRequestModule()
  const [stored, setStored] = useState(() => readStoredTab(moduleKey))

  const setTab = useCallback(
    (tab: string) => {
      setStored(tab)
      try {
        window.localStorage.setItem(storageKey(moduleKey), tab)
      } catch {
        // Storage can be unavailable: the tab still switches for this session.
      }
    },
    [moduleKey],
  )

  return { tab: categoryKeys.includes(stored) ? stored : OVERVIEW_TAB, setTab }
}
