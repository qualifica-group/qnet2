import { afterEach, describe, expect, it, vi } from 'vitest'
import { useContext } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { tokenStorage } from '@/api/token-storage'
import { fetchMe, logout as logoutRequest } from '@/features/auth/api'
import { AuthContext } from '@/features/auth/auth-context'
import { AuthProvider } from '@/features/auth/auth-provider'

vi.mock('@/features/auth/api', () => ({
  fetchMe: vi.fn(),
  fetchImpersonation: vi.fn().mockResolvedValue({ impersonating: false }),
  impersonateUser: vi.fn(),
  stopImpersonation: vi.fn(),
  login: vi.fn(),
  logout: vi.fn().mockResolvedValue(undefined),
}))

function AuthProbe() {
  const auth = useContext(AuthContext)
  const state = auth?.isInitializing ? 'initializing' : auth?.hasInitTimedOut ? 'timed-out' : 'settled'
  return (
    <>
      <output>{state}</output>
      <button type="button" onClick={auth?.retryInit}>
        retry
      </button>
    </>
  )
}

function renderProvider() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <AuthProbe />
      </AuthProvider>
    </QueryClientProvider>,
  )
}

afterEach(() => {
  tokenStorage.clear()
  vi.clearAllMocks()
})

describe('AuthProvider when `me` times out', () => {
  it('keeps the session and reports the timeout instead of logging out', async () => {
    tokenStorage.set('token')
    vi.mocked(fetchMe).mockRejectedValue(new AxiosError('timeout', 'ECONNABORTED'))

    renderProvider()

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('timed-out'))
    expect(logoutRequest).not.toHaveBeenCalled()
    expect(tokenStorage.get()).toBe('token')
  })

  it('validates the session again on retry', async () => {
    tokenStorage.set('token')
    vi.mocked(fetchMe)
      .mockRejectedValueOnce(new AxiosError('timeout', 'ECONNABORTED'))
      .mockResolvedValueOnce({ id: 1, name: 'Ada', locale: 'en' } as Awaited<ReturnType<typeof fetchMe>>)

    renderProvider()
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('timed-out'))

    fireEvent.click(screen.getByRole('button', { name: 'retry' }))

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('settled'))
    expect(fetchMe).toHaveBeenCalledTimes(2)
    expect(tokenStorage.get()).toBe('token')
  })
})
