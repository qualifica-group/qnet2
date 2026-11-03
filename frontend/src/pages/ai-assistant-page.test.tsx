import { beforeEach, describe, expect, it, vi } from 'vitest'
import { screen } from '@testing-library/react'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import AiAssistantPage from '@/pages/ai-assistant-page'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, isLoading: false }),
}))
vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  deleteAiConversation: vi.fn(),
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: () => ({ locale: 'it', guideKey: 'general', guideText: null }),
}))

import { fetchAiAssistantConfig, fetchAiConversations } from '@/features/ai-assistant/api'

beforeEach(() => {
  vi.mocked(fetchAiConversations).mockResolvedValue({
    items: [],
    pagination: { total: 0, offset: 0, limit: 30, total_pages: 0 },
  })
})

describe('AiAssistantPage surfaces (AC-034 FE, D-16)', () => {
  it('shows the chat while the page is on', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
    renderWithProviders(<AiAssistantPage />, '/ai-assistant')

    expect(await screen.findByRole('heading', { name: 'Assistente AI' })).toBeInTheDocument()
    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
  })

  it('shows the disabled notice and no chat while the page is off', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig({ page_enabled: false }))
    renderWithProviders(<AiAssistantPage />, '/ai-assistant')

    expect(await screen.findByText("La pagina dell'assistente AI non è attiva in questa installazione.")).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: 'Scrivi un messaggio' })).not.toBeInTheDocument()
  })
})
