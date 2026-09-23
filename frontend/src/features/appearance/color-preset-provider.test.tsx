import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import { ColorPresetProvider } from '@/features/appearance/color-preset-provider'
import type { User } from '@/features/auth/types'

/**
 * The provider is the only bridge between the user's `color_preset` and the
 * stylesheet: it must mirror the preference onto <body>, follow a change, and
 * fall back to the default for an anonymous visitor or an unknown value.
 */

const currentUser = vi.fn<() => Partial<User> | undefined>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

beforeEach(() => {
  currentUser.mockReset()
})

afterEach(() => {
  document.body.removeAttribute('data-color-preset')
})

describe('ColorPresetProvider', () => {
  it("applies the user's preset to <body>", () => {
    currentUser.mockReturnValue({ color_preset: 'forest' })
    render(<ColorPresetProvider>child</ColorPresetProvider>)

    expect(document.body).toHaveAttribute('data-color-preset', 'forest')
  })

  it('follows a change of the preference', () => {
    currentUser.mockReturnValue({ color_preset: 'forest' })
    const { rerender } = render(<ColorPresetProvider>child</ColorPresetProvider>)

    currentUser.mockReturnValue({ color_preset: 'terracotta' })
    rerender(<ColorPresetProvider>child</ColorPresetProvider>)

    expect(document.body).toHaveAttribute('data-color-preset', 'terracotta')
  })

  it('falls back to the default without a user or with an unknown value', () => {
    currentUser.mockReturnValue(undefined)
    const { rerender } = render(<ColorPresetProvider>child</ColorPresetProvider>)
    expect(document.body).toHaveAttribute('data-color-preset', 'default')

    currentUser.mockReturnValue({ color_preset: 'neon' as never })
    rerender(<ColorPresetProvider>child</ColorPresetProvider>)
    expect(document.body).toHaveAttribute('data-color-preset', 'default')
  })
})
