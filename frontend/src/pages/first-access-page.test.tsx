import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import FirstAccessPage from '@/pages/first-access-page'

/** Spec 0177 AC-019. */

const changePasswordMock = vi.fn()
const logoutMock = vi.fn()

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
  changePassword: (...args: unknown[]) => changePasswordMock(...args),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ logout: logoutMock }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/first-access']}>
        <Routes>
          <Route path="/first-access" element={<FirstAccessPage />} />
          <Route path="/dashboard" element={<p>dashboard page</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('FirstAccessPage', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    changePasswordMock.mockReset()
    logoutMock.mockReset()
    changePasswordMock.mockResolvedValue(undefined)
    logoutMock.mockResolvedValue(undefined)
  })

  it('changes the password and goes to the dashboard', async () => {
    renderPage()

    fireEvent.change(screen.getByLabelText(/^Current password/), { target: { value: 'temporary1' } })
    fireEvent.change(screen.getByLabelText(/^New password/), { target: { value: 'longenough1' } })
    fireEvent.change(screen.getByLabelText(/^Confirm/), { target: { value: 'longenough1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Update password' }))

    expect(await screen.findByText('dashboard page')).toBeInTheDocument()
    expect(changePasswordMock).toHaveBeenCalledWith({
      current_password: 'temporary1',
      password: 'longenough1',
      password_confirmation: 'longenough1',
    })
  })

  it('signs the user out from the Sign out button', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: 'Sign out' }))

    await waitFor(() => expect(logoutMock).toHaveBeenCalledTimes(1))
  })
})
