import type {
  AssignmentScopeBalancedGroup,
  BalancedOperatorsBySiteEntry,
} from '@/features/assignment/types'

/**
 * Selection state of the "Smistamento equo" picker (spec 0168 D-2/D-3): every
 * operator starts selected, so the state only needs to remember who was
 * DESELECTED — keyed by Sede id, each a set of excluded operator ids. A Sede
 * with no entry (or an empty set) means "everyone selected", which is both
 * the initial state (D-3: a fresh `{}` on every dialog open) and the common
 * case, so nothing needs seeding once the groups arrive asynchronously.
 */
export type BalancedExclusions = Record<number, Set<number>>

/** A checkbox's tri-state value (mirrors the permission explorer's own). */
export type TriState = boolean | 'indeterminate'

function excludedSet(exclusions: BalancedExclusions, siteId: number): Set<number> {
  return exclusions[siteId] ?? new Set<number>()
}

export function isOperatorSelected(
  exclusions: BalancedExclusions,
  siteId: number,
  operatorId: number,
): boolean {
  return !excludedSet(exclusions, siteId).has(operatorId)
}

/** Ids still selected in one group, in the group's own operator order. */
export function selectedOperatorIds(
  group: AssignmentScopeBalancedGroup,
  exclusions: BalancedExclusions,
): number[] {
  const excluded = excludedSet(exclusions, group.operational_site_id)
  return group.operators.filter((operator) => !excluded.has(operator.id)).map((operator) => operator.id)
}

/** Tri-state of a group's header checkbox: `checked` only when none of its operators are excluded. */
export function groupSelectionState(
  group: AssignmentScopeBalancedGroup,
  exclusions: BalancedExclusions,
): TriState {
  const excluded = excludedSet(exclusions, group.operational_site_id)
  if (excluded.size === 0) {
    return true
  }
  return excluded.size >= group.operators.length ? false : 'indeterminate'
}

/** Toggles one operator within its own group, leaving every other group untouched (AC-012). */
export function toggleOperatorSelection(
  exclusions: BalancedExclusions,
  siteId: number,
  operatorId: number,
  checked: boolean,
): BalancedExclusions {
  const next = { ...exclusions }
  const set = new Set(excludedSet(exclusions, siteId))
  if (checked) {
    set.delete(operatorId)
  } else {
    set.add(operatorId)
  }
  if (set.size === 0) {
    delete next[siteId]
  } else {
    next[siteId] = set
  }
  return next
}

/** Toggles every operator of one group at once (the group header's tri-state checkbox, AC-012). */
export function toggleGroupSelection(
  exclusions: BalancedExclusions,
  group: AssignmentScopeBalancedGroup,
  checked: boolean,
): BalancedExclusions {
  const next = { ...exclusions }
  if (checked) {
    delete next[group.operational_site_id]
  } else {
    next[group.operational_site_id] = new Set(group.operators.map((operator) => operator.id))
  }
  return next
}

/** Whether at least one operator is still selected in at least one group (AC-013 Confirm gating). */
export function hasAnySelection(
  groups: AssignmentScopeBalancedGroup[],
  exclusions: BalancedExclusions,
): boolean {
  return groups.some((group) => selectedOperatorIds(group, exclusions).length > 0)
}

/**
 * Builds `operators_by_site` (spec 0168 `data_contract`): one entry per group
 * with at least one operator still selected, holding only the selected ids —
 * a group left at zero is simply omitted (its records are reported `skipped`
 * server-side).
 */
export function buildOperatorsBySite(
  groups: AssignmentScopeBalancedGroup[],
  exclusions: BalancedExclusions,
): BalancedOperatorsBySiteEntry[] {
  return groups
    .map((group) => ({
      operational_site_id: group.operational_site_id,
      operator_ids: selectedOperatorIds(group, exclusions),
    }))
    .filter((entry) => entry.operator_ids.length > 0)
}
