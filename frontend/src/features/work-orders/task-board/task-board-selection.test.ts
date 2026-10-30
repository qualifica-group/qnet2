import { describe, expect, it } from 'vitest'
import { selectAllState, setIdsSelection } from '@/features/work-orders/task-board/task-board-selection'

describe('selectAllState', () => {
  it('is unchecked with no selected id, checked with all, indeterminate in between', () => {
    expect(selectAllState([1, 2], new Set())).toBe(false)
    expect(selectAllState([1, 2], new Set([1, 2, 9]))).toBe(true)
    expect(selectAllState([1, 2], new Set([2]))).toBe('indeterminate')
  })

  it('ignores selected ids outside the group', () => {
    expect(selectAllState([1, 2], new Set([9]))).toBe(false)
  })
})

describe('setIdsSelection', () => {
  it('adds the ids keeping the other selected ones', () => {
    expect([...setIdsSelection(new Set([9]), [1, 2], true)].sort()).toEqual([1, 2, 9])
  })

  it('removes only the given ids', () => {
    expect([...setIdsSelection(new Set([1, 2, 9]), [1, 2], false)]).toEqual([9])
  })

  it('never mutates the current set', () => {
    const current = new Set([1])
    setIdsSelection(current, [2], true)
    expect([...current]).toEqual([1])
  })
})
