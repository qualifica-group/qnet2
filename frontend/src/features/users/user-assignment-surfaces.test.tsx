// REQUIREMENT CHANGED (spec 0286 D-1): the UI label "Competence" is now "Assignment scope"; keys, fields and API are unchanged.
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  HIDDEN,
  confirmOpenRow,
  employmentFixture,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'

/**
 * The assignment configuration (user directive 2026-09-11) on the record
 * layout of spec 0222: one section gathering the Assignable flag, the
 * competence rows and BOTH Sedi; the verdict live in the KPI strip and in the
 * callout while creating, persisted on the detail. REQUIREMENT CHANGED (spec
 * 0222): the verdict follows the rows confirmed with Fatto, and it lives in the
 * KPI strip (the identity bar and side recap are gone).
 */

/**
 * The row editor has its own suite: here it only has to publish how many rows
 * the field holds and let a test append a COMPLETE one, which is all the
 * verdict reads.
 */
vi.mock('@/features/product-lines/competence-lines-field', () => ({
  CompetenceLinesField: ({
    value,
    onChange,
  }: {
    value: { business_function_id: number | null; product_category_id: number | null }[]
    onChange: (rows: { business_function_id: number | null; product_category_id: number | null }[]) => void
  }) => (
    <div>
      <output aria-label="competence rows">{value.length}</output>
      <button type="button" onClick={() => onChange([...value, { business_function_id: 4, product_category_id: 21 }])}>
        add competence
      </button>
    </div>
  ),
}))

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

const CONFIGURED_EMPLOYMENT: EmploymentDetail = employmentFixture({
  primary_operational_site_id: 8,
  remote_operational_site_ids: [9, 10],
  product_lines: [{ id: 91, business_function: { id: 4, name: 'Sales' }, product_category: { id: 21, name: 'Photovoltaic' } }],
  primary_operational_site: { id: 8, label: 'Via Roma 1' },
  remote_operational_sites: [
    { id: 9, label: 'Via Milano 2' },
    { id: 10, label: 'Via Torino 3' },
  ],
})

/** A role that sees none of the five assignment fields (spec 0129 added the all-categories flag, spec 0194 the Assignable flag). */
const ASSIGNMENT_HIDDEN: Record<string, typeof HIDDEN> = {
  'employment.product_lines': HIDDEN,
  'employment.is_assignable': HIDDEN,
  'employment.covers_all_product_categories': HIDDEN,
  'employment.primary_operational_site_id': HIDDEN,
  'employment.remote_operational_site_ids': HIDDEN,
}

function userWith(employment: EmploymentDetail, permissions: ResourcePermissions = permissionsFor()): UserDetailWithPermissions {
  return userFixture({ employment, permissions })
}

/** The KPI tile labelled "Matching", where the one-word verdict lives. */
function verdictTile(): HTMLElement {
  return screen.getByText('Matching').parentElement?.parentElement as HTMLElement
}

function addCompetenceRow() {
  fireEvent.click(pencilOf('Assignment scope'))
  fireEvent.click(screen.getByRole('button', { name: 'add competence' }))
  confirmOpenRow('Done')
}

describe('UserForm — the assignment configuration is one section', () => {
  it('gathers the Assignable flag, the competence rows and BOTH Sedi in the same block', async () => {
    renderCreate()

    const section = (await screen.findByText('Assignment configuration')).closest('section') as HTMLElement
    for (const row of ['Assignable', 'Scope on all categories', 'Assignment scope', 'Physical site', 'Remote sites']) {
      expect(within(section).getByRole('button', { name: `Edit ${row}` })).toBeInTheDocument()
    }
  })
})

describe('UserForm — live assignment verdict', () => {
  it('refuses nothing but says the person is unreachable, and names BOTH reasons', async () => {
    renderCreate()

    await screen.findByText('Assignment configuration')
    expect(verdictTile()).toHaveTextContent('Not assignable')
    expect(screen.getByRole('status')).toHaveTextContent(/No assignment scope configured/)
    expect(screen.getByRole('status')).toHaveTextContent(/No operational site/)
    // The verdict is guidance, not a gate.
    expect(screen.getAllByRole('button', { name: 'Save' })[0]).toBeEnabled()
  })

  it('flips to assignable as soon as a competence row and a Sede are set', async () => {
    renderCreate()
    await screen.findByText('Assignment configuration')

    addCompetenceRow()
    fireEvent.click(pencilOf('Physical site'))
    fireEvent.click(screen.getByText('pick-Physical site'))
    confirmOpenRow('Done')

    await waitFor(() => expect(verdictTile()).toHaveTextContent('Assignable'))
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('counts a REMOTE site as a full Sede, exactly as the server does', async () => {
    renderCreate()
    await screen.findByText('Assignment configuration')

    addCompetenceRow()
    fireEvent.click(pencilOf('Remote sites'))
    fireEvent.click(screen.getByText('pick-Remote sites'))
    confirmOpenRow('Done')

    await waitFor(() => expect(verdictTile()).not.toHaveTextContent('Not assignable'))
  })

  it('tells a role that cannot see the fields nothing about assignment in the section', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor(ASSIGNMENT_HIDDEN) })
    renderCreate()

    await screen.findByRole('button', { name: 'Edit Personal details' })
    expect(screen.queryByText('Assignment configuration')).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.queryByText('Physical site')).not.toBeInTheDocument()
    expect(screen.queryByText('Remote sites')).not.toBeInTheDocument()
  })

  it('still shows the assignment section to a role that sees only the all-categories flag', async () => {
    const fields = { ...ASSIGNMENT_HIDDEN }
    delete fields['employment.covers_all_product_categories']
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor(fields) })
    renderCreate()

    expect(await screen.findByText('Assignment configuration')).toBeInTheDocument()
    expect(screen.queryByText('Physical site')).not.toBeInTheDocument()
    expect(screen.queryByText('Remote sites')).not.toBeInTheDocument()
  })
})

describe('UserDetailView — the assignment configuration on the record', () => {
  it('counts competence and Sedi in the KPI strip, with no warning band when it all works', async () => {
    await renderDetail(userWith(CONFIGURED_EMPLOYMENT))

    expect(verdictTile()).toHaveTextContent('Assignable')
    expect(screen.getByText('1 physical · 2 remote')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('spec 0194 AC-008 — says "Not assignable" with the dedicated reason when the flag is off', async () => {
    await renderDetail(userWith({ ...CONFIGURED_EMPLOYMENT, is_assignable: false }))

    expect(screen.getByRole('status')).toHaveTextContent('Not assignable')
    expect(screen.getByRole('status')).toHaveTextContent(/Assignment turned off by the Assignable setting/)
    expect(screen.getByRole('status')).not.toHaveTextContent(/No assignment scope configured/)
  })

  it('raises the band, and names the reason, for a person no record can reach', async () => {
    await renderDetail(userWith(employmentFixture()))

    expect(screen.getByRole('status')).toHaveTextContent(/No assignment scope configured/)
    expect(screen.getByRole('status')).toHaveTextContent(/No operational site/)
  })
})
