import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useRequestManagementCategoryTab } from '@/features/request-management/use-request-management-category-tab'
import { fetchRequestManagementCategories } from '@/features/request-management/api'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

vi.mock('@/features/request-management/api', () => ({
  fetchRequestManagementCategories: vi.fn(),
}))

const fetchCategoriesMock = vi.mocked(fetchRequestManagementCategories)

const STORAGE_KEY = 'request-management.category-tab'

const CATEGORIES: RequestManagementProductCategory[] = [
  { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  window.localStorage.clear()
  fetchCategoriesMock.mockReset()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useRequestManagementCategoryTab (spec 0064)', () => {
  it('selects the persisted category once it is confirmed present in the live list (AC-021)', async () => {
    window.localStorage.setItem(STORAGE_KEY, '12')
    fetchCategoriesMock.mockResolvedValue(CATEGORIES)

    const { result } = renderHook(() => useRequestManagementCategoryTab(), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.selectedCategoryId).toBe(12))
  })

  it('falls back to "Tutte" without error when the persisted category is no longer in the live list', async () => {
    window.localStorage.setItem(STORAGE_KEY, '999')
    fetchCategoriesMock.mockResolvedValue(CATEGORIES)

    const { result } = renderHook(() => useRequestManagementCategoryTab(), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.isPending).toBe(false))
    expect(result.current.selectedCategoryId).toBeNull()
  })

  it('persists a newly picked category and reflects it immediately', async () => {
    fetchCategoriesMock.mockResolvedValue(CATEGORIES)
    const { result } = renderHook(() => useRequestManagementCategoryTab(), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))

    act(() => result.current.setCategoryId(12))

    expect(result.current.selectedCategoryId).toBe(12)
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('12')
  })
})
