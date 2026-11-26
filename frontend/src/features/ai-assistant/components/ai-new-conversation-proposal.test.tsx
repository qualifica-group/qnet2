import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen } from '@testing-library/react'
import { useState } from 'react'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import type { AiConversationDetail } from '@/features/ai-assistant/types'

vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiTurn: vi.fn(),
  sendAiMessage: vi.fn(),
  createAiConversation: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: vi.fn(() => ({ locale: 'it', guideKey: 'general', guideText: 'GUIDE' })),
}))

import { fetchAiAssistantConfig, fetchAiConversation } from '@/features/ai-assistant/api'

function detail(propose: boolean): AiConversationDetail {
  return {
    id: 3,
    title: 'Lunga',
    last_message_at: '2026-11-01T10:00:00Z',
    created_at: '2026-11-01T09:00:00Z',
    messages: [
      {
        id: 1,
        role: 'user',
        content: 'Ciao',
        mentions: [],
        attachments: [],
        model: null,
        input_tokens: null,
        output_tokens: null,
        created_at: '2026-11-02T10:00:00Z',
      },
    ],
    propose_new_conversation: propose,
  }
}

function Harness() {
  const [id, setId] = useState<number | null>(3)
  return (
    <>
      <span data-testid="selected">{String(id)}</span>
      <AiChatPanel variant="widget" conversationId={id} onConversationChange={setId} />
    </>
  )
}

beforeEach(() => {
  vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
})

describe('AiChatPanel new conversation proposal (D-5)', () => {
  it('offers a button that opens a new, empty conversation once the server proposes it', async () => {
    vi.mocked(fetchAiConversation).mockResolvedValue(detail(true))
    renderWithProviders(<Harness />)

    expect(await screen.findByText(/è diventata lunga/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Apri una nuova conversazione' }))

    expect(screen.getByTestId('selected')).toHaveTextContent('null')
    expect(screen.queryByText(/è diventata lunga/)).not.toBeInTheDocument()
  })

  it('shows nothing when the conversation is not long', async () => {
    vi.mocked(fetchAiConversation).mockResolvedValue(detail(false))
    renderWithProviders(<Harness />)

    expect(await screen.findByText('Ciao')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Apri una nuova conversazione' })).not.toBeInTheDocument()
  })
})
