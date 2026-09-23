import { describe, expect, it } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { useTaskListUrlFilters } from '@/features/tasks/use-task-list-url-filters'

function wrapperFor(initialEntry: string) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <MemoryRouter initialEntries={[initialEntry]}>{children}</MemoryRouter>
  }
}

describe('useTaskListUrlFilters (spec 0151 D-2/D-5, AC-008)', () => {
  it('no query string ⇒ no override', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), { wrapper: wrapperFor('/tasks') })

    expect(result.current.override).toBeNull()
  })

  it('reads status + assignment when both are recognized values', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=assigned_to_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: 'assigned_to_me' })
  })

  it('assignment without status defaults status to open', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?assignment=created_by_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: 'created_by_me' })
  })

  it('a new D-3 assignment value is recognized', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=in_validation&assignment=observed_by_me'),
    })

    expect(result.current.override).toEqual({ status: 'in_validation', assignment: 'observed_by_me' })
  })

  it('an unknown value is ignored', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=not-a-real-status&assignment=assigned_to_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: 'assigned_to_me' })
  })

  it('every value unknown ⇒ no override', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=nope&assignment=nope'),
    })

    expect(result.current.override).toBeNull()
  })

  it('clear() strips status/assignment from the URL, leaving other params untouched', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=assigned_to_me&page=2'),
    })

    act(() => result.current.clear())

    expect(result.current.override).toBeNull()
  })
})
