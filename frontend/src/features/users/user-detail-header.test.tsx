import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailHeader } from '@/features/users/user-detail-header'
import { userFixture, userWrapper } from '@/features/users/user-test-fixtures'
import { formatDateTime } from '@/lib/formatting/date-display'

/** Spec 0370 AC-005: the last sign-in badge shows only when a login is recorded. */
describe('UserDetailHeader last sign-in', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the last sign-in date and time when recorded', () => {
    const lastLoginAt = '2026-11-01T09:30:00+00:00'
    render(<UserDetailHeader user={userFixture({ last_login_at: lastLoginAt })} />, userWrapper())

    expect(screen.getByText(`Last sign-in: ${formatDateTime(lastLoginAt)}`)).toBeInTheDocument()
  })

  it('shows no badge when no login is recorded', () => {
    render(<UserDetailHeader user={userFixture({ last_login_at: null })} />, userWrapper())

    expect(screen.queryByText(/Last sign-in/)).not.toBeInTheDocument()
  })
})
