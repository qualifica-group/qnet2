import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { apiClient } from '@/api/client'
import { tokenStorage } from '@/api/token-storage'
import { AuthProvider } from '@/features/auth/auth-provider'

/** Spec 0355 AC-014: a 403 `password_change_required` makes the app refresh the current user (`me`). */

const fetchMeMock = vi.fn()

vi.mock('@/features/auth/api', () => ({
  fetchMe: () => fetchMeMock(),
  login: vi.fn(),
  logout: vi.fn(),
}))

vi.mock('@/features/auth/use-impersonation-actions', () => ({
  useImpersonationActions: () => ({ impersonator: null, impersonate: vi.fn(), stopImpersonation: vi.fn() }),
}))

function forbiddenWith(reason: string) {
  return apiClient.get('/navigation', {
    adapter: async (config) => {
      throw new AxiosError('Forbidden', '403', config, undefined, {
        status: 403,
        statusText: 'Forbidden',
        headers: {},
        config,
        data: { success: false, message: 'x', authorization: { reason, permission: null } },
      })
    },
  })
}

function renderProvider() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <div />
      </AuthProvider>
    </QueryClientProvider>,
  )
}

describe('password_change_required interceptor', () => {
  beforeEach(() => {
    fetchMeMock.mockReset()
    fetchMeMock.mockResolvedValue({ id: 1, name: 'Ada', locale: 'en', must_set_password: false })
    tokenStorage.set('token')
  })

  it('invalidates the me query on a 403 password_change_required', async () => {
    renderProvider()
    await waitFor(() => expect(fetchMeMock).toHaveBeenCalledTimes(1))

    await forbiddenWith('password_change_required').catch(() => undefined)

    await waitFor(() => expect(fetchMeMock).toHaveBeenCalledTimes(2))
  })

  it('does not refresh me on any other 403', async () => {
    renderProvider()
    await waitFor(() => expect(fetchMeMock).toHaveBeenCalledTimes(1))

    await forbiddenWith('missing_permission').catch(() => undefined)
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(fetchMeMock).toHaveBeenCalledTimes(1)
  })
})
