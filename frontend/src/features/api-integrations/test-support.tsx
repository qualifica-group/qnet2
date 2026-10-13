import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ApiClient } from '@/features/api-integrations/types'

export function buildClient(overrides: Partial<ApiClient> = {}): ApiClient {
  return {
    id: 5,
    name: 'ERP',
    description: null,
    rate_limit_per_minute: null,
    effective_rate_limit_per_minute: 60,
    expires_at: null,
    is_active: true,
    is_expired: false,
    key_last_four: 'wxyz',
    last_used_at: null,
    service_user: { id: 9, name: 'API · ERP' },
    created_by: { id: 1, name: 'Admin' },
    created_at: '2026-10-01T10:00:00Z',
    updated_at: '2026-10-01T10:00:00Z',
    ...overrides,
  }
}

/** One QueryClient per test, exposed so assertions can inspect its caches. */
export function createWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
  return { client, Wrapper }
}
