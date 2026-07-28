import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { useRequestManagementCategoryPreference } from '@/features/request-management/use-request-management-category-preference'

const STORAGE_KEY = 'request-management.category-tab'

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useRequestManagementCategoryPreference (spec 0064 AC-021)', () => {
  it('defaults to "Tutte" (null) when nothing was stored yet', () => {
    const { result } = renderHook(() => useRequestManagementCategoryPreference())

    expect(result.current.categoryId).toBeNull()
  })

  it('persists the picked category id to localStorage', () => {
    const { result } = renderHook(() => useRequestManagementCategoryPreference())

    act(() => result.current.setCategoryId(12))

    expect(result.current.categoryId).toBe(12)
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('12')
  })

  it('survives a remount: a fresh mount reads the previously stored id back', () => {
    const first = renderHook(() => useRequestManagementCategoryPreference())
    act(() => first.result.current.setCategoryId(12))
    first.unmount()

    const second = renderHook(() => useRequestManagementCategoryPreference())

    expect(second.result.current.categoryId).toBe(12)
  })

  it('clears the stored preference when picking "Tutte" (null) again', () => {
    const { result } = renderHook(() => useRequestManagementCategoryPreference())
    act(() => result.current.setCategoryId(12))

    act(() => result.current.setCategoryId(null))

    expect(result.current.categoryId).toBeNull()
    expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('ignores a corrupted stored value, falling back to "Tutte"', () => {
    window.localStorage.setItem(STORAGE_KEY, 'not-a-number')

    const { result } = renderHook(() => useRequestManagementCategoryPreference())

    expect(result.current.categoryId).toBeNull()
  })
})
