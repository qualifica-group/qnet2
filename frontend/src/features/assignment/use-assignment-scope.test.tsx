import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useAssignmentScope } from '@/features/assignment/use-assignment-scope'
import { assignmentKeys } from '@/features/assignment/query-keys'

const fetchAssignmentScopeMock = vi.fn()

vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

/** One QueryClient per test: sharing it across renders leaks cache between cases. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function scope(overrides: {
  product_category_ids?: number[]
  operational_site_id?: number | null
  campaign_ids?: number[]
}) {
  return {
    product_category_ids: [],
    operational_site_id: null,
    campaign_ids: [],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAssignmentScope', () => {
  it('issues no request and applies no filter when nothing is selected', () => {
    const { result } = renderHook(() => useAssignmentScope({ selection: null }), {
      wrapper: wrapper(),
    })

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()
    expect(result.current.competenceCategoryIds).toBeUndefined()
    expect(result.current.operationalSiteId).toBeUndefined()
    expect(result.current.campaignIds).toBeUndefined()
    expect(result.current.isResolving).toBe(false)
    expect(result.current.isError).toBe(false)
  })

  it('issues no request while the caller gate is closed', () => {
    renderHook(
      () => useAssignmentScope({ selection: { domain: 'leads', ids: [1] }, enabled: false }),
      { wrapper: wrapper() },
    )

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()
  })

  it('maps the three fields of the resolved scope and reports the resolving state', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ product_category_ids: [3, 7], operational_site_id: 5, campaign_ids: [2, 8] }),
    )

    const { result } = renderHook(
      () =>
        useAssignmentScope({
          selection: {
            domain: 'import_rows',
            import_run_id: 12,
            select_all: false,
            row_ids: [1, 2],
          },
        }),
      { wrapper: wrapper() },
    )

    expect(result.current.isResolving).toBe(true)
    expect(result.current.operationalSiteId).toBeUndefined()
    expect(result.current.campaignIds).toBeUndefined()

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([3, 7]))
    expect(result.current.operationalSiteId).toBe(5)
    expect(result.current.campaignIds).toEqual([2, 8])
    expect(result.current.isResolving).toBe(false)
    expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({
      domain: 'import_rows',
      import_run_id: 12,
      select_all: false,
      row_ids: [1, 2],
    })
  })

  it('applies no competence filter when the selection expresses no requirement', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ operational_site_id: 4, campaign_ids: [1] }),
    )

    const { result } = renderHook(
      () => useAssignmentScope({ selection: { domain: 'quotes', ids: [5] } }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isResolving).toBe(false))
    expect(result.current.competenceCategoryIds).toBeUndefined()
    expect(result.current.operationalSiteId).toBe(4)
  })

  it('keeps a mixed selection distinguishable from an unresolved one', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ product_category_ids: [4], operational_site_id: null, campaign_ids: [2, 3] }),
    )

    const { result } = renderHook(
      () => useAssignmentScope({ selection: { domain: 'leads', ids: [1, 2] } }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isResolving).toBe(false))
    expect(result.current.operationalSiteId).toBeNull()
    expect(result.current.campaignIds).toEqual([2, 3])
  })

  it('surfaces a failed resolution without filtering anything (AC-034)', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('boom'))

    const { result } = renderHook(
      () => useAssignmentScope({ selection: { domain: 'leads', ids: [1] } }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isError).toBe(true))
    expect(result.current.competenceCategoryIds).toBeUndefined()
    expect(result.current.operationalSiteId).toBeUndefined()
    expect(result.current.campaignIds).toBeUndefined()
  })

  it('refetches when the selection changes', async () => {
    fetchAssignmentScopeMock
      .mockResolvedValueOnce(scope({ product_category_ids: [3] }))
      .mockResolvedValueOnce(scope({ product_category_ids: [9] }))

    const { result, rerender } = renderHook(
      ({ ids }: { ids: number[] }) => useAssignmentScope({ selection: { domain: 'leads', ids } }),
      { wrapper: wrapper(), initialProps: { ids: [1] } },
    )

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([3]))

    rerender({ ids: [2] })

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([9]))
    expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(2)
  })

  it('resolves an identical selection once, whatever the object identity', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ product_category_ids: [3] }))

    const { result, rerender } = renderHook(
      ({ ids }: { ids: number[] }) => useAssignmentScope({ selection: { domain: 'leads', ids } }),
      { wrapper: wrapper(), initialProps: { ids: [1, 2] } },
    )

    await waitFor(() => expect(result.current.competenceCategoryIds).toEqual([3]))

    rerender({ ids: [1, 2] })

    await waitFor(() => expect(result.current.isResolving).toBe(false))
    expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1)
    expect(assignmentKeys.selectionScope({ domain: 'leads', ids: [1, 2] })).toEqual(
      assignmentKeys.selectionScope({ domain: 'leads', ids: [1, 2] }),
    )
  })
})
