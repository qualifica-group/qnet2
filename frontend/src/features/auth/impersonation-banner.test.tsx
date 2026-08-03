import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ImpersonationBanner } from '@/features/auth/impersonation-banner'
import type { User } from '@/features/auth/types'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'

const stopImpersonationMock = vi.fn()
let authState: {
  user: User | null
  impersonator: { id: number; name: string; email: string } | null
}

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ ...authState, stopImpersonation: stopImpersonationMock }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function user(overrides: Partial<User> = {}): User {
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
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  stopImpersonationMock.mockReset()
  stopImpersonationMock.mockResolvedValue(undefined)
})

describe('ImpersonationBanner', () => {
  it('is not rendered when impersonator is null (AC-021)', () => {
    authState = { user: user(), impersonator: null }
    render(<ImpersonationBanner />)

    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('shows the impersonated user name and an exit action while impersonating (AC-020)', () => {
    authState = {
      user: user({ name: 'Target User' }),
      impersonator: { id: 1, name: 'Original Actor', email: 'actor@example.com' },
    }
    render(<ImpersonationBanner />)

    expect(screen.getByRole('status')).toHaveTextContent('You are operating as Target User')
    expect(screen.getByRole('button', { name: 'Back to your account' })).toBeInTheDocument()
  })

  it('calls stopImpersonation when the exit action is clicked (AC-022)', async () => {
    authState = {
      user: user(),
      impersonator: { id: 1, name: 'Original Actor', email: 'actor@example.com' },
    }
    render(<ImpersonationBanner />)

    fireEvent.click(screen.getByRole('button', { name: 'Back to your account' }))

    await waitFor(() => expect(stopImpersonationMock).toHaveBeenCalledTimes(1))
  })
})
