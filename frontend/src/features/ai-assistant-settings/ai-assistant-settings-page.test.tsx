import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'
import AiAssistantSettingsPage from '@/features/ai-assistant-settings/ai-assistant-settings-page'
import type { AiAssistantSettings, AiModelsPage } from '@/features/ai-assistant-settings/types'

/** Spec 0433: the super-admin page. The API module is mocked: the browser never reaches OpenRouter. */

const fetchSettingsMock = vi.fn()
const updateSettingsMock = vi.fn()
const fetchModelsMock = vi.fn()
const fetchProvidersMock = vi.fn()
const hasRoleMock = vi.fn()

vi.mock('@/features/ai-assistant-settings/api', () => ({
  fetchAiAssistantSettings: () => fetchSettingsMock(),
  updateAiAssistantSettings: (payload: unknown) => updateSettingsMock(payload),
  fetchAiModels: (search: string, limit: number) => fetchModelsMock(search, limit),
  fetchAiModelProviders: (modelId: string) => fetchProvidersMock(modelId),
}))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: (role: string) => hasRoleMock(role), roles: [], isLoading: false }),
}))

const SECRET = 'sk-or-must-never-appear'

const settings: AiAssistantSettings = {
  enabled: true,
  model_id: 'deepseek/deepseek-v4-flash-0731',
  instructions: '## Ruolo\nAiuta.',
  instructions_max_chars: 8000,
  spend_cap_person_usd: null,
  spend_cap_total_usd: '120.00',
  routing: { zdr: true, data_collection: 'deny', require_parameters: true, sort: 'throughput' },
  openrouter_key_present: true,
  updated_at: null,
  updated_by: null,
}

const models: AiModelsPage = {
  items: [
    {
      id: 'acme/text-pro',
      name: 'Acme Text Pro',
      context_length: 131072,
      pricing: { prompt: '0.14', completion: '0.28', input_cache_read: null, request: null },
      selected: false,
    },
    {
      id: 'deepseek/deepseek-v4-flash-0731',
      name: 'DeepSeek V4 Flash',
      context_length: 1048576,
      pricing: { prompt: '0.07', completion: '0.14', input_cache_read: '0.014', request: null },
      selected: true,
    },
  ],
  pagination: { total: 2, offset: 0, limit: 25 },
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/dev/ai-assistant-settings']}>
        <Routes>
          <Route element={<MigrationRouteGuard />}>
            <Route path="/dev/ai-assistant-settings" element={children} />
          </Route>
          <Route path="/dashboard" element={<p>dashboard</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('AiAssistantSettingsPage', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en')
    vi.clearAllMocks()
    hasRoleMock.mockReturnValue(true)
    fetchSettingsMock.mockResolvedValue(settings)
    fetchModelsMock.mockResolvedValue(models)
    fetchProvidersMock.mockResolvedValue([
      {
        name: 'Fast',
        tag: 'fast',
        zdr: true,
        uptime_pct: 99.5,
        throughput_tps: 193.5,
        latency_ms: null,
        pricing: { prompt: '0.07', completion: '0.14', input_cache_read: '0.014' },
      },
    ])
    updateSettingsMock.mockImplementation(async (payload: object) => ({ ...settings, ...payload }))
  })

  it('redirects a user without the super-admin role and calls no endpoint', async () => {
    hasRoleMock.mockReturnValue(false)

    render(<AiAssistantSettingsPage />, { wrapper })

    expect(await screen.findByText('dashboard')).toBeInTheDocument()
    expect(fetchSettingsMock).not.toHaveBeenCalled()
  })

  it('shows the settings, the key presence only, the models with prices and the providers', async () => {
    const { container } = render(<AiAssistantSettingsPage />, { wrapper })

    expect(await screen.findByText('Present')).toBeInTheDocument()
    expect(await screen.findByText('Acme Text Pro')).toBeInTheDocument()
    expect(screen.getAllByText(/\$0\.14/).length).toBeGreaterThan(0)
    expect(await screen.findByText('Fast')).toBeInTheDocument()
    expect(screen.getByText('193.5 tokens/s')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Instructions' })).toHaveValue('## Ruolo\nAiuta.')
    expect(container.textContent).not.toContain(SECRET)
  })

  it('saves the chosen model and the edited instructions, with empty caps sent as null', async () => {
    render(<AiAssistantSettingsPage />, { wrapper })
    fireEvent.click(await screen.findByRole('radio', { name: /Acme Text Pro/ }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Instructions' }), { target: { value: 'Breve.' } })

    fireEvent.click(screen.getByRole('button', { name: 'Save settings' }))

    await waitFor(() => expect(updateSettingsMock).toHaveBeenCalledTimes(1))
    expect(updateSettingsMock.mock.calls[0][0]).toEqual({
      enabled: true,
      model_id: 'acme/text-pro',
      instructions: 'Breve.',
      spend_cap_person_usd: null,
      spend_cap_total_usd: '120.00',
      routing: settings.routing,
    })
  })

  it('blocks a save with instructions over the limit and sends nothing', async () => {
    fetchSettingsMock.mockResolvedValue({ ...settings, instructions_max_chars: 5 })
    render(<AiAssistantSettingsPage />, { wrapper })
    await screen.findByText('Acme Text Pro')

    fireEvent.click(screen.getByRole('button', { name: 'Save settings' }))

    expect(await screen.findByText('The instructions can have at most 5 characters.')).toBeInTheDocument()
    expect(updateSettingsMock).not.toHaveBeenCalled()
  })

  it('tells the model list is unavailable without hiding the rest of the page', async () => {
    fetchModelsMock.mockRejectedValue(new Error('503'))

    render(<AiAssistantSettingsPage />, { wrapper })

    expect(await screen.findByText('The model list is not available. Try again later.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Save settings' })).toBeInTheDocument()
  })
})
