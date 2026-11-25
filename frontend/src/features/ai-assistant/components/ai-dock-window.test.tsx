import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import AiDockWindow from '@/features/ai-assistant/components/ai-dock-window'
import { useAiDockEntry } from '@/features/ai-assistant/hooks/use-ai-dock-entry'

/**
 * The assistant as a conversation of the chat dock (spec 0432). REQUIREMENT
 * CHANGED: it replaces the floating launcher (`FloatingAiAssistant`), keeping
 * its rules (AC-019 permission, D-16 floating switch, hidden on the page).
 */

const abilities = vi.hoisted(() => ({ granted: true }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => abilities.granted, isLoading: false }),
}))
vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
  deleteAiConversation: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: () => ({ locale: 'it', guideKey: null, guideText: null }),
}))

import { fetchAiAssistantConfig, fetchAiConversations } from '@/features/ai-assistant/api'

beforeEach(() => {
  vi.clearAllMocks()
  abilities.granted = true
  vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig())
  vi.mocked(fetchAiConversations).mockResolvedValue({ data: [], meta: { has_more: false, next_cursor: null } } as never)
})

function renderEntry(route: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[route]}>{children}</MemoryRouter>
    </QueryClientProvider>
  )
  return renderHook(() => useAiDockEntry(), { wrapper })
}

describe('useAiDockEntry (AC-019, D-16)', () => {
  it('lists the assistant with ai-assistant.use while the server keeps the floating chat on', async () => {
    const { result } = renderEntry('/companies')

    await waitFor(() => expect(result.current).toBe(true))
  })

  it('does not list it, nor ask the config, without the permission', () => {
    abilities.granted = false
    const { result } = renderEntry('/companies')

    expect(result.current).toBe(false)
    expect(fetchAiAssistantConfig).not.toHaveBeenCalled()
  })

  it('does not list it while the server keeps the floating chat off', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig({ floating_enabled: false }))
    const { result } = renderEntry('/companies')

    await waitFor(() => expect(fetchAiAssistantConfig).toHaveBeenCalled())
    expect(result.current).toBe(false)
  })

  it.each(['/ai-assistant', '/app/hr/ai-assistant'])('does not list it on the assistant page %s', async (route) => {
    const { result } = renderEntry(route)

    await waitFor(() => expect(fetchAiAssistantConfig).toHaveBeenCalled())
    expect(result.current).toBe(false)
  })
})

/** The dock's minimize state, owned by the dock in the app. */
function MinimizableWindow() {
  const [minimized, setMinimized] = useState(false)
  return <AiDockWindow minimized={minimized} onToggleMinimized={() => setMinimized((current) => !current)} onClose={vi.fn()} />
}

describe('AiDockWindow', () => {
  it('opens the chat with the full-page link', async () => {
    renderWithProviders(<AiDockWindow minimized={false} onToggleMinimized={vi.fn()} onClose={vi.fn()} />, '/companies')

    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Apri a pagina intera' })).toHaveAttribute('href', '/ai-assistant')
  })

  it('drops the full-page link while the page is off', async () => {
    vi.mocked(fetchAiAssistantConfig).mockResolvedValue(buildAiConfig({ page_enabled: false }))
    renderWithProviders(<AiDockWindow minimized={false} onToggleMinimized={vi.fn()} onClose={vi.fn()} />, '/companies')

    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Apri a pagina intera' })).not.toBeInTheDocument()
  })

  it('keeps the chat mounted while minimized, so an answer and the draft survive', async () => {
    renderWithProviders(<MinimizableWindow />, '/companies')
    fireEvent.change(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' }), { target: { value: 'bozza' } })

    fireEvent.click(screen.getByRole('button', { name: 'Riduci' }))

    expect(screen.getByRole('button', { name: 'Apri' })).toBeInTheDocument()
    expect(screen.getByDisplayValue('bozza')).toBeInTheDocument()
  })

  it('switches to the conversation history and back', async () => {
    renderWithProviders(<AiDockWindow minimized={false} onToggleMinimized={vi.fn()} onClose={vi.fn()} />, '/companies')
    await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })

    fireEvent.click(screen.getByRole('button', { name: 'Cronologia' }))
    expect(screen.queryByRole('textbox', { name: 'Scrivi un messaggio' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Torna alla chat' }))
    expect(await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })).toBeInTheDocument()
  })
})
