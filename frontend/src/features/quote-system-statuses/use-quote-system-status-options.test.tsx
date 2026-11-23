import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useQuoteSystemStatusOptions } from '@/features/quote-system-statuses/use-quote-system-status-options'

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

describe('useQuoteSystemStatusOptions', () => {
  it('maps the for-select items to the editor options, keeping the system key', async () => {
    fetchForSelectMock.mockResolvedValue({
      items: [
        { id: 1, label: 'Aperto', meta: { color: 'green', behavior: 'open', system_key: 'open' } },
        { id: 3, label: 'Vinto provvisorio', meta: { color: 'violet', behavior: 'validated', system_key: null } },
      ],
      export_link: null,
      pagination: { total: 2, offset: 0, limit: 100, total_pages: 1 },
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>

    const { result } = renderHook(() => useQuoteSystemStatusOptions(), { wrapper })

    await waitFor(() => expect(result.current.options).toHaveLength(2))
    expect(fetchForSelectMock).toHaveBeenCalledWith('quote-system-statuses', { limit: 100 })
    expect(result.current.options).toEqual([
      { id: 1, label: 'Aperto', color: 'green', behavior: 'open', systemKey: 'open' },
      { id: 3, label: 'Vinto provvisorio', color: 'violet', behavior: 'validated', systemKey: null },
    ])
  })
})
