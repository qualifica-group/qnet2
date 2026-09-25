import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useBalancedOperatorSelection } from '@/features/assignment/use-balanced-operator-selection'
import type { AssignmentScopeBalancedGroup } from '@/features/assignment/types'

const NAPOLI: AssignmentScopeBalancedGroup = {
  operational_site_id: 1,
  operational_site_label: 'Napoli',
  record_count: 3,
  operators: [
    { id: 11, label: 'Anna', avatar_url: null, load: 0 },
    { id: 12, label: 'Bruno', avatar_url: null, load: 0 },
  ],
}

describe('useBalancedOperatorSelection', () => {
  it('D-3: starts with everyone selected and every group fully sent', () => {
    const { result } = renderHook(() => useBalancedOperatorSelection([NAPOLI]))

    expect(result.current.hasSelection).toBe(true)
    expect(result.current.groupState(NAPOLI)).toBe(true)
    expect(result.current.operatorsBySite).toEqual([{ operational_site_id: 1, operator_ids: [11, 12] }])
  })

  it('D-2/AC-012: toggling one operator updates only that operator and re-derives the group state', () => {
    const { result } = renderHook(() => useBalancedOperatorSelection([NAPOLI]))

    act(() => result.current.toggleOperator(1, 11, false))

    expect(result.current.isOperatorSelected(1, 11)).toBe(false)
    expect(result.current.isOperatorSelected(1, 12)).toBe(true)
    expect(result.current.groupState(NAPOLI)).toBe('indeterminate')
    expect(result.current.operatorsBySite).toEqual([{ operational_site_id: 1, operator_ids: [12] }])
  })

  it('AC-013: hasSelection turns false once every operator of every group is deselected', () => {
    const { result } = renderHook(() => useBalancedOperatorSelection([NAPOLI]))

    act(() => result.current.toggleGroup(NAPOLI, false))

    expect(result.current.hasSelection).toBe(false)
    expect(result.current.operatorsBySite).toEqual([])
  })

  it('treats undefined groups (scope not resolved yet) as no selection at all', () => {
    const { result } = renderHook(() => useBalancedOperatorSelection(undefined))

    expect(result.current.hasSelection).toBe(false)
    expect(result.current.operatorsBySite).toEqual([])
  })
})
