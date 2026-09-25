import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useCustomFilterState } from '@/features/table/custom-filters/use-custom-filter-state'
import type { FilterRules } from '@/features/table/types'

const RULES: FilterRules = { and: [{ field: 'status', operator: 'equals', value: 'open' }], or: [] }

describe('useCustomFilterState', () => {
  it('starts inactive', () => {
    const { result } = renderHook(() => useCustomFilterState())
    expect(result.current.state).toBeNull()
    expect(result.current.getActive()).toBeNull()
  })

  it('activate runs mutateOthers, then sets the active state', () => {
    const { result } = renderHook(() => useCustomFilterState())
    const mutateOthers = vi.fn()

    act(() => {
      result.current.activate(RULES, { viewId: 5, name: 'My filter' }, mutateOthers)
    })

    expect(mutateOthers).toHaveBeenCalledTimes(1)
    expect(result.current.state).toEqual({ rules: RULES, viewId: 5, name: 'My filter' })
    expect(result.current.getActive()).toEqual(RULES)
  })

  it('suppresses notifyExternalChange calls made from within mutateOthers', () => {
    const { result } = renderHook(() => useCustomFilterState())

    act(() => {
      result.current.activate(RULES, {}, () => {
        // Simulates the grid firing its own filter-changed callback as a side
        // effect of the programmatic reset performed during activation.
        result.current.notifyExternalChange()
      })
    })

    expect(result.current.state).not.toBeNull()
  })

  it('deactivates on a notifyExternalChange call OUTSIDE activation (D-2: "vince l\'ultimo")', () => {
    const { result } = renderHook(() => useCustomFilterState())

    act(() => {
      result.current.activate(RULES, {}, () => {})
    })
    expect(result.current.state).not.toBeNull()

    act(() => {
      result.current.notifyExternalChange()
    })

    expect(result.current.state).toBeNull()
    expect(result.current.getActive()).toBeNull()
  })

  it('notifyExternalChange is a no-op when nothing is active', () => {
    const { result } = renderHook(() => useCustomFilterState())

    act(() => {
      result.current.notifyExternalChange()
    })

    expect(result.current.state).toBeNull()
  })

  it('deactivate clears the state directly', () => {
    const { result } = renderHook(() => useCustomFilterState())

    act(() => {
      result.current.activate(RULES, {}, () => {})
    })
    act(() => {
      result.current.deactivate()
    })

    expect(result.current.state).toBeNull()
  })
})
