// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/features/ai-assistant/api', () => ({ fetchAiTurn: vi.fn() }))

import { fetchAiTurn } from '@/features/ai-assistant/api'
import { useAiActiveTurn } from '@/features/ai-assistant/hooks/use-ai-active-turn'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiTurn, AiTurnStatus } from '@/features/ai-assistant/types'

const POLL_MS = 15

function turn(status: AiTurnStatus, overrides: Partial<AiTurn> = {}): AiTurn {
  return {
    id: 5, conversation_id: 1, status, user_message_id: 10, assistant_text: '', assistant_message: null,
    steps: [], preview: null, queue_position: null, error_code: null, error_message: null,
    created_at: 'now', updated_at: 'now', finished_at: null, ...overrides,
  }
}

function setup(serverTurn: AiTurn | null = null, conversationId: number | null = 1) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
  const view = renderHook(
    () => useAiActiveTurn({ conversationId, serverTurn, pollIntervalMs: POLL_MS }),
    { wrapper },
  )
  return { queryClient, ...view }
}

describe('useAiActiveTurn', () => {
  beforeEach(() => vi.mocked(fetchAiTurn).mockReset())

  it('shows the queued turn at once, polls it, and stops on a final state', async () => {
    let finished = false
    vi.mocked(fetchAiTurn).mockImplementation(async () =>
      finished
        ? turn('completed', { assistant_text: 'Ecco tutto' })
        : turn('running', { assistant_text: 'Ecco', steps: [{ index: 0, tool: 'list_records', label: 'Leggo', state: 'running' }] }),
    )
    const { result, queryClient } = setup()
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')

    act(() => result.current.track(turn('queued')))
    expect(result.current.turn?.status).toBe('queued')

    await waitFor(() => expect(result.current.turn?.status).toBe('running'))
    expect(result.current.turn?.assistant_text).toBe('Ecco')
    finished = true
    await waitFor(() => expect(result.current.turn?.status).toBe('completed'))
    expect(invalidate).toHaveBeenCalledWith({ queryKey: aiAssistantKeys.conversation(1) })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: aiAssistantKeys.conversations })

    const calls = vi.mocked(fetchAiTurn).mock.calls.length
    await new Promise((resolve) => setTimeout(resolve, POLL_MS * 6))
    expect(vi.mocked(fetchAiTurn)).toHaveBeenCalledTimes(calls)
  })

  it('stops polling while the turn waits for the confirmation (preview card is ticket 05)', async () => {
    vi.mocked(fetchAiTurn).mockResolvedValue(turn('awaiting_confirmation'))
    const { result } = setup()

    act(() => result.current.track(turn('queued')))
    await waitFor(() => expect(result.current.turn?.status).toBe('awaiting_confirmation'))

    const calls = vi.mocked(fetchAiTurn).mock.calls.length
    await new Promise((resolve) => setTimeout(resolve, POLL_MS * 6))
    expect(vi.mocked(fetchAiTurn)).toHaveBeenCalledTimes(calls)
  })

  it('resumes the polling of the active turn sent by the conversation detail', async () => {
    vi.mocked(fetchAiTurn).mockResolvedValue(turn('failed', { error_code: 'turn_timeout' }))
    const { result } = setup(turn('running'))

    expect(result.current.turn?.status).toBe('running')
    await waitFor(() => expect(result.current.turn?.status).toBe('failed'))
    expect(fetchAiTurn).toHaveBeenCalledWith(1, 5)
  })

  it('keeps showing the closed turn after the server stops listing it as active', async () => {
    vi.mocked(fetchAiTurn).mockResolvedValue(turn('failed', { error_code: 'internal' }))
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )
    const { result, rerender } = renderHook(
      ({ serverTurn }: { serverTurn: AiTurn | null }) =>
        useAiActiveTurn({ conversationId: 1, serverTurn, pollIntervalMs: POLL_MS }),
      { wrapper, initialProps: { serverTurn: turn('running') as AiTurn | null } },
    )
    await waitFor(() => expect(result.current.turn?.status).toBe('failed'))

    rerender({ serverTurn: null })

    expect(result.current.turn?.status).toBe('failed')
  })

  it('does not show a turn of another conversation', () => {
    const { result, rerender } = renderHook(
      ({ id }: { id: number }) => useAiActiveTurn({ conversationId: id, serverTurn: null, pollIntervalMs: POLL_MS }),
      {
        wrapper: ({ children }: { children: ReactNode }) => (
          <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
        ),
        initialProps: { id: 1 },
      },
    )
    act(() => result.current.track(turn('queued')))
    expect(result.current.turn).not.toBeNull()

    rerender({ id: 2 })

    expect(result.current.turn).toBeNull()
  })
})
