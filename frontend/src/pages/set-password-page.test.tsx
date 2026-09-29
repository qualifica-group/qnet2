import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import SetPasswordPage from '@/pages/set-password-page'

/** Spec 0177 AC-017. */

const setPasswordMock = vi.fn()

// The real shell mounts a data-router-only loading bar; the page logic is what is under test.
vi.mock('@/features/auth/auth-shell', () => ({
  AuthShell: ({ title, children, footer }: { title: string; children: ReactNode; footer?: ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {children}
      {footer}
    </div>
  ),
}))

vi.mock('@/features/auth/api', () => ({
  setPassword: (...args: unknown[]) => setPasswordMock(...args),
  resetPassword: vi.fn(),
}))

function renderAt(search: string) {
  return render(
    <MemoryRouter initialEntries={[`/set-password${search}`]}>
      <SetPasswordPage />
    </MemoryRouter>,
  )
}

function fillAndSubmit(password: string) {
  fireEvent.change(screen.getByLabelText(/^New password/), { target: { value: password } })
  fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: password } })
  fireEvent.click(screen.getByRole('button', { name: 'Set password' }))
}

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('422', 'ERR_BAD_REQUEST', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'invalid', errors },
  } as AxiosResponse)
}

describe('SetPasswordPage', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    setPasswordMock.mockReset()
  })

  it('shows the invalid-link notice when token or email are missing', () => {
    renderAt('')

    expect(screen.getByText(/This link is invalid or has expired/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Set password' })).not.toBeInTheDocument()
  })

  it('posts the token payload and shows the success message with a link to sign in', async () => {
    setPasswordMock.mockResolvedValue(undefined)
    renderAt('?token=abc&email=ada%40example.com')

    fillAndSubmit('longenough1')

    expect(await screen.findByText('Password set. You can now sign in.')).toBeInTheDocument()
    expect(setPasswordMock).toHaveBeenCalledWith({
      token: 'abc',
      email: 'ada@example.com',
      password: 'longenough1',
      password_confirmation: 'longenough1',
    })
    expect(screen.getByRole('link', { name: /sign in/i })).toHaveAttribute('href', '/login')
  })

  it('shows the invalid-or-expired message on a 422 under email', async () => {
    setPasswordMock.mockRejectedValue(unprocessable({ email: ['bad token'] }))
    renderAt('?token=abc&email=ada%40example.com')

    fillAndSubmit('longenough1')

    await waitFor(() =>
      expect(screen.getByText(/This link is invalid or has expired/)).toBeInTheDocument(),
    )
  })
})
