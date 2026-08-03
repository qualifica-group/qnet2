import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DateFormatForm } from '@/features/appearance/date-format-form'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import { authKeys } from '@/features/auth/query-keys'
import type { User } from '@/features/auth/types'

/**
 * The settings section saves the date/time display preferences on its own — a
 * partial PATCH /auth/me carrying only those two keys — and primes the
 * ['auth','me'] cache so DateDisplayProvider re-renders the app with the new
 * patterns without a reload.
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

let queryClient: QueryClient

function wrapper() {
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProfileMock.mockReset()
  currentUser.mockReset()
  currentUser.mockReturnValue(user())
})

describe('DateFormatForm', () => {
  it('preselects the saved preferences', () => {
    currentUser.mockReturnValue(user({ date_format: 'ymd', time_format: '12h' }))
    render(<DateFormatForm />, { wrapper: wrapper() })

    expect(screen.getByRole('combobox', { name: 'Date' })).toHaveTextContent('Year-Month-Day (ISO)')
    expect(screen.getByRole('combobox', { name: 'Time' })).toHaveTextContent('12 hours (2:30 PM)')
  })

  it('falls back to the Italian day-first default when the value is unknown', () => {
    currentUser.mockReturnValue(user({ date_format: 'nope' as never }))
    render(<DateFormatForm />, { wrapper: wrapper() })

    expect(screen.getByRole('combobox', { name: 'Date' })).toHaveTextContent('Day/Month/Year')
  })

  it('previews the pending selection before it is saved', () => {
    render(<DateFormatForm />, { wrapper: wrapper() })

    expect(screen.getByText('Preview: 03/08/2026 14:30')).toBeInTheDocument()
  })

  it('keeps Save disabled until something actually changes', async () => {
    currentUser.mockReturnValue(user({ date_format: 'dmy', time_format: '24h' }))
    render(<DateFormatForm />, { wrapper: wrapper() })

    const save = screen.getByRole('button', { name: 'Save changes' })
    expect(save).toBeDisabled()

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Date' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('option', { name: /Year-Month-Day/ }))

    await waitFor(() => expect(save).toBeEnabled())
  })

  it('saves both preferences in one partial PATCH and primes the me cache', async () => {
    const updated = user({ date_format: 'ymd', time_format: '24h' })
    updateProfileMock.mockResolvedValue(updated)
    render(<DateFormatForm />, { wrapper: wrapper() })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Date' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('option', { name: /Year-Month-Day/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() =>
      expect(updateProfileMock).toHaveBeenCalledWith({ date_format: 'ymd', time_format: '24h' }),
    )
    await waitFor(() => expect(queryClient.getQueryData(authKeys.me)).toEqual(updated))
  })

  it('surfaces a failed save instead of pretending it worked', async () => {
    updateProfileMock.mockRejectedValue(new Error('network'))
    render(<DateFormatForm />, { wrapper: wrapper() })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Time' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('option', { name: /12 hours/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Something went wrong. Please try again.',
    )
  })
})
