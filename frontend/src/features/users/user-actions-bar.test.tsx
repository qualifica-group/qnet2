import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import type { AuthContextValue } from '@/features/auth/auth-context'
import i18n from '@/i18n'
import { UserActionsBar } from '@/features/users/user-actions-bar'
import { UserDetailHeader } from '@/features/users/user-detail-header'
import { ACTOR_AUTH, EDITABLE, READ_ONLY, permissionsFor, userFixture, userWrapper } from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * The user record actions bar: resend welcome (spec 0177 AC-020),
 * activate/deactivate, impersonate and delete — each present only when it
 * applies, the risky ones behind a confirmation.
 */

const resendWelcomeEmailMock = vi.fn()
const updateUserMock = vi.fn()
const deleteUserMock = vi.fn()
const navigateMock = vi.hoisted(() => vi.fn())
const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }))

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  resendWelcomeEmail: (...args: unknown[]) => resendWelcomeEmailMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  deleteUser: (...args: unknown[]) => deleteUserMock(...args),
}))

vi.mock('react-router-dom', async (importOriginal) => ({
  ...(await importOriginal<typeof import('react-router-dom')>()),
  useNavigate: () => navigateMock,
}))

vi.mock('sonner', () => ({ toast: toastMock }))

const impersonateMock = vi.fn()

interface RenderBarOptions {
  auth?: AuthContextValue
  onLeave?: () => void
  onChanged?: () => void
}

function renderBar(
  user: UserDetailWithPermissions,
  { auth = { ...ACTOR_AUTH, impersonate: impersonateMock }, onLeave = vi.fn(), onChanged = vi.fn() }: RenderBarOptions = {},
) {
  const { wrapper: Wrapper } = userWrapper(auth)
  render(
    <Wrapper>
      <UserDetailHeader user={user} />
      <UserActionsBar user={user} onLeave={onLeave} onChanged={onChanged} />
    </Wrapper>,
  )
  return { onLeave, onChanged }
}

async function confirmDialog(label: string) {
  const dialog = await screen.findByRole('alertdialog')
  fireEvent.click(within(dialog).getByRole('button', { name: label }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  resendWelcomeEmailMock.mockResolvedValue('Welcome email sent.')
  deleteUserMock.mockResolvedValue(undefined)
  impersonateMock.mockResolvedValue(undefined)
})

describe('UserActionsBar — first access (spec 0177 AC-020)', () => {
  it('shows the badge and posts the resend with a toast on click', async () => {
    renderBar(userFixture({ must_set_password: true }))

    expect(screen.getByText('Awaiting first sign-in')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Resend welcome email' }))

    await waitFor(() => expect(resendWelcomeEmailMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Welcome email sent.'))
  })

  it('hides the action without the update permission but keeps the badge', () => {
    const permissions = { ...permissionsFor(), resource: { ...permissionsFor().resource, update: false } }
    renderBar(userFixture({ must_set_password: true, permissions }))

    expect(screen.getByText('Awaiting first sign-in')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Resend welcome email' })).not.toBeInTheDocument()
  })

  it('shows neither badge nor action once first access is complete', () => {
    renderBar(userFixture({ must_set_password: false }))

    expect(screen.queryByText('Awaiting first sign-in')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Resend welcome email' })).not.toBeInTheDocument()
  })
})

describe('UserActionsBar — activate / deactivate', () => {
  it('deactivates an active user after the confirmation', async () => {
    updateUserMock.mockResolvedValue(userFixture({ is_active: false }))
    const { onChanged } = renderBar(userFixture({ is_active: true }))

    fireEvent.click(screen.getByRole('button', { name: 'Deactivate' }))
    await confirmDialog('Deactivate')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledWith(7, { is_active: false }))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('User deactivated.'))
    expect(onChanged).toHaveBeenCalled()
  })

  it('activates an inactive user without asking', async () => {
    updateUserMock.mockResolvedValue(userFixture({ is_active: true }))
    renderBar(userFixture({ is_active: false }))

    expect(screen.queryByRole('button', { name: 'Deactivate' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Activate' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledWith(7, { is_active: true }))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('User activated.'))
  })

  it('warns when a past termination date keeps the user inactive', async () => {
    updateUserMock.mockResolvedValue(userFixture({ is_active: false }))
    renderBar(userFixture({ is_active: false }))

    fireEvent.click(screen.getByRole('button', { name: 'Activate' }))

    await waitFor(() =>
      expect(toastMock.warning).toHaveBeenCalledWith('The user has a termination date in the past and stays inactive.'),
    )
  })

  it('offers no status action on the actor own record', () => {
    renderBar(userFixture({ is_active: true }), { auth: { ...ACTOR_AUTH, user: { id: 7 } } as AuthContextValue })
    expect(screen.queryByRole('button', { name: 'Deactivate' })).not.toBeInTheDocument()
  })

  it('offers no status action when is_active is read-only', () => {
    renderBar(userFixture({ permissions: permissionsFor({ is_active: READ_ONLY }) }))
    expect(screen.queryByRole('button', { name: 'Deactivate' })).not.toBeInTheDocument()
  })
})

describe('UserActionsBar — impersonate', () => {
  const impersonable = () => userFixture({ permissions: permissionsFor({ is_active: EDITABLE }, { impersonate: true }) })

  it('swaps to the user after the confirmation, leaves the record and lands on the dashboard', async () => {
    const { onLeave } = renderBar(impersonable())

    fireEvent.click(screen.getByRole('button', { name: 'Impersonate' }))
    await confirmDialog('Impersonate')

    await waitFor(() => expect(impersonateMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/dashboard'))
    expect(onLeave).toHaveBeenCalledWith('impersonated')
  })

  it('is absent without the server flag', () => {
    renderBar(userFixture())
    expect(screen.queryByRole('button', { name: 'Impersonate' })).not.toBeInTheDocument()
  })

  it('is absent while the actor already operates as someone else', () => {
    renderBar(impersonable(), {
      auth: { ...ACTOR_AUTH, impersonator: { id: 2, name: 'Root', email: 'root@example.com' } } as AuthContextValue,
    })
    expect(screen.queryByRole('button', { name: 'Impersonate' })).not.toBeInTheDocument()
  })
})

describe('UserActionsBar — delete', () => {
  const deletable = () => userFixture({ permissions: permissionsFor({}, { delete: true }) })

  it('deletes after the confirmation and leaves the record', async () => {
    const { onLeave } = renderBar(deletable())

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    await confirmDialog('Delete')

    await waitFor(() => expect(deleteUserMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(onLeave).toHaveBeenCalledWith('deleted'))
    expect(toastMock.success).toHaveBeenCalledWith('User deleted successfully.')
  })

  it('does nothing when the confirmation is declined', async () => {
    renderBar(deletable())

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    await confirmDialog('Cancel')

    expect(deleteUserMock).not.toHaveBeenCalled()
  })

  it('is absent without the server flag', () => {
    renderBar(userFixture())
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })
})
