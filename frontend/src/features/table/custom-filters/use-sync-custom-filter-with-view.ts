import { useEffect } from 'react'
import type { UseCustomFilterStateResult } from '@/features/table/custom-filters/use-custom-filter-state'
import { useFilterViews } from '@/features/table/use-filter-views'
import type { FilterRule, FilterRules } from '@/features/table/types'

interface UseSyncCustomFilterWithViewArgs {
  domain: string
  active: UseCustomFilterStateResult
  refreshGrid: () => void
}

function ruleKey(rule: FilterRule): string {
  return JSON.stringify([rule.field, rule.operator, rule.value ?? null])
}

/** Same rules regardless of the JSON key order the server stores them in. */
export function sameRules(a: FilterRules, b: FilterRules): boolean {
  const serialize = (rules: FilterRules) =>
    JSON.stringify([rules.and.map(ruleKey), rules.or.map(ruleKey)])
  return serialize(a) === serialize(b)
}

/** Activating over an active custom filter: the column filters are already empty. */
function keepOtherFilters(): void {}

/**
 * Keeps an active custom filter that came from a saved view in step with
 * that view as the server stores it. The browser keeps a copy of the active
 * filter per table and tab (`use-table-local-filters.ts`); without this, a
 * tab opened later restored the rules the view had when it was first applied
 * there, not the ones saved since (the "Richiami" view kept going back to the
 * day it was created). An ad-hoc filter (no `viewId`) is left alone.
 */
export function useSyncCustomFilterWithView({ domain, active, refreshGrid }: UseSyncCustomFilterWithViewArgs): void {
  const { state, activate } = active
  const viewId = state?.viewId
  const { data: views } = useFilterViews(domain, viewId !== undefined)
  const savedView = viewId === undefined ? undefined : views?.find((view) => view.id === viewId)

  // Syncs with the server copy and reloads the grid (an external system), so
  // an effect rather than a render-time adjustment.
  useEffect(() => {
    if (!state || !savedView?.rules) {
      return
    }
    if (sameRules(savedView.rules, state.rules) && savedView.name === state.name) {
      return
    }
    activate(savedView.rules, { viewId: savedView.id, name: savedView.name }, keepOtherFilters)
    refreshGrid()
  }, [state, savedView, activate, refreshGrid])
}
