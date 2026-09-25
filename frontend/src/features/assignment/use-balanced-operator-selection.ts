import { useState } from 'react'
import {
  buildOperatorsBySite,
  groupSelectionState,
  hasAnySelection,
  isOperatorSelected,
  toggleGroupSelection,
  toggleOperatorSelection,
  type BalancedExclusions,
  type TriState,
} from '@/features/assignment/balanced-operator-selection'
import type {
  AssignmentScopeBalancedGroup,
  BalancedOperatorsBySiteEntry,
} from '@/features/assignment/types'

/** Stable default: an inline `[]` would be a new reference on every render. */
const NO_GROUPS: AssignmentScopeBalancedGroup[] = []

export interface UseBalancedOperatorSelectionResult {
  isOperatorSelected: (siteId: number, operatorId: number) => boolean
  groupState: (group: AssignmentScopeBalancedGroup) => TriState
  toggleOperator: (siteId: number, operatorId: number, checked: boolean) => void
  toggleGroup: (group: AssignmentScopeBalancedGroup, checked: boolean) => void
  /** AC-013: Confirm needs at least one operator selected across every group. */
  hasSelection: boolean
  /** `operators_by_site` ready to send (spec 0168), one entry per group with a selection. */
  operatorsBySite: BalancedOperatorsBySiteEntry[]
}

/**
 * Owns the "Smistamento equo" picker's selection state (spec 0168 D-2/D-3):
 * per-group exclusions, starting empty (everyone selected) on every mount —
 * which IS the reset on every dialog open, since `AssignOperatorsDialogBody`
 * already remounts fresh each time (see its own doc comment). Groups arrive
 * asynchronously from the scope query; `undefined` (not yet resolved) is
 * treated as "no groups yet" rather than seeded into state, so no effect is
 * needed to sync it.
 */
export function useBalancedOperatorSelection(
  groups: AssignmentScopeBalancedGroup[] | undefined,
): UseBalancedOperatorSelectionResult {
  const [exclusions, setExclusions] = useState<BalancedExclusions>({})
  const safeGroups = groups ?? NO_GROUPS

  return {
    isOperatorSelected: (siteId, operatorId) => isOperatorSelected(exclusions, siteId, operatorId),
    groupState: (group) => groupSelectionState(group, exclusions),
    toggleOperator: (siteId, operatorId, checked) =>
      setExclusions((current) => toggleOperatorSelection(current, siteId, operatorId, checked)),
    toggleGroup: (group, checked) =>
      setExclusions((current) => toggleGroupSelection(current, group, checked)),
    hasSelection: hasAnySelection(safeGroups, exclusions),
    operatorsBySite: buildOperatorsBySite(safeGroups, exclusions),
  }
}
