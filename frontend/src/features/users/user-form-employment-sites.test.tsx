import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import {
  HIDDEN,
  confirmOpenRow,
  employmentFixture,
  pencilOf,
  permissionsFor,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * Spec 0103 AC-028..030 on the record layout of spec 0222: the physical site
 * (at most one) and the remote sites, in the assignment section, each its own
 * in-place row. REQUIREMENT CHANGED (spec 0222): the pickers open from their
 * rows, and a save sends the whole employment object only.
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

function userWithSites(remote = [9, 10]): UserDetailWithPermissions {
  const labels: Record<number, string> = { 9: 'Via Milano 2', 10: 'Via Torino 3' }
  return userFixture({
    employment: employmentFixture({
      primary_operational_site_id: 8,
      primary_operational_site: { id: 8, label: 'Via Roma 1' },
      remote_operational_site_ids: remote,
      remote_operational_sites: remote.map((id) => ({ id, label: labels[id] })),
    }),
  })
}

describe('UserDetailView — physical site + remote sites (spec 0103 AC-028)', () => {
  it('binds a single picker for the physical site and a multi picker for the remote sites to operational-sites', async () => {
    await renderDetail(userWithSites())

    fireEvent.click(pencilOf('Physical site'))
    expect(screen.getByTestId('resource-Physical site')).toHaveTextContent('operational-sites')
    expect(screen.getByTestId('selected-label-Physical site')).toHaveTextContent('Via Roma 1')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    fireEvent.click(pencilOf('Remote sites'))
    expect(screen.getByTestId('resource-Remote sites')).toHaveTextContent('operational-sites')
    expect(screen.getByTestId('selected-label-Remote sites')).toHaveTextContent('Via Milano 2, Via Torino 3')
  })

  it('appends a picked remote site without replacing the others, and keeps the physical one', async () => {
    await renderDetail(userWithSites())

    fireEvent.click(pencilOf('Remote sites'))
    fireEvent.click(screen.getByText('pick-Remote sites'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment).toMatchObject({
      primary_operational_site_id: 8,
      remote_operational_site_ids: [9, 10, 1],
    })
  })
})

describe('UserDetailView — remote sites field permission gate (spec 0103 AC-029)', () => {
  it('renders no remote-sites row for a role that only sees the physical site', async () => {
    await renderDetail({
      ...userWithSites(),
      permissions: permissionsFor({ 'employment.remote_operational_site_ids': HIDDEN }),
    })

    expect(pencilOf('Physical site')).toBeInTheDocument()
    expect(screen.queryByText('Remote sites')).not.toBeInTheDocument()
  })
})

describe('UserDetailView — physical + remote sites shown (spec 0103 AC-030)', () => {
  it('shows the physical site and every remote site, distinguishably, in the assignment section', async () => {
    await renderDetail(userWithSites())

    const assignment = screen.getByText('Assignment configuration').closest('section') as HTMLElement
    expect(within(assignment).getByText('Physical site')).toBeInTheDocument()
    expect(within(assignment).getByText('Via Roma 1')).toBeInTheDocument()
    expect(within(assignment).getByText('Remote sites')).toBeInTheDocument()
    expect(within(assignment).getByText('Via Milano 2')).toBeInTheDocument()
    expect(within(assignment).getByText('Via Torino 3')).toBeInTheDocument()
  })

  it('falls back to the shared empty marker when there are no remote sites', async () => {
    await renderDetail(userWithSites([]))

    const assignment = screen.getByText('Assignment configuration').closest('section') as HTMLElement
    expect(within(assignment).getByText('Via Roma 1')).toBeInTheDocument()
    expect(within(assignment).getAllByText('—').length).toBeGreaterThan(0)
  })
})
