import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import i18n from '@/i18n'
import { useRecordMergeBulkAction } from '@/features/record-merge/use-record-merge-bulk-action'
import type { TableSelection } from '@/features/table/use-bulk-actions-slot'

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({ useAbilities: () => ({ can: (permission: string) => canMock(permission) }) }))

const selectionOf = (count: number): TableSelection => ({
  ids: Array.from({ length: count }, (_, index) => index + 1),
  rows: [],
})

const tableRef = { current: null }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
})

describe('useRecordMergeBulkAction (spec 0394 D-1)', () => {
  it.each(['registries', 'referents'] as const)('offers the action on %s with update and delete', (module) => {
    const { result } = renderHook(() => useRecordMergeBulkAction(module, tableRef))
    const [action] = result.current.getBulkActions(selectionOf(2))

    expect(action.label).toBe('Merge duplicates')
    expect(action.disabled).toBe(false)
    expect(canMock).toHaveBeenCalledWith(`${module}.update`)
    expect(canMock).toHaveBeenCalledWith(`${module}.delete`)
  })

  it.each([
    ['registries.update'],
    ['registries.delete'],
  ])('hides the action without %s', (missing) => {
    canMock.mockImplementation((permission) => permission !== missing)
    const { result } = renderHook(() => useRecordMergeBulkAction('registries', tableRef))

    expect(result.current.getBulkActions(selectionOf(2))).toEqual([])
  })

  it.each([1, 6])('disables the action with a reason when %i rows are selected', (count) => {
    const { result } = renderHook(() => useRecordMergeBulkAction('registries', tableRef))
    const [action] = result.current.getBulkActions(selectionOf(count))

    expect(action.disabled).toBe(true)
    expect(action.disabledReason).toBe('Select 2 to 5 rows to merge duplicates.')
  })

  it('keeps the action enabled at the 5 rows limit', () => {
    const { result } = renderHook(() => useRecordMergeBulkAction('registries', tableRef))

    expect(result.current.getBulkActions(selectionOf(5))[0].disabled).toBe(false)
  })
})
