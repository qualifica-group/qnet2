import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import '@/i18n'
import { AiTurnStatus } from '@/features/ai-assistant/components/ai-turn-status'
import type { AiPreview, AiTurn, AiTurnStatus as Status } from '@/features/ai-assistant/types'

function turn(status: Status, overrides: Partial<AiTurn> = {}): AiTurn {
  return {
    id: 5, conversation_id: 1, status, user_message_id: 10, assistant_text: '', assistant_message: null,
    steps: [], preview: null, queue_position: null, error_code: null, error_message: null,
    created_at: 'now', updated_at: 'now', finished_at: null, ...overrides,
  }
}

describe('AiTurnStatus', () => {
  it('shows the queued state, with the position when the server sends it', () => {
    const { rerender } = render(<AiTurnStatus turn={turn('queued')} />)
    expect(screen.getByRole('status')).toHaveTextContent('In coda: l\'assistente inizia a breve.')

    rerender(<AiTurnStatus turn={turn('queued', { queue_position: 3 })} />)
    expect(screen.getByRole('status')).toHaveTextContent('In coda, posizione 3')
  })

  it('shows the partial text, with the steps and their state, while running', () => {
    render(
      <AiTurnStatus
        turn={turn('running', {
          assistant_text: 'Ecco **le commesse**',
          steps: [
            { index: 0, tool: 'search_operations', label: 'Cerco l\'operazione', state: 'done' },
            { index: 1, tool: 'list_records', label: 'Leggo le commesse', state: 'running' },
          ],
        })}
      />,
    )

    const steps = screen.getByRole('list', { name: 'Passi dell\'assistente' })
    const items = within(steps).getAllByRole('listitem')
    expect(items[0]).toHaveTextContent('Cerco l\'operazione')
    expect(items[0]).toHaveTextContent('completato')
    expect(items[1]).toHaveTextContent('Leggo le commesse')
    expect(items[1]).toHaveTextContent('in corso')
    expect(screen.getByText('le commesse')).toHaveProperty('tagName', 'STRONG')
  })

  it('shows the typing hint while running with neither text nor steps', () => {
    render(<AiTurnStatus turn={turn('running')} />)
    expect(screen.getByRole('status')).toHaveTextContent('L\'assistente sta scrivendo...')
  })

  it('keeps the closed card with its outcome once the turn is over', () => {
    const preview: AiPreview = {
      id: 'p1', confirmable: true, content: 'Record 1: uno, poi due',
      expires_at: new Date(Date.now() - 1000).toISOString(), state: 'expired', outcome: 'expired',
    }
    render(
      <QueryClientProvider client={new QueryClient()}>
        <AiTurnStatus turn={turn('completed', { preview, assistant_text: 'Non ho scritto nulla' })} />
      </QueryClientProvider>,
    )

    expect(screen.getByRole('group', { name: 'Modifica da confermare' })).toHaveTextContent('Scaduta: non è stato scritto nulla.')
    expect(screen.queryByRole('button', { name: 'Conferma' })).not.toBeInTheDocument()
    expect(screen.getByText('Non ho scritto nulla')).toBeInTheDocument()
  })

  it.each([
    ['succeeded', 'confirmed', 'la modifica è stata scritta'],
    ['uncertain', 'uncertain', 'Esito incerto'],
  ] as const)('keeps the closed card with its %s outcome while the turn is queued again', (outcome, state, text) => {
    const preview: AiPreview = {
      id: 'p1', confirmable: true, content: 'Record 1: uno, poi due',
      expires_at: new Date(Date.now() + 600_000).toISOString(), state, outcome,
    }
    render(
      <QueryClientProvider client={new QueryClient()}>
        <AiTurnStatus turn={turn('queued', { preview })} />
      </QueryClientProvider>,
    )

    expect(screen.getByRole('group', { name: 'Modifica da confermare' })).toHaveTextContent(text)
    expect(screen.queryByRole('button', { name: 'Conferma' })).not.toBeInTheDocument()
    expect(screen.getByText(/In coda/)).toBeInTheDocument()
  })

  it('shows the preview card with Confirm and Cancel while waiting for the confirmation', () => {
    const preview: AiPreview = {
      id: 'p1', confirmable: true, content: 'Record 1: uno, poi due',
      expires_at: new Date(Date.now() + 600_000).toISOString(), state: 'open', outcome: null,
    }
    const queryClient = new QueryClient()
    render(
      <QueryClientProvider client={queryClient}>
        <AiTurnStatus turn={turn('awaiting_confirmation', { preview, assistant_text: 'Testo del modello' })} />
      </QueryClientProvider>,
    )

    expect(screen.getByRole('status')).toHaveTextContent('aspetta la tua conferma')
    expect(screen.getByRole('group', { name: 'Modifica da confermare' })).toHaveTextContent('Record 1')
    expect(screen.getByRole('button', { name: 'Conferma' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Annulla' })).toBeInTheDocument()
    expect(screen.queryByText('Testo del modello')).not.toBeInTheDocument()
  })

  it('shows no card, and no buttons, when the awaiting turn carries no preview', () => {
    render(<AiTurnStatus turn={turn('awaiting_confirmation')} />)
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows a fixed translated text per error code, never the server text', () => {
    render(
      <AiTurnStatus
        turn={turn('failed', { error_code: 'turn_timeout', error_message: 'English server text', assistant_text: 'Parziale' })}
      />,
    )
    expect(screen.getByRole('alert')).toHaveTextContent('la richiesta ha richiesto troppo tempo')
    expect(screen.queryByText('English server text')).not.toBeInTheDocument()
    expect(screen.getByText('Parziale')).toBeInTheDocument()
  })

  it.each([
    ['failed', null, 'L\'assistente si è fermato. Il tuo messaggio è salvato'],
    ['cancelled', null, 'La richiesta è stata annullata'],
    ['limit_reached', 'limit_person', 'limite mensile di spesa'],
    ['failed', 'not_a_known_code' as never, 'L\'assistente si è fermato. Il tuo messaggio è salvato'],
  ] as const)('falls back to the state text: %s / %s', (status, code, text) => {
    render(<AiTurnStatus turn={turn(status, { error_code: code })} />)
    expect(screen.getByRole('alert')).toHaveTextContent(text)
  })

  it('keeps the final text of a completed turn until the saved message replaces it', () => {
    render(<AiTurnStatus turn={turn('completed', { assistant_text: 'Fatto' })} />)
    expect(screen.getByText('Fatto')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
