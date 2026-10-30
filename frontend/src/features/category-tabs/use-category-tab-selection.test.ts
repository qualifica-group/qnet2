// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { useCategoryTabSelection } from '@/features/category-tabs/use-category-tab-selection'

const STORAGE_KEY = 'opportunities.category-tab'

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useCategoryTabSelection (spec 0064 AC-021, spec 0335)', () => {
  it('defaults to "Tutte" (null) when nothing was stored yet', () => {
    const { result } = renderHook(() => useCategoryTabSelection('opportunities'))

    expect(result.current.categoryId).toBeNull()
  })

  it('persists the picked id under the source key and survives a remount', () => {
    const first = renderHook(() => useCategoryTabSelection('opportunities'))
    act(() => first.result.current.setCategoryId(12))
    first.unmount()

    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('12')
    expect(renderHook(() => useCategoryTabSelection('opportunities')).result.current.categoryId).toBe(12)
  })

  it('keeps one entry per source', () => {
    const { result } = renderHook(() => useCategoryTabSelection('opportunities'))
    act(() => result.current.setCategoryId(12))

    expect(renderHook(() => useCategoryTabSelection('quotes')).result.current.categoryId).toBeNull()
  })

  it('clears the entry when picking "Tutte" again', () => {
    const { result } = renderHook(() => useCategoryTabSelection('opportunities'))
    act(() => result.current.setCategoryId(12))

    act(() => result.current.setCategoryId(null))

    expect(result.current.categoryId).toBeNull()
    expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('ignores a corrupted stored value', () => {
    window.localStorage.setItem(STORAGE_KEY, 'not-a-number')

    expect(renderHook(() => useCategoryTabSelection('opportunities')).result.current.categoryId).toBeNull()
  })
})
