import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ModuleOpenModeForm } from '@/features/modules/module-open-mode-form'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { User } from '@/features/auth/types'

/**
 * AC-015: the dedicated settings section saves the module open-mode preference
 * on its own — a partial PATCH /auth/me carrying only `module_open_preferences`
 * — and primes the `['auth','me']` cache so the app applies it without reload.
 */

const updateProfileMock = vi.fn()
vi.mock('@/features/auth/api', () => ({
  updateProfile: (...args: unknown[]) => updateProfileMock(...args),
}))

const currentUser = vi.fn<() => User | undefined>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function user(overrides: Partial<User> = {}): User {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    personal_data: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProfileMock.mockReset()
  currentUser.mockReset()
  currentUser.mockReturnValue(user())
  updateProfileMock.mockResolvedValue(user())
})

describe('ModuleOpenModeForm', () => {
  it('renders the section control seeded from the current preference', () => {
    render(<ModuleOpenModeForm />, { wrapper: wrapper() })

    expect(screen.getByRole('heading', { name: 'Module open mode' })).toBeInTheDocument()
  })

  it('AC-015: saves only module_open_preferences via a partial PATCH /auth/me', async () => {
    render(<ModuleOpenModeForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(updateProfileMock).toHaveBeenCalledTimes(1))

    const payload = updateProfileMock.mock.calls[0][0]
    expect(payload).toEqual({ module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES })
    // Never touches locale or personal_data from this section.
    expect(payload.locale).toBeUndefined()
    expect(payload.personal_data).toBeUndefined()
  })

  it('restores the initial defaults in one click', async () => {
    currentUser.mockReturnValue(
      user({ module_open_preferences: { mode: 'page', overrides: { projects: 'modal' } } }),
    )

    render(<ModuleOpenModeForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Restore defaults' }))

    await waitFor(() => expect(updateProfileMock).toHaveBeenCalledTimes(1))
    expect(updateProfileMock.mock.calls[0][0]).toEqual({
      module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    })
  })
})
