import { describe, expect, it, vi, beforeEach } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import {
  flattenForSelectPages,
  useForSelect,
  useForSelectLabels,
} from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectPagination,
  ForSelectResponse,
} from '@/features/for-select/types'

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: unknown) =>
    fetchForSelectMock(resource, params),
}))

function page(
  items: ForSelectItem[],
  pagination: Partial<ForSelectPagination> & { offset: number; limit: number },
): ForSelectResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { total: null, total_pages: null, ...pagination },
  }
}

function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  fetchForSelectMock.mockReset()
})

describe('useForSelect', () => {
  it('requests the first page with offset 0 and hydration ids', async () => {
    fetchForSelectMock.mockResolvedValue(
      page([{ id: 1, label: 'Jane' }], { total: 1, offset: 0, limit: 25 }),
    )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: 'ja', ids: [7] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(fetchForSelectMock).toHaveBeenCalledWith('users', {
      search: 'ja',
      offset: 0,
      limit: 25,
      ids: [7],
      includeTotal: false,
    })
  })

  it('stops paginating once offset + limit reaches total', async () => {
    fetchForSelectMock.mockResolvedValue(
      page([{ id: 1, label: 'A' }], { total: 1, offset: 0, limit: 25 }),
    )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: '' }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.hasNextPage).toBe(false)
  })

  it('exposes a next page while more rows remain and drops ids on later pages', async () => {
    fetchForSelectMock
      .mockResolvedValueOnce(
        page([{ id: 1, label: 'A' }], { total: 30, offset: 0, limit: 25 }),
      )
      .mockResolvedValueOnce(
        page([{ id: 2, label: 'B' }], { total: 30, offset: 25, limit: 25 }),
      )

    const { result } = renderHook(
      () => useForSelect({ resource: 'users', search: '', ids: [99] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.hasNextPage).toBe(true)

    await result.current.fetchNextPage()
    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledTimes(2),
    )

    // First page carries ids; the second page must not re-send them.
    expect(fetchForSelectMock.mock.calls[0][1]).toMatchObject({ ids: [99] })
    expect(fetchForSelectMock.mock.calls[1][1]).toMatchObject({
      offset: 25,
      ids: undefined,
    })
  })

  it('does not run while disabled', () => {
    renderHook(
      () => useForSelect({ resource: 'users', search: '', enabled: false }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})

describe('useForSelect include_total / has_more (spec 0178 D-7, AC-028)', () => {
  async function loadFirstPage(pagination: Parameters<typeof page>[1]) {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'A' }], pagination))
    const hook = renderHook(
      () => useForSelect({ resource: 'quotes', search: '' }),
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(hook.result.current.isSuccess).toBe(true))
    return hook.result.current
  }

  it('asks the server to skip the total on every list request', async () => {
    await loadFirstPage({ offset: 0, limit: 25, has_more: true })
    expect(fetchForSelectMock.mock.calls[0][1]).toMatchObject({ includeTotal: false })
  })

  it('has a next page when has_more is true, even with a null total', async () => {
    const result = await loadFirstPage({ offset: 0, limit: 25, has_more: true })
    expect(result.hasNextPage).toBe(true)
  })

  it('stops when has_more is false', async () => {
    const result = await loadFirstPage({ offset: 25, limit: 25, has_more: false })
    expect(result.hasNextPage).toBe(false)
  })

  it('requests the next offset as offset + limit when has_more is true', async () => {
    fetchForSelectMock
      .mockResolvedValueOnce(page([{ id: 1, label: 'A' }], { offset: 0, limit: 25, has_more: true }))
      .mockResolvedValueOnce(page([{ id: 2, label: 'B' }], { offset: 25, limit: 25, has_more: false }))
    const { result } = renderHook(
      () => useForSelect({ resource: 'quotes', search: '' }),
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    act(() => {
      void result.current.fetchNextPage()
    })
    await waitFor(() => expect(result.current.data?.pages).toHaveLength(2))
    expect(fetchForSelectMock.mock.calls[1][1]).toMatchObject({ offset: 25 })
    expect(result.current.hasNextPage).toBe(false)
  })

  it('falls back to offset + limit < total when has_more is absent', async () => {
    const more = await loadFirstPage({ total: 30, offset: 0, limit: 25 })
    expect(more.hasNextPage).toBe(true)
  })

  it('falls back to no next page when has_more is absent and total is reached', async () => {
    const done = await loadFirstPage({ total: 25, offset: 0, limit: 25 })
    expect(done.hasNextPage).toBe(false)
  })

  it('does not send include_total on the labels-by-ids query', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 5, label: 'Alice' }], { offset: 0, limit: 1 }))
    const { result } = renderHook(
      () => useForSelectLabels({ resource: 'quotes', ids: [5] }),
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(result.current.size).toBe(1))
    expect(fetchForSelectMock.mock.calls[0][1]).not.toHaveProperty('includeTotal')
  })
})

describe('useForSelectLabels', () => {
  it('resolves an id set into a label map, requesting exactly those ids', async () => {
    fetchForSelectMock.mockResolvedValue(
      page(
        [
          { id: 5, label: 'Alice' },
          { id: 88, label: 'Bob' },
        ],
        { total: 0, offset: 0, limit: 2 },
      ),
    )

    const { result } = renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [88, 5] }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.size).toBe(2))
    expect(result.current.get(5)?.label).toBe('Alice')
    expect(result.current.get(88)?.label).toBe('Bob')
    // Ids are sorted for a stable, order-independent query key.
    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ offset: 0, ids: [5, 88] }),
    )
  })

  it('does not run for an empty id set', () => {
    const { result } = renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [] }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
    expect(result.current.size).toBe(0)
  })

  it('does not run while disabled', () => {
    renderHook(
      () => useForSelectLabels({ resource: 'users', ids: [5], enabled: false }),
      { wrapper: wrapper() },
    )
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })
})

describe('flattenForSelectPages', () => {
  it('returns an empty list when there are no pages', () => {
    expect(flattenForSelectPages(undefined)).toEqual([])
  })

  it('flattens pages and de-duplicates by id', () => {
    const result = flattenForSelectPages([
      { items: [{ id: 1, label: 'A' }, { id: 2, label: 'B' }] },
      { items: [{ id: 2, label: 'B' }, { id: 3, label: 'C' }] },
    ])
    expect(result.map((item) => item.id)).toEqual([1, 2, 3])
  })
})
