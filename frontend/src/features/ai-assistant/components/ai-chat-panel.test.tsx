import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import { useState } from 'react'
import { AiAssistantPageView } from '@/features/ai-assistant/components/ai-assistant-page-view'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import AiDockWindow from '@/features/ai-assistant/components/ai-dock-window'
import type { AiAssistantConfig, AiConversationDetail, AiMessage, AiPreview, AiTurn } from '@/features/ai-assistant/types'

vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
  createAiConversation: vi.fn(),
  deleteAiConversation: vi.fn(),
  downloadAiAttachment: vi.fn(),
  sendAiMessage: vi.fn(),
  fetchAiTurn: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: vi.fn(() => ({ locale: 'it', guideKey: 'general', guideText: 'GUIDE' })),
}))

import {
  deleteAiConversation,
  downloadAiAttachment,
  fetchAiAssistantConfig,
  fetchAiConversation,
  fetchAiConversations,
  fetchAiTurn,
} from '@/features/ai-assistant/api'
import { useAiPageGuide } from '@/features/ai-assistant/hooks/use-ai-page-guide'

const saveBlob = vi.hoisted(() => vi.fn())
vi.mock('@/lib/download', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/download')>()),
  saveBlob,
}))

function message(overrides: Partial<AiMessage>): AiMessage {
  return {
    id: 1,
    role: 'user',
    content: 'Ciao',
    mentions: [],
    attachments: [],
    model: null,
    input_tokens: null,
    output_tokens: null,
    created_at: '2026-11-02T10:00:00Z',
    ...overrides,
  }
}

const DETAIL: AiConversationDetail = {
  id: 3,
  title: 'Preventivo ACME',
  last_message_at: '2026-11-01T10:00:00Z',
  created_at: '2026-11-01T09:00:00Z',
  messages: [
    message({
      id: 1,
      content: 'Guarda **questo**',
      mentions: [{ module: 'companies', id: 5, label: 'ACME Spa' }],
      attachments: [{ id: 9, original_name: 'offerta.pdf', mime_type: 'application/pdf', size: 10 }],
    }),
    message({ id: 2, role: 'assistant', content: 'Ecco **il riepilogo**' }),
  ],
}

function Harness({ config }: { config: AiAssistantConfig }) {
  vi.mocked(fetchAiAssistantConfig).mockResolvedValue(config)
  const [id, setId] = useState<number | null>(3)
  return <AiChatPanel variant="widget" conversationId={id} onConversationChange={setId} />
}

beforeEach(() => {
  vi.mocked(useAiPageGuide).mockClear()
  vi.mocked(fetchAiConversation).mockResolvedValue(DETAIL)
  vi.mocked(fetchAiConversations).mockResolvedValue({
    items: [{ id: 3, title: 'Preventivo ACME', last_message_at: '2026-11-01T10:00:00Z', created_at: '2026-11-01T09:00:00Z' }],
    pagination: { total: 1, offset: 0, limit: 30, total_pages: 1 },
  })
  saveBlob.mockClear()
})

describe('AiChatPanel not configured (AC-026)', () => {
  it('shows the not-configured state without a composer', async () => {
    renderWithProviders(<Harness config={buildAiConfig({ configured: false, driver: null, model: null })} />)

    expect(await screen.findByText('Assistente non configurato')).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Invia' })).not.toBeInTheDocument()
  })
})

describe('AiChatPanel messages', () => {
  it('renders the saved messages with markdown, mention chips and a downloadable attachment', async () => {
    renderWithProviders(<Harness config={buildAiConfig()} />)

    expect(await screen.findByText('il riepilogo')).toHaveProperty('tagName', 'STRONG')
    expect(screen.getByText('Aziende: ACME Spa')).toBeInTheDocument()

    vi.mocked(downloadAiAttachment).mockResolvedValue(new Blob(['x']))
    fireEvent.click(screen.getByRole('button', { name: 'Scarica offerta.pdf' }))

    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(1))
    expect(downloadAiAttachment).toHaveBeenCalledWith(1, 9)
    expect(saveBlob.mock.calls[0][1]).toBe('offerta.pdf')
  })

  it('shows the empty state with suggestions that fill the draft', async () => {
    vi.mocked(fetchAiConversation).mockResolvedValue({ ...DETAIL, messages: [] })
    renderWithProviders(<Harness config={buildAiConfig()} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Come si usa questa pagina?' }))

    expect(screen.getByRole('textbox', { name: 'Scrivi un messaggio' })).toHaveValue('Come si usa questa pagina?')
  })
})

