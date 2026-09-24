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

describe('useTaskListUrlFilters (spec 0151 D-2/D-5, spec 0153 D-1, AC-008/AC-019)', () => {
  it('no query string ⇒ no override', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), { wrapper: wrapperFor('/tasks') })

    expect(result.current.override).toBeNull()
  })

  it('reads status + a single assignment value as a one-element array', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=assigned_to_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: ['assigned_to_me'] })
  })

  it('assignment without status defaults status to open', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?assignment=created_by_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: ['created_by_me'] })
  })

  // REQUIREMENT CHANGED (spec 0153 D-1): `assignment` is now a multi-value
  // filter — a repeated query param becomes the union, order preserved.
  it('reads several repeated assignment values as one array (spec 0153 D-1)', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=assigned_to_me&assignment=observed_by_me'),
    })

    expect(result.current.override).toEqual({
      status: 'open',
      assignment: ['assigned_to_me', 'observed_by_me'],
    })
  })

  it('recognizes the explicit "all" assignment value (spec 0153 D-1)', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=all'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: ['all'] })
  })

  it('drops an unknown value out of a repeated assignment param, keeping the recognized ones', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=open&assignment=nope&assignment=created_by_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: ['created_by_me'] })
  })

  it('an unknown status value is ignored', () => {
    const { result } = renderHook(() => useTaskListUrlFilters(), {
      wrapper: wrapperFor('/tasks?status=not-a-real-status&assignment=assigned_to_me'),
    })

    expect(result.current.override).toEqual({ status: 'open', assignment: ['assigned_to_me'] })
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
