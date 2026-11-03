import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import { useState } from 'react'
import { AiAssistantPageView } from '@/features/ai-assistant/components/ai-assistant-page-view'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import FloatingAiPanel from '@/features/ai-assistant/components/floating-ai-panel'
import type { AiAssistantConfig, AiConversationDetail, AiMessage } from '@/features/ai-assistant/types'

vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
  createAiConversation: vi.fn(),
  deleteAiConversation: vi.fn(),
  downloadAiAttachment: vi.fn(),
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

describe('guide forwarded to the assistant (AC-024)', () => {
  it('the full page pins the general guide', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    renderWithProviders(<AiAssistantPageView />, '/ai-assistant')

    await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })

    expect(useAiPageGuide).toHaveBeenCalledWith(40000, true)
  })

  it('the widget follows the guide of the current page', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    renderWithProviders(<FloatingAiPanel open onClose={vi.fn()} />, '/companies')

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