describe('turn resumed from the conversation detail (spec 0433 D-15)', () => {
  const RUNNING_TURN: AiTurn = {
    id: 8, conversation_id: 3, status: 'running', user_message_id: 1, assistant_text: 'Sto leggendo', assistant_message: null,
    steps: [{ index: 0, tool: 'list_records', label: 'Leggo le commesse', state: 'running' }],
    preview: null, queue_position: null, error_code: null, error_message: null,
    created_at: 'now', updated_at: 'now', finished_at: null,
  }

  it('shows the running steps and partial text, then the saved reply once, when the turn completes', async () => {
    const reply = message({ id: 9, role: 'assistant', content: 'Ecco tutte le commesse' })
    let finished = false
    vi.mocked(fetchAiConversation).mockImplementation(async () =>
      finished
        ? { ...DETAIL, messages: [...DETAIL.messages, reply], active_turn: null }
        : { ...DETAIL, active_turn: RUNNING_TURN },
    )
    vi.mocked(fetchAiTurn).mockImplementation(async () =>
      finished
        ? { ...RUNNING_TURN, status: 'completed', assistant_text: reply.content, assistant_message: reply }
        : RUNNING_TURN,
    )
    renderWithProviders(<Harness config={buildAiConfig()} />)

    expect(await screen.findByText('Leggo le commesse')).toBeInTheDocument()
    expect(screen.getByText('Sto leggendo')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Invia' })).toBeDisabled()

    finished = true
    expect(await screen.findByText('Ecco tutte le commesse', {}, { timeout: 4000 })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Leggo le commesse')).not.toBeInTheDocument())
    expect(screen.getAllByText('Ecco tutte le commesse')).toHaveLength(1)
  })
})

describe('preview of a turn (spec 0433, D-3)', () => {
  const PREVIEW: AiPreview = {
    id: 'p1', confirmable: true, content: 'Commessa 7: da Aperta a Chiusa',
    expires_at: new Date(Date.now() + 600_000).toISOString(), state: 'open', outcome: null,
  }
  const TURN: AiTurn = {
    id: 8, conversation_id: 3, status: 'awaiting_confirmation', user_message_id: 1, assistant_text: '', assistant_message: null,
    steps: [], preview: PREVIEW, queue_position: null, error_code: null, error_message: null,
    created_at: 'now', updated_at: 'now', finished_at: null,
  }

  it('says why the send button is off while a preview is open', async () => {
    vi.mocked(fetchAiConversation).mockResolvedValue({ ...DETAIL, active_turn: TURN })
    vi.mocked(fetchAiTurn).mockResolvedValue(TURN)
    renderWithProviders(<Harness config={buildAiConfig()} />)

    expect(await screen.findByText('Conferma o annulla l\'anteprima per continuare.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Invia' })).toBeDisabled()
  })

  it('keeps the card, closed with its outcome and no active button, after the reply is saved', async () => {
    const reply = message({ id: 9, role: 'assistant', content: 'Ho chiuso la commessa 7' })
    const done: AiTurn = {
      ...TURN, status: 'completed', assistant_text: reply.content, assistant_message: reply,
      preview: { ...PREVIEW, state: 'confirmed', outcome: 'succeeded' },
    }
    vi.mocked(fetchAiConversation).mockResolvedValue({ ...DETAIL, messages: [...DETAIL.messages, reply], active_turn: done })
    vi.mocked(fetchAiTurn).mockResolvedValue(done)
    renderWithProviders(<Harness config={buildAiConfig()} />)

    const card = await screen.findByRole('group', { name: 'Modifica da confermare' })
    expect(card).toHaveTextContent('Commessa 7: da Aperta a Chiusa')
    expect(card).toHaveTextContent('Confermata: la modifica è stata scritta.')
    expect(screen.queryByRole('button', { name: 'Conferma' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Annulla' })).not.toBeInTheDocument()
    expect(screen.getAllByText('Ho chiuso la commessa 7')).toHaveLength(1)
    expect(screen.queryByText('Conferma o annulla l\'anteprima per continuare.')).not.toBeInTheDocument()
  })
})

describe('guide forwarded to the assistant (AC-024)', () => {
  it('the full page pins the general guide', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    renderWithProviders(<AiAssistantPageView />, '/ai-assistant')

    await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })

    expect(useAiPageGuide).toHaveBeenCalledWith(40000, true)
  })

  it('the widget follows the guide of the current page', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    renderWithProviders(<AiDockWindow minimized={false} onToggleMinimized={vi.fn()} onClose={vi.fn()} />, '/companies')

    await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })

    expect(useAiPageGuide).toHaveBeenCalledWith(40000, false)
    expect(useAiPageGuide).not.toHaveBeenCalledWith(40000, true)
  })
})

describe('conversation list', () => {
  it('deletes a conversation only after confirmation', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    vi.mocked(deleteAiConversation).mockResolvedValue(undefined)
    renderWithProviders(<AiAssistantPageView />, '/ai-assistant')

    fireEvent.click(await screen.findByRole('button', { name: 'Elimina la conversazione: Preventivo ACME' }))
    expect(deleteAiConversation).not.toHaveBeenCalled()

    fireEvent.click(await screen.findByRole('button', { name: 'Elimina' }))

    await waitFor(() => expect(deleteAiConversation).toHaveBeenCalledTimes(1))
    expect(vi.mocked(deleteAiConversation).mock.calls[0][0]).toBe(3)
  })
})
