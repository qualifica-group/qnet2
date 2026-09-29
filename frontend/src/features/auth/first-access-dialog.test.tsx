import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { FirstAccessDialog } from '@/features/auth/first-access-dialog'

/** Spec 0177 rev. 2, AC-018 / AC-019. */

const setFirstPasswordMock = vi.fn()
const toastSuccessMock = vi.fn()

interface AuthState {
  user: { name: string; must_set_password: boolean } | null
  impersonator: { id: number } | null
}
let authState: AuthState

vi.mock('@/features/auth/api', () => ({
  setFirstPassword: (...args: unknown[]) => setFirstPasswordMock(...args),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => authState,
}))

vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() },
}))

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <FirstAccessDialog />
    </QueryClientProvider>,
  )
}

function fill(password: string, confirmation: string) {
  fireEvent.change(screen.getByLabelText(/^New password/), { target: { value: password } })
  fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: confirmation } })
  fireEvent.click(screen.getByRole('button', { name: 'Save password' }))
}

describe('FirstAccessDialog', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    setFirstPasswordMock.mockReset()
    toastSuccessMock.mockReset()
    setFirstPasswordMock.mockResolvedValue(undefined)
    authState = { user: { name: 'Ada', must_set_password: true }, impersonator: null }
  })

  it('opens with the user name when the flag is set', () => {
    renderDialog()

    expect(screen.getByRole('dialog', { name: 'Welcome to QNet, Ada!' })).toBeInTheDocument()
  })

  it('stays closed without the flag', () => {
    authState = { user: { name: 'Ada', must_set_password: false }, impersonator: null }
    renderDialog()

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('stays closed while impersonating', () => {
    authState = { user: { name: 'Ada', must_set_password: true }, impersonator: { id: 1 } }
    renderDialog()

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('closes with Later', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Later' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('submits a valid pair and shows the toast', async () => {
    renderDialog()

    fill('longenough1', 'longenough1')

    await waitFor(() =>
      expect(setFirstPasswordMock).toHaveBeenCalledWith({
        password: 'longenough1',
        password_confirmation: 'longenough1',
      }),
    )
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Password updated.'))
  })

  it('shows a field error and does not call the API on mismatch or short password', async () => {
    renderDialog()

    fill('longenough1', 'different1')
    expect(await screen.findByText('Passwords do not match.')).toBeInTheDocument()

    fill('short', 'short')
    expect(await screen.findByText('Password must be at least 8 characters.')).toBeInTheDocument()
    expect(setFirstPasswordMock).not.toHaveBeenCalled()
  })

  it('maps a 422 on password to the field', async () => {
    setFirstPasswordMock.mockRejectedValue(
      new AxiosError('422', 'ERR_BAD_REQUEST', undefined, undefined, {
        status: 422,
        data: { errors: { password: ['Same as the current one.'] } },
      } as AxiosResponse),
    )
    renderDialog()

    fill('longenough1', 'longenough1')

    expect(await screen.findByText('Same as the current one.')).toBeInTheDocument()
  })
})
