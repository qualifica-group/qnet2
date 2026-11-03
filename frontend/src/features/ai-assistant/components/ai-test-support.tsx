import type { ReactElement } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { AiAssistantConfig } from '@/features/ai-assistant/types'

/** Test-only helpers shared by the component tests of the assistant (not imported by production code). */
export function buildAiConfig(overrides: Partial<AiAssistantConfig> = {}): AiAssistantConfig {
  return {
    configured: true,
    driver: 'openai',
    model: 'gpt-test',
    limits: {
      max_message_chars: 8000,
      guide_max_chars: 40000,
      max_mentions: 2,
      max_files: 2,
      max_file_size_kb: 1024,
      accepted_mimes: ['application/pdf', 'image/png'],
    },
    mention_modules: ['companies', 'tasks'],
    ...overrides,
  }
}

export function renderWithProviders(ui: ReactElement, route = '/dashboard') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}
