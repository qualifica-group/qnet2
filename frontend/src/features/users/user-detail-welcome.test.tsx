import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { UserDetailHeader } from '@/features/users/user-detail-header'
import type { UserDetail } from '@/features/users/types'

/** Spec 0177 AC-020. */

const resendWelcomeEmailMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  resendWelcomeEmail: (...args: unknown[]) => resendWelcomeEmailMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() },
}))

function user(mustSetPassword: boolean): UserDetail {
  return {
    id: 9,
    name: 'Jane Doe',
    email: 'jane@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    must_set_password: mustSetPassword,
    created_at: null,
  }
}

function renderHeader(mustSetPassword: boolean, canUpdate: boolean) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <UserDetailHeader user={user(mustSetPassword)} canUpdate={canUpdate} />
    </QueryClientProvider>,
  )
}

describe('UserDetailHeader first access', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    resendWelcomeEmailMock.mockReset()
    toastSuccessMock.mockReset()
    resendWelcomeEmailMock.mockResolvedValue('Welcome email sent.')
  })

  it('shows the badge and posts the resend with a toast on click', async () => {
    renderHeader(true, true)

    expect(screen.getByText('Awaiting first sign-in')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Resend welcome email' }))

    await waitFor(() => expect(resendWelcomeEmailMock).toHaveBeenCalledWith(9))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Welcome email sent.'))
  })

  it('hides the action without the update permission but keeps the badge', () => {
    renderHeader(true, false)

    expect(screen.getByText('Awaiting first sign-in')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Resend welcome email' })).not.toBeInTheDocument()
  })

  it('shows neither badge nor action once first access is complete', () => {
    renderHeader(false, true)

    expect(screen.queryByText('Awaiting first sign-in')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Resend welcome email' })).not.toBeInTheDocument()
  })
})
