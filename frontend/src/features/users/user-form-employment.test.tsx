import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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
  typeInRow,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0015 AC-014..019 (frontend) on the record layout of spec 0222: the
 * employment rows, the manager / reports-to cascade (spec 0166 D-5), the
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
      job_description: 'Backend engineer',
      relationship_type: 'employee',
      qualification_type: 'coordinator',
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

describe('UserForm — is_manager / reports_to (spec 0015 AC-015, spec 0166 D-5)', () => {
  it('hides reports_to and force-empties it in the payload once is_manager is enabled', async () => {
    renderCreate()
    await fillRequiredCreateRows()

    fireEvent.click(pencilOf('Reports to'))
    fireEvent.click(screen.getByText('pick-Reports to'))
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Manager'))
    fireEvent.click(screen.getByRole('switch', { name: 'Manager' }))
    confirmOpenRow('Done')

    expect(screen.queryByRole('button', { name: 'Edit Reports to' })).not.toBeInTheDocument()
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toMatchObject({ is_manager: true, reports_to_ids: [] })
  })

  it('submits the picked managers when the person is not a manager', async () => {
    renderCreate()
    await fillRequiredCreateRows()

    fireEvent.click(pencilOf('Reports to'))
    fireEvent.click(screen.getByText('pick-Reports to'))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toMatchObject({ is_manager: false, reports_to_ids: [1] })
  })

  it('offers no Manager pencil when "Reports to", which it empties, is read-only (spec 0222 D-4)', async () => {
    await renderDetail(
      userFixture({ permissions: permissionsFor({ 'employment.reports_to_ids': READ_ONLY }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Manager' })).not.toBeInTheDocument()
    expect(pencilOf('Job description')).toBeInTheDocument()
  })
})

describe('UserDetailView — employment relation pickers (spec 0015 AC-016)', () => {
  it('binds each picker to its resource, hydrated from the persisted profile', async () => {
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Reports to'))
    expect(screen.getByTestId('resource-Reports to')).toHaveTextContent('users')
    expect(screen.getByTestId('selected-label-Reports to')).toHaveTextContent('Grace Hopper')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    fireEvent.click(pencilOf('Company'))
    expect(screen.getByTestId('resource-Company')).toHaveTextContent('companies')
    expect(screen.getByTestId('selected-label-Company')).toHaveTextContent('Acme Srl')
  })
})

describe('UserForm — employment payload + 422 mapping (spec 0015 AC-018)', () => {
  it('includes the whole employment object with contract snake_case keys on create', async () => {
    renderCreate()
    await fillRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0].employment).toEqual({
      is_manager: false,
      job_description: null,
      reports_to_ids: [],
      relationship_type: null,
      company_id: null,
      primary_operational_site_id: null,
      remote_operational_site_ids: [],
      is_assignable: true,
      covers_all_product_categories: false,
      product_lines: [],
      qualification_type: null,
      hired_at: null,
      terminated_at: null,
      standard_daily_minutes: null,
      break_daily_minutes: null,
      hourly_cost: null,
    })
  })

  it('sends the whole employment object, and nothing else, when one employment row is saved', async () => {
    await renderDetail(userWithEmployment())

    typeInRow('Job description', 'Platform engineer', 'Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    const payload = updateUserMock.mock.calls[0][1]
    expect(Object.keys(payload)).toEqual(['employment'])
    expect(payload.employment).toMatchObject({ job_description: 'Platform engineer', company_id: 5, reports_to_ids: [2] })
  })

  it('maps a nested employment.* 422 error onto its open row, inline', async () => {
    updateUserMock.mockRejectedValue(unprocessable({ 'employment.company_id': ['The selected company is invalid.'] }))
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Company'))
    fireEvent.click(screen.getByText('pick-Company'))
    confirmOpenRow('Save')

    expect(await screen.findByText('The selected company is invalid.')).toBeInTheDocument()
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

describe('UserDetail — hourly cost (spec 0272 AC-002)', () => {
  it('shows the saved cost in euro/hour and sends a decimal edit as employment.hourly_cost', async () => {
    const user = userWithEmployment()
    await renderDetail({ ...user, employment: { ...user.employment!, hourly_cost: 25 } })

    expect(screen.getByText(/25,00.*\/h/)).toBeInTheDocument()

    fireEvent.click(pencilOf('Hourly cost'))
    const input = screen.getByRole('spinbutton', { name: /Hourly cost/ })
    expect(input).toHaveAttribute('step', '0.01')
    expect(screen.getByText('€/h')).toBeInTheDocument()
    fireEvent.change(input, { target: { value: '27.5' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment).toMatchObject({ hourly_cost: 27.5, company_id: 5 })
  })

  it('maps an employment.hourly_cost 422 onto its open row', async () => {
    updateUserMock.mockRejectedValue(unprocessable({ 'employment.hourly_cost': ['The hourly cost must not exceed 9999.99.'] }))
    await renderDetail(userWithEmployment())

    fireEvent.click(pencilOf('Hourly cost'))
    fireEvent.change(screen.getByRole('spinbutton', { name: /Hourly cost/ }), { target: { value: '12' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('The hourly cost must not exceed 9999.99.')).toBeInTheDocument()
  })
})
