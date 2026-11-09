import { afterEach, describe, expect, it, vi } from 'vitest'
import { useContext } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
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

function serviceUnavailable(data: object): AxiosError {
  const response = { status: 503, data } as AxiosResponse
  return new AxiosError('Unavailable', '503', undefined, undefined, response)
}

function AuthProbe() {
  const auth = useContext(AuthContext)
  return <output>{auth?.isInitializing ? 'initializing' : 'settled'}</output>
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

describe('AuthProvider during maintenance', () => {
  it('keeps the session when `me` answers the maintenance 503', async () => {
    tokenStorage.set('token')
    vi.mocked(fetchMe).mockRejectedValue(serviceUnavailable({ maintenance: true }))

    renderProvider()

    await waitFor(() => expect(fetchMe).toHaveBeenCalled())
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('initializing'))
    expect(logoutRequest).not.toHaveBeenCalled()
    expect(tokenStorage.get()).toBe('token')
  })

  it('still logs out on any other 503', async () => {
    tokenStorage.set('token')
    vi.mocked(fetchMe).mockRejectedValue(serviceUnavailable({}))

    renderProvider()

    await waitFor(() => expect(logoutRequest).toHaveBeenCalled())
  })
})
