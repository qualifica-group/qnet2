import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { DateDisplayProvider } from '@/features/appearance/date-display-provider'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import {
  DATE_FORMAT_DEFAULT,
  TIME_FORMAT_DEFAULT,
  applyDateDisplayPreferences,
  formatDateTime,
} from '@/lib/formatting/date-display'
import type { User } from '@/features/auth/types'

/**
 * The provider is what makes the preference reach the plain (non-hook)
 * formatters used by AG Grid renderers: it must apply BEFORE the children
 * paint, and a change must actually re-render what is already on screen.
 */

const currentUser = vi.fn<() => User | undefined>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

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

/** A child that formats through the shared module state, like a grid cell does. */
function Stamp() {
  return <span>{formatDateTime('2026-08-03T14:30:00')}</span>
}

beforeEach(() => {
  currentUser.mockReset()
})

afterEach(() => {
  applyDateDisplayPreferences(DATE_FORMAT_DEFAULT, TIME_FORMAT_DEFAULT)
})

describe('DateDisplayProvider', () => {
  it("applies the user's preference before the children render", () => {
    currentUser.mockReturnValue(user({ date_format: 'ymd', time_format: '12h' }))

    render(
      <DateDisplayProvider>
        <Stamp />
      </DateDisplayProvider>,
    )

    expect(screen.getByText('2026-08-03 2:30 PM')).toBeInTheDocument()
  })

  it('re-renders dates already on screen when the preference changes', () => {
    currentUser.mockReturnValue(user({ date_format: 'dmy', time_format: '24h' }))
    const { rerender } = render(
      <DateDisplayProvider>
        <Stamp />
      </DateDisplayProvider>,
    )
    expect(screen.getByText('03/08/2026 14:30')).toBeInTheDocument()

    currentUser.mockReturnValue(user({ date_format: 'mdy', time_format: '12h' }))
    rerender(
      <DateDisplayProvider>
        <Stamp />
      </DateDisplayProvider>,
    )

    expect(screen.getByText('08/03/2026 2:30 PM')).toBeInTheDocument()
  })

  it('falls back to the defaults when no user is authenticated yet', () => {
    currentUser.mockReturnValue(undefined)

    render(
      <DateDisplayProvider>
        <Stamp />
      </DateDisplayProvider>,
    )

    expect(screen.getByText('03/08/2026 14:30')).toBeInTheDocument()
  })
})
