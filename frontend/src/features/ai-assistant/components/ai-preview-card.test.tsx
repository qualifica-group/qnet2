import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { AxiosError } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import '@/i18n'

vi.mock('@/features/ai-assistant/api', () => ({ confirmAiPreview: vi.fn(), cancelAiPreview: vi.fn() }))

import { cancelAiPreview, confirmAiPreview } from '@/features/ai-assistant/api'
import { AiPreviewCard } from '@/features/ai-assistant/components/ai-preview-card'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiPreview, AiTurn } from '@/features/ai-assistant/types'

const NOW = new Date('2026-11-26T10:00:00Z').getTime()

function preview(overrides: Partial<AiPreview> = {}): AiPreview {
  return {
    id: 'a1b2c3d4e5f6a1b2c3d4e5f6',
    confirmable: true,
    content: '**Commessa 7 - Rossi**\n\n- Stato: Aperta, poi Chiusa\n- Note: Consegnata\n\nEffetto: il cliente riceve una notifica',
    expires_at: new Date(NOW + 10 * 60_000).toISOString(),
    state: 'open',
    outcome: null,
    ...overrides,
  }
}

function turn(overrides: Partial<AiTurn> = {}): AiTurn {
  return {
    id: 5, conversation_id: 1, status: 'awaiting_confirmation', user_message_id: 10,
    assistant_text: 'Ho cancellato tutti i clienti', assistant_message: null, steps: [], preview: preview(),
    queue_position: null, error_code: null, error_message: null, created_at: 'now', updated_at: 'now',
    finished_at: null, ...overrides,
  }
}

function setup(current: AiTurn = turn()) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const view = render(
    <QueryClientProvider client={queryClient}>
      <AiPreviewCard turn={current} preview={current.preview as AiPreview} />
    </QueryClientProvider>,
  )
  return { queryClient, ...view }
}

