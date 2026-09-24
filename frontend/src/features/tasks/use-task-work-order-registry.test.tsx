import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { useTaskWorkOrderRegistryId } from '@/features/tasks/use-task-work-order-registry'
import type { ForSelectItem, PaginatedResponse } from '@/features/for-select/types'

/** Builds a well-formed for-select page, `meta` cast in since the base `ForSelectItem` carries none. */
function page(items: Array<ForSelectItem & { meta: Record<string, unknown> }>): PaginatedResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { offset: 0, limit: items.length, total: items.length, total_pages: 1 },
  }
}

/**
 * Spec 0154 D-11: resolves a commessa's own registry via the for-select
 * `ids[]` hydration (frozen contract: `meta.registry_id` on every item),
 * reused rather than a dedicated endpoint.
 */

vi.mock('@/features/for-select/api', () => ({ fetchForSelect: vi.fn() }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  vi.mocked(fetchForSelect).mockReset()
})

describe('useTaskWorkOrderRegistryId', () => {
  it('does not query when there is no work order', () => {
    renderHook(() => useTaskWorkOrderRegistryId(null), { wrapper: wrapper() })

    expect(fetchForSelect).not.toHaveBeenCalled()
  })

  it('resolves the registry id from the hydrated item meta', async () => {
    vi.mocked(fetchForSelect).mockResolvedValue(page([{ id: 9, label: 'WO-1', meta: { registry_id: 7 } }]))

    const { result } = renderHook(() => useTaskWorkOrderRegistryId(9), { wrapper: wrapper() })

    await waitFor(() => expect(result.current).toBe(7))
    expect(fetchForSelect).toHaveBeenCalledWith('work-orders', { ids: [9], limit: 1 })
  })

  it('resolves null when the commessa carries no registry', async () => {
    vi.mocked(fetchForSelect).mockResolvedValue(page([{ id: 9, label: 'WO-1', meta: { registry_id: null } }]))

    const { result } = renderHook(() => useTaskWorkOrderRegistryId(9), { wrapper: wrapper() })

    await waitFor(() => expect(fetchForSelect).toHaveBeenCalled())
    expect(result.current).toBeNull()
  })
})
