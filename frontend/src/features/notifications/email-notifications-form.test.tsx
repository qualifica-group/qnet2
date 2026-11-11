import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { EmailNotificationsForm } from '@/features/notifications/email-notifications-form'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import { authKeys } from '@/features/auth/query-keys'
import type { User } from '@/features/auth/types'

/**
 * The switch saves on flip — a partial PATCH /auth/me carrying only
 * `email_notifications_enabled` — and primes the ['auth','me'] cache.
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
    must_set_password: false,
    onboarding_status: 'completed',
    personal_data: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
    color_preset: 'default',
    email_notifications_enabled: true,
    ...overrides,
  }
}

let queryClient: QueryClient

function wrapper() {
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  }
}

const SWITCH_NAME = 'Also receive notifications by email'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProfileMock.mockReset()
  currentUser.mockReset()
  currentUser.mockReturnValue(user())
})

describe('EmailNotificationsForm', () => {
  it('reflects the saved preference', () => {
    currentUser.mockReturnValue(user({ email_notifications_enabled: false }))
    render(<EmailNotificationsForm />, { wrapper: wrapper() })

    expect(screen.getByRole('switch', { name: SWITCH_NAME })).not.toBeChecked()
  })

  it('turning it off sends only the switch and primes the auth cache', async () => {
    const saved = user({ email_notifications_enabled: false })
    updateProfileMock.mockResolvedValue(saved)
    render(<EmailNotificationsForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('switch', { name: SWITCH_NAME }))

    await waitFor(() =>
      expect(updateProfileMock).toHaveBeenCalledWith({ email_notifications_enabled: false }),
    )
    await waitFor(() => expect(queryClient.getQueryData(authKeys.me)).toEqual(saved))
  })

  it('shows an error and keeps the saved value when the request fails', async () => {
    updateProfileMock.mockRejectedValue(new Error('boom'))
    render(<EmailNotificationsForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('switch', { name: SWITCH_NAME }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong')
    expect(screen.getByRole('switch', { name: SWITCH_NAME })).toBeChecked()
  })
})
