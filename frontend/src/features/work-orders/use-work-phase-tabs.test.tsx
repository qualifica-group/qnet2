import { describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, renderHook, screen } from '@testing-library/react'
import type { RefObject } from 'react'
import '@/i18n'
import type { TableViewHandle } from '@/features/table/table-view'
import { useWorkPhaseTabs } from '@/features/work-orders/use-work-phase-tabs'
import { WorkPhaseTabs } from '@/features/work-orders/work-phase-tabs'

function tableRefWith(setFilterModel: (patch: Record<string, unknown>) => void): RefObject<TableViewHandle | null> {
  return { current: { setFilterModel } as unknown as TableViewHandle }
}

describe('useWorkPhaseTabs', () => {
  it('starts on all and follows the grid filter model', () => {
    const { result } = renderHook(() => useWorkPhaseTabs(tableRefWith(vi.fn())))
    expect(result.current.active).toBe('all')

    act(() => result.current.onFilterModelChange({ status: { filterType: 'set', values: ['terminated'] } }))
    expect(result.current.active).toBe('terminated')

    act(() => result.current.onFilterModelChange({}))
    expect(result.current.active).toBe('all')
  })

  it('keeps the counts the grid reports', () => {
    const { result } = renderHook(() => useWorkPhaseTabs(tableRefWith(vi.fn())))

    act(() => result.current.onAggregatesChange({ all: 2, to_plan: 2 }))

    expect(result.current.counts).toEqual({ all: 2, to_plan: 2 })
  })

  it('selects no tab when the column filter holds several phases', () => {
    const { result } = renderHook(() => useWorkPhaseTabs(tableRefWith(vi.fn())))

    act(() => result.current.onFilterModelChange({ status: { filterType: 'set', values: ['to_plan', 'completed'] } }))

    expect(result.current.active).toBeNull()
  })

  it('writes the phase into the grid filter, and removes it for all', () => {
    const setFilterModel = vi.fn()
    const { result } = renderHook(() => useWorkPhaseTabs(tableRefWith(setFilterModel)))

    result.current.select('cancelled')
    expect(setFilterModel).toHaveBeenLastCalledWith({ status: { filterType: 'set', values: ['cancelled'] } })

    result.current.select('all')
    expect(setFilterModel).toHaveBeenLastCalledWith({ status: null })
  })
})

describe('WorkPhaseTabs', () => {
  it('offers the six legacy tabs in order and reports the pick', () => {
    const onSelect = vi.fn()
    render(<WorkPhaseTabs active="all" onSelect={onSelect} />)

    expect(screen.getAllByRole('tab')).toHaveLength(6)
    expect(screen.getAllByRole('tab')[0]).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(screen.getAllByRole('tab')[5])
    expect(onSelect).toHaveBeenCalledWith('terminated')
  })

  // Spec 0395 (REQUIREMENT CHANGED): "All" has no figure, as on the legacy list.
  it('shows the count of each phase tab, none on all, and none when it is zero or not known yet', () => {
    const { rerender } = render(<WorkPhaseTabs active="all" counts={{ all: 3, to_plan: 3, terminated: 0 }} onSelect={vi.fn()} />)

    const [all, toPlan, , , , terminated] = screen.getAllByRole('tab')
    expect(all.textContent).not.toMatch(/\(/)
    expect(toPlan.textContent).toMatch(/\(3\)$/)
    expect(terminated.textContent).not.toMatch(/\(/)

    rerender(<WorkPhaseTabs active="all" onSelect={vi.fn()} />)
    expect(screen.getAllByRole('tab')[0].textContent).not.toMatch(/\(/)
  })
})
