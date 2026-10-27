import { describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import type { ReactNode } from 'react'
import { apiClient } from '@/api/client'
import { useNavigation } from '@/features/navigation/use-navigation'
import { createWrapper } from '@/features/workspaces/test-support'

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 7 }, isAuthenticated: true }),
}))
vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

function renderNavigation(path: string) {
  const { Wrapper, client } = createWrapper()
  vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [] } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[path]}>
      <Wrapper>{children}</Wrapper>
    </MemoryRouter>
  )
  return { ...renderHook(() => useNavigation(), { wrapper }), client }
}

describe('useNavigation (spec 0317 AC-013)', () => {
  it('asks for the menu of the app in the URL and caches it per slug', async () => {
    const { result, client } = renderNavigation('/app/hr/invoices')

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(apiClient.get).toHaveBeenCalledWith('/navigation', { params: { workspace: 'hr' } })
    expect(client.getQueryData(['navigation', 'hr'])).toEqual([])
  })

  it('asks for the full menu outside any app', async () => {
    const { result, client } = renderNavigation('/invoices')

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(apiClient.get).toHaveBeenLastCalledWith('/navigation', { params: undefined })
    expect(client.getQueryData(['navigation', null])).toEqual([])
  })
})
