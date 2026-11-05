import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import { FloatingAiAssistant } from '@/features/ai-assistant/components/floating-ai-assistant'

const abilities = vi.hoisted(() => ({ granted: true }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => abilities.granted, isLoading: false }),
}))
vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: () => ({ locale: 'it', guideKey: null, guideText: null }),
}))

import { fetchAiAssistantConfig } from '@/features/ai-assistant/api'

beforeEach(() => {
  abilities.granted = true
  vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
})

// REQUIREMENT CHANGED (D-16): the launcher waits for /config (floating_enabled), so it is found asynchronously.
describe('FloatingAiAssistant (AC-019 FE)', () => {
  it('shows the launcher to a user with ai-assistant.use and opens the chat window', async () => {
    renderWithProviders(<FloatingAiAssistant />, '/companies')

    fireEvent.click(await screen.findByRole('button', { name: "Apri l'assistente AI" }))

    expect(await screen.findByRole('complementary', { name: 'Assistente AI' })).toBeInTheDocument()
    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Apri a pagina intera' })).toHaveAttribute('href', '/ai-assistant')
  })

  it('renders nothing without the permission', () => {
    abilities.granted = false
    renderWithProviders(<FloatingAiAssistant />, '/companies')

    expect(screen.queryByRole('button', { name: "Apri l'assistente AI" })).not.toBeInTheDocument()
  })

  it.each(['/ai-assistant', '/app/hr/ai-assistant'])('is hidden on the assistant page %s', (route) => {
    renderWithProviders(<FloatingAiAssistant />, route)

    expect(screen.queryByRole('button', { name: "Apri l'assistente AI" })).not.toBeInTheDocument()
  })

  it('keeps the window mounted but hidden when closed', async () => {
    renderWithProviders(<FloatingAiAssistant />, '/companies')
    fireEvent.click(await screen.findByRole('button', { name: "Apri l'assistente AI" }))
    await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })

    fireEvent.click(screen.getAllByRole('button', { name: "Chiudi l'assistente AI" })[0])

    // Tailwind is not loaded in jsdom: the closed state is the `hidden` utility, the node stays mounted.
    expect(screen.getByRole('complementary', { name: 'Assistente AI' })).toHaveClass('hidden')
    expect(screen.getByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
  })
})

describe('FloatingAiAssistant surfaces (AC-034 FE, D-16)', () => {
  it('renders no launcher while the server keeps the floating chat off', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig({ floating_enabled: false }))
    renderWithProviders(<FloatingAiAssistant />, '/companies')

    await waitFor(() => expect(fetchAiAssistantConfig).toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: "Apri l'assistente AI" })).not.toBeInTheDocument()
  })

  it('drops the full-page link while the page is off', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig({ page_enabled: false }))
    renderWithProviders(<FloatingAiAssistant />, '/companies')

    fireEvent.click(await screen.findByRole('button', { name: "Apri l'assistente AI" }))

    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Apri a pagina intera' })).not.toBeInTheDocument()
  })
})
