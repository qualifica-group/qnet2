import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useRequiredCategories } from '@/features/assignment/use-required-categories'

const fetchRequiredCategoriesMock = vi.fn()

vi.mock('@/features/assignment/api', () => ({
  fetchRequiredCategories: (...args: unknown[]) => fetchRequiredCategoriesMock(...args),
}))

/** One QueryClient per test: sharing it across renders leaks cache between cases. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useRequiredCategories', () => {
  it('issues no request and applies no filter when nothing is selected', () => {
    const { result } = renderHook(() => useRequiredCategories({ selection: null }), {
      wrapper: wrapper(),
    })

    expect(fetchRequiredCategoriesMock).not.toHaveBeenCalled()
    expect(result.current.competenceCategoryIds).toBeUndefined()
    expect(result.current.isResolving).toBe(false)
    expect(result.current.isError).toBe(false)
  })

  it('issues no request while the caller gate is closed', () => {
    renderHook(
      () => useRequiredCategories({ selection: { domain: 'leads', ids: [1] }, enabled: false }),
      { wrapper: wrapper() },
    )

    expect(fetchRequiredCategoriesMock).not.toHaveBeenCalled()
  })

  it('resolves the union for the selection and reports the resolving state', async () => {
    fetchRequiredCategoriesMock.mockResolvedValue([3, 7])

    const { result } = renderHook(
      () =>
        useRequiredCategories({
          selection: { domain: 'import_rows', import_run_id: 12, select_all: false, row_ids: [1, 2] },
        }),
      { wrapper: wrapper() },
    )

    expect(result.current.isResolving).toBe(true)

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([3, 7]))
    expect(result.current.isResolving).toBe(false)
    expect(fetchRequiredCategoriesMock).toHaveBeenCalledWith({
      domain: 'import_rows',
      import_run_id: 12,
      select_all: false,
      row_ids: [1, 2],
    })
  })

  it('applies no filter when the selection expresses no requirement', async () => {
    fetchRequiredCategoriesMock.mockResolvedValue([])

    const { result } = renderHook(
      () => useRequiredCategories({ selection: { domain: 'quotes', ids: [5] } }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isResolving).toBe(false))
    expect(result.current.competenceCategoryIds).toBeUndefined()
  })

  it('surfaces a failed resolution without filtering', async () => {
    fetchRequiredCategoriesMock.mockRejectedValue(new Error('boom'))

    const { result } = renderHook(
      () => useRequiredCategories({ selection: { domain: 'leads', ids: [1] } }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(result.current.competenceCategoryIds).toBeUndefined()
  })

  it('refetches when the selection changes', async () => {
    fetchRequiredCategoriesMock.mockResolvedValueOnce([3]).mockResolvedValueOnce([9])

    const { result, rerender } = renderHook(
      ({ ids }: { ids: number[] }) =>
        useRequiredCategories({ selection: { domain: 'leads', ids } }),
      { wrapper: wrapper(), initialProps: { ids: [1] } },
    )

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([3]))

    rerender({ ids: [2] })

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([9]))
    expect(fetchRequiredCategoriesMock).toHaveBeenCalledTimes(2)
  })
})
