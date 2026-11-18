import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useMarkReadOnLoad } from '@/hooks/use-mark-read-on-load'

function wrapperFor(client: QueryClient) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

function setup(marked: number, initialLoadedAt: number) {
  const markRead = vi.fn().mockResolvedValue(marked)
  const onMarked = vi.fn()
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const hook = renderHook(({ loadedAt }) => useMarkReadOnLoad(loadedAt, markRead, onMarked), {
    initialProps: { loadedAt: initialLoadedAt },
    wrapper: wrapperFor(client),
  })
  return { markRead, onMarked, ...hook }
}

describe('useMarkReadOnLoad', () => {
  it('marks nothing until the list has arrived', () => {
    const { markRead } = setup(3, 0)

    expect(markRead).not.toHaveBeenCalled()
  })

  it('marks the list read once it is on screen and tells the host when something became read', async () => {
    const { markRead, onMarked } = setup(2, 1000)

    await waitFor(() => expect(onMarked).toHaveBeenCalledTimes(1))
    expect(markRead).toHaveBeenCalledTimes(1)
  })

  it('does not bother the host when nothing new became read', async () => {
    const { markRead, onMarked } = setup(0, 1000)

    await waitFor(() => expect(markRead).toHaveBeenCalledTimes(1))
    expect(onMarked).not.toHaveBeenCalled()
  })

  it('marks again on every refetch, never on a plain rerender', async () => {
    const { markRead, rerender } = setup(0, 1000)
    await waitFor(() => expect(markRead).toHaveBeenCalledTimes(1))

    rerender({ loadedAt: 1000 })
    rerender({ loadedAt: 2000 })

    await waitFor(() => expect(markRead).toHaveBeenCalledTimes(2))
  })
})