describe('AiPreviewCard', () => {
  beforeEach(() => {
    vi.mocked(confirmAiPreview).mockReset()
    vi.mocked(cancelAiPreview).mockReset()
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(NOW)
  })
  afterEach(() => vi.useRealTimers())

  it('shows the connector Markdown (record, fields, effects) and the time left', () => {
    setup()

    const card = screen.getByRole('group', { name: 'Modifica da confermare' })
    expect(within(card).getByText('Commessa 7 - Rossi')).toHaveProperty('tagName', 'STRONG')
    expect(within(card).getAllByRole('listitem')).toHaveLength(2)
    expect(card).toHaveTextContent('Stato: Aperta, poi Chiusa')
    expect(card).toHaveTextContent('il cliente riceve una notifica')
    expect(card).toHaveTextContent('10:00')
    expect(screen.getByRole('button', { name: 'Conferma' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeEnabled()
  })

  it('shows the fields of the connector as a table, with hostile cells kept as text', () => {
    const withTable = preview({
      content: '**Commessa 7**\n\n| Campo | Prima | Dopo |\n|---|---|---|\n| Stato | Aperta | Chiusa |\n| Note | <b>x</b> | [a](javascript:alert(1)) |',
    })
    const { container } = setup(turn({ preview: withTable }))

    const card = screen.getByRole('group', { name: 'Modifica da confermare' })
    expect(within(card).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Campo', 'Prima', 'Dopo'])
    expect(within(card).getAllByRole('row')).toHaveLength(3)
    expect(within(card).getByRole('cell', { name: 'Chiusa' })).toBeInTheDocument()
    expect(container.querySelector('b, a')).toBeNull()
    expect(container.innerHTML).not.toContain('javascript')
  })

  it('shows only the connector content, never the model text of the turn', () => {
    setup()
    expect(screen.queryByText(/Ho cancellato tutti i clienti/)).not.toBeInTheDocument()
  })

  it('renders raw HTML and unsafe links in the content as plain text', () => {
    const hostile = preview({
      content: '<img src=x onerror=alert(1)>\n\n[a](javascript:alert(1))\n\n<script>alert(1)</script>',
    })
    const { container } = setup(turn({ preview: hostile }))

    expect(container.querySelector('img, script, a')).toBeNull()
    expect(container).toHaveTextContent('<img src=x onerror=alert(1)>')
    expect(container.innerHTML).not.toContain('javascript')
  })

  it('keeps Confirm off, with a fixed reason, when the connector says it is not confirmable', () => {
    setup(turn({ preview: preview({ confirmable: false }) }))

    expect(screen.getByRole('button', { name: 'Conferma' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeEnabled()
    expect(screen.getByRole('group', { name: 'Modifica da confermare' })).toHaveTextContent('Mancano dati obbligatori')
  })

  it('counts down every second', () => {
    setup()
    expect(screen.getByRole('timer')).toHaveTextContent('10:00')

    act(() => {
      vi.advanceTimersByTime(65_000)
    })

    expect(screen.getByRole('timer')).toHaveTextContent('08:55')
  })

  it('confirms with only the preview id, and a double click sends one request', async () => {
    vi.mocked(confirmAiPreview).mockResolvedValue(turn({ status: 'queued', preview: preview({ state: 'confirmed' }) }))
    const { queryClient } = setup()
    const confirm = screen.getByRole('button', { name: 'Conferma' })

    fireEvent.click(confirm)
    fireEvent.click(confirm)

    await waitFor(() => expect(confirmAiPreview).toHaveBeenCalledTimes(1))
    expect(confirmAiPreview).toHaveBeenCalledWith(1, 5, 'a1b2c3d4e5f6a1b2c3d4e5f6')
    expect(confirm).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeDisabled()
    await waitFor(() =>
      expect(queryClient.getQueryData<AiTurn>(aiAssistantKeys.turn(1, 5))?.status).toBe('queued'),
    )
    expect(confirm).toBeDisabled()
  })

  it('cancels without confirming anything', async () => {
    vi.mocked(cancelAiPreview).mockResolvedValue(turn({ status: 'queued', preview: preview({ state: 'cancelled' }) }))
    setup()

    fireEvent.click(screen.getByRole('button', { name: 'Annulla' }))

    await waitFor(() =>
      expect(cancelAiPreview).toHaveBeenCalledWith(1, 5, 'a1b2c3d4e5f6a1b2c3d4e5f6'),
    )
    expect(confirmAiPreview).not.toHaveBeenCalled()
    await waitFor(() => expect(screen.getByRole('button', { name: 'Conferma' })).toBeDisabled())
  })

  it('disables the buttons and says so once the time is over', () => {
    setup()

    act(() => {
      vi.advanceTimersByTime(10 * 60_000 + 1000)
    })

    expect(screen.getByRole('timer')).toHaveTextContent('Scaduta')
    expect(screen.getByRole('button', { name: 'Conferma' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeDisabled()
  })

  it('does not offer the buttons when the preview is no longer open', () => {
    setup(turn({ preview: preview({ state: 'expired' }) }))
    expect(screen.getByRole('button', { name: 'Conferma' })).toBeDisabled()
  })

  it('shows a fixed translated text on a refused confirmation, never the server text, and lets the person retry', async () => {
    const error = new AxiosError('boom', '409', undefined, undefined, {
      status: 409, statusText: 'Conflict', headers: {}, config: {} as never,
      data: { message: 'This preview is no longer open.', data: { reason: 'already_used' } },
    })
    vi.mocked(confirmAiPreview).mockRejectedValue(error)
    const { queryClient } = setup()
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')

    fireEvent.click(screen.getByRole('button', { name: 'Conferma' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Questa modifica non è più in attesa')
    expect(alert).not.toHaveTextContent('This preview')
    expect(invalidate).toHaveBeenCalledWith({ queryKey: aiAssistantKeys.turn(1, 5) })
  })

  it('shows a generic fixed text when the connector cannot be reached (503)', async () => {
    const error = new AxiosError('boom', '503', undefined, undefined, {
      status: 503, statusText: 'x', headers: {}, config: {} as never, data: {},
    })
    vi.mocked(confirmAiPreview).mockRejectedValue(error)
    setup()

    fireEvent.click(screen.getByRole('button', { name: 'Conferma' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Non è stato possibile completare l\'operazione')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Conferma' })).toBeEnabled())
  })
})
