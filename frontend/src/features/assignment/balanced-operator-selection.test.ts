import { describe, expect, it } from 'vitest'
import {
  buildOperatorsBySite,
  groupSelectionState,
  hasAnySelection,
  isOperatorSelected,
  selectedOperatorIds,
  toggleGroupSelection,
  toggleOperatorSelection,
  type BalancedExclusions,
} from '@/features/assignment/balanced-operator-selection'
import type { AssignmentScopeBalancedGroup } from '@/features/assignment/types'

/** Spec 0168 D-1/D-2: pure selection logic behind the "Smistamento equo" picker. */

const NAPOLI: AssignmentScopeBalancedGroup = {
  operational_site_id: 1,
  operational_site_label: 'Napoli',
  record_count: 3,
  operators: [
    { id: 11, label: 'Anna', avatar_url: null, load: 0 },
    { id: 12, label: 'Bruno', avatar_url: null, load: 0 },
  ],
}
const ROMA: AssignmentScopeBalancedGroup = {
  operational_site_id: 2,
  operational_site_label: 'Roma',
  record_count: 1,
  operators: [{ id: 12, label: 'Bruno', avatar_url: null, load: 0 }],
}

describe('balanced-operator-selection', () => {
  it('D-3: an empty exclusions map means everyone is selected', () => {
    const exclusions: BalancedExclusions = {}
    expect(isOperatorSelected(exclusions, 1, 11)).toBe(true)
    expect(groupSelectionState(NAPOLI, exclusions)).toBe(true)
    expect(selectedOperatorIds(NAPOLI, exclusions)).toEqual([11, 12])
  })

  it('AC-012: toggling one operator off makes the group indeterminate, toggling the last makes it false', () => {
    let exclusions: BalancedExclusions = {}
    exclusions = toggleOperatorSelection(exclusions, 1, 11, false)
    expect(groupSelectionState(NAPOLI, exclusions)).toBe('indeterminate')
    expect(isOperatorSelected(exclusions, 1, 11)).toBe(false)
    expect(isOperatorSelected(exclusions, 1, 12)).toBe(true)

    exclusions = toggleOperatorSelection(exclusions, 1, 12, false)
    expect(groupSelectionState(NAPOLI, exclusions)).toBe(false)

    exclusions = toggleOperatorSelection(exclusions, 1, 11, true)
    expect(groupSelectionState(NAPOLI, exclusions)).toBe('indeterminate')
  })

  it('D-2: an operator excluded in one group stays selected in another (same id, different Sede)', () => {
    const exclusions = toggleOperatorSelection({}, 1, 12, false)
    expect(isOperatorSelected(exclusions, 1, 12)).toBe(false)
    expect(isOperatorSelected(exclusions, 2, 12)).toBe(true)
    expect(groupSelectionState(ROMA, exclusions)).toBe(true)
  })

  it('AC-012: the group header toggles every one of its own operators, leaving other groups untouched', () => {
    let exclusions = toggleOperatorSelection({}, 2, 12, false)
    exclusions = toggleGroupSelection(exclusions, NAPOLI, false)
    expect(selectedOperatorIds(NAPOLI, exclusions)).toEqual([])
    expect(isOperatorSelected(exclusions, 2, 12)).toBe(false)

    exclusions = toggleGroupSelection(exclusions, NAPOLI, true)
    expect(selectedOperatorIds(NAPOLI, exclusions)).toEqual([11, 12])
    // Roma's own exclusion from before is untouched by Napoli's select-all.
    expect(isOperatorSelected(exclusions, 2, 12)).toBe(false)
  })

  it('AC-013: hasAnySelection is true with at least one operator selected anywhere, false with none', () => {
    let exclusions = toggleGroupSelection({}, NAPOLI, false)
    expect(hasAnySelection([NAPOLI, ROMA], exclusions)).toBe(true)

    exclusions = toggleGroupSelection(exclusions, ROMA, false)
    expect(hasAnySelection([NAPOLI, ROMA], exclusions)).toBe(false)
  })

  it('AC-014: builds one entry per group with a selection, omitting a group left at zero', () => {
    const exclusions = toggleGroupSelection({}, ROMA, false)
    expect(buildOperatorsBySite([NAPOLI, ROMA], exclusions)).toEqual([
      { operational_site_id: 1, operator_ids: [11, 12] },
    ])
  })

  it('AC-014: an unmodified selection sends every operator of every group', () => {
    expect(buildOperatorsBySite([NAPOLI, ROMA], {})).toEqual([
      { operational_site_id: 1, operator_ids: [11, 12] },
      { operational_site_id: 2, operator_ids: [12] },
    ])
  })
})
