import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useImpersonationActions } from '@/features/auth/use-impersonation-actions'
import { authKeys } from '@/features/auth/query-keys'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { LoginResult, User } from '@/features/auth/types'

const impersonateUserMock = vi.fn<(userId: number) => Promise<LoginResult>>()
const stopImpersonationMock = vi.fn<() => Promise<LoginResult>>()
const fetchImpersonationMock = vi.fn()

// TanStack Query v5 calls mutationFn(variables, context) and
// queryFn(context); only the first (the real API surface) matters here.
vi.mock('@/features/auth/api', () => ({
  impersonateUser: (userId: number) => impersonateUserMock(userId),
  stopImpersonation: () => stopImpersonationMock(),
  fetchImpersonation: () => fetchImpersonationMock(),
}))

beforeEach(() => {
  impersonateUserMock.mockClear()
  stopImpersonationMock.mockClear()
  fetchImpersonationMock.mockClear()
})

// Session identifiers used below are opaque test fixtures, not real tokens.
const targetSession = 'targetsession'
const actorSession = 'actorsession'
const freshActorSession = 'freshsession'

function targetUser(): User {
  return {
    id: 2,
    name: 'Target User',
    email: 'target@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
  }
}

function wrapper(client: QueryClient) {
  return function QueryWrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

describe('useImpersonationActions', () => {
  it('exposes the original actor from GET /auth/impersonation', async () => {
    fetchImpersonationMock.mockResolvedValue({
      impersonator: { id: 1, name: 'Original Actor', email: 'actor@example.com' },
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    const { result } = renderHook(
      () => useImpersonationActions({ token: actorSession, setToken: vi.fn() }),
      { wrapper: wrapper(client) },
    )

    await waitFor(() =>
      expect(result.current.impersonator).toEqual({
        id: 1,
        name: 'Original Actor',
        email: 'actor@example.com',
      }),
    )
  })

  it('does not query impersonation state without a token', () => {
    fetchImpersonationMock.mockResolvedValue({ impersonator: null })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    renderHook(() => useImpersonationActions({ token: null, setToken: vi.fn() }), {
      wrapper: wrapper(client),
    })

    expect(fetchImpersonationMock).not.toHaveBeenCalled()
  })

  it('start: swaps the token, clears the cache and repopulates `me` with the target', async () => {
    fetchImpersonationMock.mockResolvedValue({ impersonator: null })
    impersonateUserMock.mockResolvedValue({
      token: targetSession,
      token_type: 'Bearer',
      user: targetUser(),
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    client.setQueryData(['stale', 'from-previous-identity'], { leftover: true })
    const clearSpy = vi.spyOn(client, 'clear')
    const setToken = vi.fn()

    const { result } = renderHook(
      () => useImpersonationActions({ token: actorSession, setToken }),
      { wrapper: wrapper(client) },
    )

    await result.current.impersonate(2)

    expect(impersonateUserMock).toHaveBeenCalledWith(2)
    expect(setToken).toHaveBeenCalledWith(targetSession)
    expect(clearSpy).toHaveBeenCalledTimes(1)
    expect(client.getQueryData(authKeys.me)).toEqual(targetUser())
    expect(client.getQueryData(['stale', 'from-previous-identity'])).toBeUndefined()
  })

  it('stop: swaps the token back, clears the cache and repopulates `me` with the original actor', async () => {
    fetchImpersonationMock.mockResolvedValue({ impersonator: null })
    const original = { ...targetUser(), id: 1, name: 'Original Actor' }
    stopImpersonationMock.mockResolvedValue({
      token: freshActorSession,
      token_type: 'Bearer',
      user: original,
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const clearSpy = vi.spyOn(client, 'clear')
    const setToken = vi.fn()

    const { result } = renderHook(
      () => useImpersonationActions({ token: targetSession, setToken }),
      { wrapper: wrapper(client) },
    )

    await result.current.stopImpersonation()

    expect(stopImpersonationMock).toHaveBeenCalledTimes(1)
    expect(setToken).toHaveBeenCalledWith(freshActorSession)
    expect(clearSpy).toHaveBeenCalledTimes(1)
    expect(client.getQueryData(authKeys.me)).toEqual(original)
  })
})
