import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ColorPresetForm } from '@/features/appearance/color-preset-form'
import { COLOR_PRESETS } from '@/features/appearance/color-preset'
import { authKeys } from '@/features/auth/query-keys'
import type { User } from '@/features/auth/types'

/**
 * The settings section saves the accent palette on its own — a partial PATCH
 * /auth/me carrying only `color_preset` — and primes the ['auth','me'] cache so
 * ColorPresetProvider re-tints the app without a reload.
 */

const updateProfileMock = vi.fn()
vi.mock('@/features/auth/api', () => ({
  updateProfile: (...args: unknown[]) => updateProfileMock(...args),
}))

const currentUser = vi.fn<() => Partial<User> | undefined>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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
  currentUser.mockReturnValue({ id: 7, color_preset: 'default' })
})

describe('ColorPresetForm', () => {
  it('lists every preset, each card previewing its own palette', () => {
    render(<ColorPresetForm />, { wrapper: wrapper() })

    const radios = screen.getAllByRole('radio')
    expect(radios).toHaveLength(COLOR_PRESETS.length)
    expect(radios.map((radio) => radio.getAttribute('data-color-preset'))).toEqual([
      ...COLOR_PRESETS,
    ])
  })

  it('preselects the saved preset', () => {
    currentUser.mockReturnValue({ id: 7, color_preset: 'ocean' })
    render(<ColorPresetForm />, { wrapper: wrapper() })

    expect(screen.getByRole('radio', { name: 'Ocean breeze' })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: 'Classic blue' })).toHaveAttribute('aria-checked', 'false')
  })

  it('falls back to the default when the stored value is unknown', () => {
    currentUser.mockReturnValue({ id: 7, color_preset: 'neon' as never })
    render(<ColorPresetForm />, { wrapper: wrapper() })

    expect(screen.getByRole('radio', { name: 'Classic blue' })).toHaveAttribute('aria-checked', 'true')
  })

  it('keeps Save disabled until another preset is picked', () => {
    render(<ColorPresetForm />, { wrapper: wrapper() })

    const save = screen.getByRole('button', { name: 'Save changes' })
    expect(save).toBeDisabled()

    fireEvent.click(screen.getByRole('radio', { name: 'Forest green' }))

    expect(save).toBeEnabled()
  })

  it('saves only color_preset via a partial PATCH and primes the me cache', async () => {
    const updated = { id: 7, color_preset: 'plum' }
    updateProfileMock.mockResolvedValue(updated)
    render(<ColorPresetForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('radio', { name: 'Plum night' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(updateProfileMock).toHaveBeenCalledWith({ color_preset: 'plum' }))
    await waitFor(() => expect(queryClient.getQueryData(authKeys.me)).toEqual(updated))
  })

  it('surfaces a failed save instead of pretending it worked', async () => {
    updateProfileMock.mockRejectedValue(new Error('network'))
    render(<ColorPresetForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('radio', { name: 'Amber warm' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Something went wrong. Please try again.',
    )
  })
})
