import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { tokenStorage } from '@/api/token-storage'
import { ConfigGate } from '@/features/config/config-gate'
import { fetchConfig } from '@/features/config/api'
import { configKeys } from '@/features/config/query-keys'
import { AuthProvider } from '@/features/auth/auth-provider'

// This suite validates the config-first bootstrap *order guarantee* (ADR 0009)
// at integration level: it drives the real ConfigGate + real AuthProvider with
// the HTTP boundary (apiClient) mocked, so we can assert which endpoints fire
// in which boot state. It deliberately does NOT mock useConfig.
vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
  UNAUTHORIZED_EVENT: 'auth:unauthorized',
}))

const mockedGet = vi.mocked(apiClient.get)

/** Routes apiClient.get by URL so we can resolve/inspect each endpoint. */
function configResponse() {
  return { data: { data: { enums: {} } } }
}

function meResponse() {
  return { data: { data: { id: 1, name: 'Test', email: 't@e.st', locale: 'en' } } }
}

function meWasCalled() {
  return mockedGet.mock.calls.some(([url]) => url === '/auth/me')
}

function configCallCount() {
  return mockedGet.mock.calls.filter(([url]) => url === '/config').length
}

function newClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
}

function App({ client, children }: { client: QueryClient; children: ReactNode }) {
  return (
    <QueryClientProvider client={client}>
      <ConfigGate>
        <AuthProvider>{children}</AuthProvider>
      </ConfigGate>
    </QueryClientProvider>
  )
}

beforeEach(async () => {
  await i18n.changeLanguage('en')
  // A token is present, so AuthProvider's `me` query is `enabled`. If the gate
  // leaked, /auth/me would fire — which is exactly what we assert against.
  tokenStorage.set('valid-token')
  mockedGet.mockReset()
})

afterEach(() => {
  tokenStorage.clear()
})

describe('ConfigGate order guarantee (integration)', () => {
  it('does not fetch /auth/me while the config is still pending', async () => {
    // Never-resolving config keeps the gate in the pending state.
    mockedGet.mockReturnValue(new Promise(() => {}))

    render(
      <App client={newClient()}>
        <div>app-children</div>
      </App>,
    )

    // Boot splash is shown, children are withheld...
    expect(await screen.findByRole('status')).toBeInTheDocument()
    expect(screen.queryByText('app-children')).not.toBeInTheDocument()
    // ...and crucially the auth `me` fetch has NOT started.
    expect(meWasCalled()).toBe(false)
    expect(mockedGet).toHaveBeenCalledWith('/config')
  })

  it('does not fetch /auth/me when the config load errors (after retries)', async () => {
    // useConfig overrides the global retry with retry:3 + exponential backoff
    // (1s, 2s, 4s). Drive fake timers to exhaust the retries deterministically
    // instead of waiting ~7s of real time.
    vi.useFakeTimers()
    try {
      mockedGet.mockImplementation((url: string) => {
        if (url === '/config') return Promise.reject(new Error('boom'))
        return Promise.resolve(meResponse())
      })

      render(
        <App client={newClient()}>
          <div>app-children</div>
        </App>,
      )

      // Flush the initial attempt + the three backoff windows.
      await vi.advanceTimersByTimeAsync(8_000)

      expect(
        screen.getByText('Unable to start the application'),
      ).toBeInTheDocument()
      expect(screen.queryByText('app-children')).not.toBeInTheDocument()
      // Four /config attempts (1 initial + 3 retries), and never /auth/me.
      expect(configCallCount()).toBe(4)
      expect(meWasCalled()).toBe(false)
    } finally {
      vi.useRealTimers()
    }
  })

  it('mounts AuthProvider (and fires /auth/me) only after config success', async () => {
    mockedGet.mockImplementation((url: string) => {
      if (url === '/config') return Promise.resolve(configResponse())
      if (url === '/auth/me') return Promise.resolve(meResponse())
      return Promise.reject(new Error(`unexpected ${url}`))
    })

    render(
      <App client={newClient()}>
        <div>app-children</div>
      </App>,
    )

    // Children mount as soon as the config lands; the splash is still covering
    // them until its minimum duration elapses, which is a purely visual layer.
    expect(await screen.findByText('app-children')).toBeInTheDocument()
    // Now that children mounted, the auth query is allowed to run.
    await waitFor(() => expect(meWasCalled()).toBe(true))
  })

  it('issues a single /config request when prefetch and useConfig share the cache', async () => {
    mockedGet.mockImplementation((url: string) => {
      if (url === '/config') return Promise.resolve(configResponse())
      if (url === '/auth/me') return Promise.resolve(meResponse())
      return Promise.reject(new Error(`unexpected ${url}`))
    })

    const client = newClient()
    // Reproduce the main.tsx prefetch: same key + queryFn primes the shared cache.
    await client.ensureQueryData({
      queryKey: configKeys.all,
      queryFn: fetchConfig,
      staleTime: Infinity,
    })

    render(
      <App client={client}>
        <div>app-children</div>
      </App>,
    )

    // useConfig reads the primed cache; no second /config round-trip.
    expect(await screen.findByText('app-children')).toBeInTheDocument()
    expect(configCallCount()).toBe(1)
  })
})
