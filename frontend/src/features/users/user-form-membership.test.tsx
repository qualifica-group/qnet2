import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  READ_ONLY,
  clickCreateSave,
  confirmOpenRow,
  employmentFixture,
  fillRequiredCreateRows,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0275 AC-018/AC-019: the business-function membership picker, its payload
 * and 422 mapping on the user record, and the read-only "Manager of functions".
 */

const fetchUserMock = vi.fn()
const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The quick-create "+" (spec 0028) is gated by `Can`/`useAbilities`, which needs
// an `AuthProvider` these suites do not render: deny everything so it never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/config/use-config', async () => {
  const { USER_ENUMS } = await import('@/features/users/user-test-fixtures')
  return {
    useConfig: () => ({ data: { enums: USER_ENUMS } }),
    useEnumOptions: (key: string) => USER_ENUMS[key] ?? [],
  }
})

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/users/user-test-stubs')).StubAsyncSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/users/user-test-stubs')).StubMultiSelect,
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

async function renderDetail(user: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(user)
  const { wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  await screen.findByRole('button', { name: 'Edit Personal details' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  fetchUserMock.mockReset()
  createUserMock.mockReset()
  updateUserMock.mockReset()
  createUserMock.mockResolvedValue(userFixture())
  updateUserMock.mockResolvedValue(userFixture())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

const FIELD = 'Business functions of membership'

function memberUser(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return userFixture({
    employment: employmentFixture({
      business_function_ids: [8],
      business_functions: [{ id: 8, name: 'Sales' }],
    }),
    ...overrides,
  })
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

describe('UserDetailView — business function membership (spec 0275 AC-018)', () => {
  it('binds the picker to the business functions, hydrated with the current membership', async () => {
    await renderDetail(memberUser())

    fireEvent.click(pencilOf(FIELD))
    expect(screen.getByTestId(`resource-${FIELD}`)).toHaveTextContent('business-functions')
    expect(screen.getByTestId(`selected-label-${FIELD}`)).toHaveTextContent('Sales')
  })

  it('sends employment.business_function_ids with the picked function', async () => {
    await renderDetail(memberUser())

    fireEvent.click(pencilOf(FIELD))
    fireEvent.click(screen.getByText(`pick-${FIELD}`))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment).toMatchObject({ business_function_ids: [8, 1] })
  })

  it('shows the server 422 under the field with the accessible error wiring', async () => {
    updateUserMock.mockRejectedValue(
      unprocessable({ 'employment.business_function_ids': ['An active person needs at least one business function.'] }),
    )
    await renderDetail(memberUser())

    fireEvent.click(pencilOf(FIELD))
    fireEvent.click(screen.getByText(`clear-${FIELD}`))
    confirmOpenRow('Save')

    const alerts = await screen.findAllByRole('alert')
    expect(alerts[0]).toHaveTextContent('An active person needs at least one business function.')
  })

  it('surfaces an indexed employment.business_function_ids.N 422 under the field', async () => {
    updateUserMock.mockRejectedValue(unprocessable({ 'employment.business_function_ids.1': ['The selected function is invalid.'] }))
    await renderDetail(memberUser())

    fireEvent.click(pencilOf(FIELD))
    fireEvent.click(screen.getByText(`pick-${FIELD}`))
    confirmOpenRow('Save')

    expect(await screen.findByText('The selected function is invalid.')).toBeInTheDocument()
  })

  it('offers no pencil when the field is read-only for the actor', async () => {
    await renderDetail(memberUser({ permissions: permissionsFor({ 'employment.business_function_ids': READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: `Edit ${FIELD}` })).not.toBeInTheDocument()
  })

  it('creates with the employment membership key in the payload', async () => {
    const { wrapper } = userWrapper()
    render(<UserForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
    await fillRequiredCreateRows()
    fireEvent.click(pencilOf(FIELD))
    fireEvent.click(screen.getByText(`pick-${FIELD}`))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toMatchObject({ business_function_ids: [1] })
  })
})

// REQUIREMENT CHANGED (spec 0276 D-4, D-7): the managed-functions row is editable and
// the header repeats the functions as chips, so a name can appear in several places.
describe('UserDetailView — manager of functions (spec 0275 AC-019, spec 0276 AC-012, AC-013)', () => {
  it('lists the managed functions in the row, which has a pencil', async () => {
    await renderDetail(
      memberUser({ managed_business_functions: [{ id: 2, name: 'Finance' }, { id: 3, name: 'Legal' }] }),
    )

    expect(screen.getByText('Manager of functions')).toBeInTheDocument()
    expect(screen.getAllByText('Finance').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Legal').length).toBeGreaterThan(0)
    expect(screen.getByRole('button', { name: 'Edit Manager of functions' })).toBeInTheDocument()
  })

  it('crowns only the managed functions in the header, the membership and the managed list (AC-013, AC-024)', async () => {
    await renderDetail(
      userFixture({
        employment: employmentFixture({
          business_function_ids: [2, 8],
          business_functions: [{ id: 2, name: 'Finance' }, { id: 8, name: 'Sales' }],
        }),
        managed_business_functions: [{ id: 2, name: 'Finance' }],
      }),
    )

    // One crown per place Finance is listed (header, membership, managed list); none on Sales.
    expect(screen.getAllByRole('img', { name: 'Manager' })).toHaveLength(3)
    screen.getAllByText('Sales').forEach((sales) => expect(sales.parentElement?.querySelector('[role="img"]')).toBeNull())
  })

  it('adds the managed functions the person is not a member of to the header, crowned (AC-013)', async () => {
    await renderDetail(
      userFixture({
        employment: employmentFixture({ business_function_ids: [8], business_functions: [{ id: 8, name: 'Sales' }] }),
        managed_business_functions: [{ id: 3, name: 'Legal' }],
      }),
    )

    const header = screen.getByRole('heading', { name: 'Ada Lovelace' }).parentElement?.parentElement as HTMLElement
    expect(within(header).getByText('Sales')).toBeInTheDocument()
    expect(within(header).getByText('Legal')).toBeInTheDocument()
    expect(within(header).getAllByRole('img', { name: 'Manager' })).toHaveLength(1)
  })

  it('shows no function chip in the header when there is none (AC-013)', async () => {
    await renderDetail(memberUser())

    const header = screen.getByRole('heading', { name: 'Ada Lovelace' }).parentElement?.parentElement as HTMLElement
    expect(within(header).queryByRole('img', { name: 'Manager' })).not.toBeInTheDocument()
    expect(within(header).queryByText('—')).not.toBeInTheDocument()
  })

  it('shows the dedicated empty text when the person manages no function', async () => {
    await renderDetail(memberUser())

    expect(screen.getByText('Not the manager of any function.')).toBeInTheDocument()
  })
})
