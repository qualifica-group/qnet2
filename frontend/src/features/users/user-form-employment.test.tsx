import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  HIDDEN,
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
 * Spec 0015 AC-014..019 (frontend) on the record layout of spec 0222: the
 * employment rows, the reports-to and managed-functions rows, the
 * relation pickers, the employment payload and its 422 mapping, the i18n.
 * REQUIREMENT CHANGED (spec 0222): rows are closed until opened, the edit
 * cases run on the in-place detail, and `employment` travels only when one of
 * its fields changed (D-3).
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

function renderCreate() {
  const { client, wrapper } = userWrapper()
  render(<UserForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
  return client
}

async function renderDetail(user: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(user)
  const { client, wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  await screen.findByRole('button', { name: 'Edit Personal details' })
  return client
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

function userWithEmployment(): UserDetailWithPermissions {
  return userFixture({
    employment: employmentFixture({
      job_role_ids: [3],
      job_roles: [{ id: 3, name: 'Backend developer' }],
      relationship_type: 'employee',
      hired_at: '2026-01-15',
      standard_daily_minutes: 480,
      break_daily_minutes: 30,
      reports_to_ids: [2],
      company_id: 5,
      reports_to: [{ id: 2, label: 'Grace Hopper' }],
      company: { id: 5, label: 'Acme Srl' },
    }),
  })
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

describe('UserForm — the record layout (spec 0222 D-2)', () => {
  it('lays out the card, credentials, assignment, employment and contact blocks on one screen, no macro tabs', async () => {
    renderCreate()

    expect(await screen.findByRole('button', { name: 'Edit Personal details' })).toBeInTheDocument()
    expect(pencilOf('Email')).toBeInTheDocument()
    expect(pencilOf('Reports to')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Phone/)).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Employment' })).not.toBeInTheDocument()
  })
})

// REQUIREMENT CHANGED (spec 0276 D-2): there is no manager flag, so "Reports to" is always editable and never emptied.
describe('UserForm — reports_to / managed functions (spec 0015 AC-015, spec 0276 AC-007, AC-012)', () => {
  it('submits the picked managers', async () => {
    renderCreate()
    await fillRequiredCreateRows()

    fireEvent.click(pencilOf('Reports to'))
    fireEvent.click(screen.getByText('pick-Reports to'))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toMatchObject({ reports_to_ids: [1] })
  })

  it('sends the managed functions picked on the Manager of functions row', async () => {
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Manager of functions'))
    expect(screen.getByTestId('resource-Manager of functions')).toHaveTextContent('business-functions')
    expect(screen.getByText('If the function already has a manager, it passes to this person.')).toBeInTheDocument()
    fireEvent.click(screen.getByText('pick-Manager of functions'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment).toMatchObject({
      managed_business_function_ids: [1],
      reports_to_ids: [2],
    })
  })

  it('hydrates the picker with the functions the user already manages', async () => {
    await renderDetail(userFixture({ managed_business_functions: [{ id: 5, name: 'Sales' }] }))

    fireEvent.click(pencilOf('Manager of functions'))
    expect(screen.getByTestId('selected-label-Manager of functions')).toHaveTextContent('Sales')
  })

  it('offers no pencil on Manager of functions without the field permission (spec 0276 D-4)', async () => {
    await renderDetail(
      userFixture({ permissions: permissionsFor({ 'employment.managed_business_function_ids': READ_ONLY }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Manager of functions' })).not.toBeInTheDocument()
    expect(pencilOf('Job roles')).toBeInTheDocument()
  })

  it('shows an indexed employment.managed_business_function_ids.N 422 under the field with the aria wiring', async () => {
    updateUserMock.mockRejectedValue(
      unprocessable({ 'employment.managed_business_function_ids.0': ['The selected business function is invalid.'] }),
    )
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Manager of functions'))
    fireEvent.click(screen.getByText('pick-Manager of functions'))
    confirmOpenRow('Save')

    const message = await screen.findByText('The selected business function is invalid.')
    expect(message).toHaveAttribute('role', 'alert')
  })

  it('has no Manager row left (the flag is gone)', async () => {
    await renderDetail(userWithEmployment())

    expect(screen.queryByRole('button', { name: 'Edit Manager' })).not.toBeInTheDocument()
  })
})

describe('UserDetailView — employment relation pickers (spec 0015 AC-016)', () => {
  it('binds each picker to its resource, hydrated from the persisted profile', async () => {
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Reports to'))
    expect(screen.getByTestId('resource-Reports to')).toHaveTextContent('users')
    expect(screen.getByTestId('selected-label-Reports to')).toHaveTextContent('Grace Hopper')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    fireEvent.click(pencilOf('Job roles'))
    expect(screen.getByTestId('resource-Job roles')).toHaveTextContent('job-roles')
    expect(screen.getByTestId('selected-label-Job roles')).toHaveTextContent('Backend developer')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
  })

  /** REQUIREMENT CHANGED (spec 0272 D-2): relationship type and company are the in-force condition, read only. */
  it('shows the relationship type and company as plain read-only rows', async () => {
    await renderDetail(userWithEmployment())

    expect(screen.getByText('Acme Srl')).toBeInTheDocument()
    expect(screen.getByText('Employee')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Company' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Relationship type' })).not.toBeInTheDocument()
    expect(screen.queryByText('Qualification')).not.toBeInTheDocument()
  })
})

describe('UserDetailView — contractual data behind the field matrix (spec 0283 AC-009)', () => {
  const SENSITIVE = [
    'employment.relationship_type',
    'employment.company_id',
    'employment.contracts',
    'employment.hired_at',
    'employment.terminated_at',
    'employment.standard_daily_minutes',
    'employment.break_daily_minutes',
  ]

  it('renders neither the contract, its history nor the contract data when the role hides them', async () => {
    const hidden = Object.fromEntries(SENSITIVE.map((field) => [field, HIDDEN]))
    await renderDetail({ ...userWithEmployment(), permissions: permissionsFor(hidden) })

    for (const section of ['contract', 'contractHistory', 'contractData']) {
      expect(screen.queryByText(i18n.t(`users.form.sections.${section}.title`))).not.toBeInTheDocument()
    }
    expect(screen.getByText(i18n.t('users.form.sections.profile.title'))).toBeInTheDocument()
  })

  it('keeps the contract rows the role may see and drops the others', async () => {
    const permissions = permissionsFor({ 'employment.contracts': HIDDEN, 'employment.company_id': HIDDEN })
    await renderDetail({ ...userWithEmployment(), permissions })

    expect(screen.getByText(i18n.t('users.form.sections.contract.title'))).toBeInTheDocument()
    expect(screen.getByText('Employee')).toBeInTheDocument()
    expect(screen.queryByText('Acme Srl')).not.toBeInTheDocument()
    expect(screen.queryByText(i18n.t('users.detail.employment.collectiveAgreementLevel'))).not.toBeInTheDocument()
    expect(screen.queryByText(i18n.t('users.form.sections.contractHistory.title'))).not.toBeInTheDocument()
  })
})

describe('UserForm — employment payload + 422 mapping (spec 0015 AC-018)', () => {
  /** REQUIREMENT CHANGED (spec 0283 D-5): untouched contract data are left out of the payload. */
  it('includes the employment object on create, without the untouched contract data', async () => {
    renderCreate()
    await fillRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toEqual({
      job_role_ids: [],
      business_function_ids: [],
      managed_business_function_ids: [],
      reports_to_ids: [],
      primary_operational_site_id: null,
      remote_operational_site_ids: [],
      is_assignable: true,
      covers_all_product_categories: false,
      product_lines: [],
    })
  })

  it('sends the whole employment object, and nothing else, when one employment row is saved', async () => {
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Job roles'))
    fireEvent.click(screen.getByText('pick-Job roles'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    const payload = updateUserMock.mock.calls[0][1]
    expect(Object.keys(payload)).toEqual(['employment'])
    expect(payload.employment).toMatchObject({ job_role_ids: [3, 1], reports_to_ids: [2] })
    // REQUIREMENT CHANGED (spec 0272 D-2): relationship type and company come from the contract history.
    expect(payload.employment).not.toHaveProperty('company_id')
    expect(payload.employment).not.toHaveProperty('relationship_type')
    expect(payload.employment).not.toHaveProperty('qualification_type')
  })

  it('maps a nested employment.* 422 error onto its open row, inline', async () => {
    updateUserMock.mockRejectedValue(unprocessable({ 'employment.terminated_at': ['The termination date is invalid.'] }))
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Terminated at'))
    fireEvent.change(screen.getByLabelText('Terminated at'), { target: { value: '2026-02-01' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('The termination date is invalid.')).toBeInTheDocument()
    expect(updateUserMock).toHaveBeenCalledTimes(1)
  })

  /** Spec 0166 AC-014: the per-id 422 (duplicate/inexistent/self) is indexed, no RHF path matches it directly. */
  it('surfaces an indexed employment.reports_to_ids.N 422 under the field', async () => {
    updateUserMock.mockRejectedValue(unprocessable({ 'employment.reports_to_ids.0': ['A user cannot report to themselves.'] }))
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Reports to'))
    fireEvent.click(screen.getByText('pick-Reports to'))
    confirmOpenRow('Save')

    expect(await screen.findByText('A user cannot report to themselves.')).toBeInTheDocument()
  })
})

describe('UserDetailView — employment i18n (spec 0015 AC-019)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })
  afterAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('localizes the employment section headings and the relationship_type enum from the `enums` namespace', async () => {
    fetchUserMock.mockResolvedValue(userWithEmployment())
    const { wrapper } = userWrapper()
    render(<UserDetailView userId={7} />, { wrapper })

    expect(await screen.findByText('Rapporto contrattuale')).toBeInTheDocument()
    expect(screen.getByText('Dipendente')).toBeInTheDocument()
  })
})
