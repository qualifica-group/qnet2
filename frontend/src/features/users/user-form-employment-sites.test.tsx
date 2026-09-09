import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import { UserDetailView } from '@/features/users/user-detail'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0103 (physical + remote operational sites), the microtask F2 slice:
 * AC-028 (both controls on the form, payload carries both keys), AC-029 (the
 * remote-sites multi-select is gated by its own field-permission key) and
 * AC-030 (the read-only scheda shows both, distinguishable, with the shared
 * empty state). Split out of `user-form-employment.test.tsx` to stay within
 * the file size limit (`.claude/rules/engineering.md` §6) — same boilerplate
 * shape as that file and `user-form.test.tsx`, this codebase's established
 * per-concern test split.
 */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()
const fetchUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true, view_activity: false },
}

/** Controllable per-test, defaulting to full access (AC-029 overrides it). */
const formMetaPermissions = vi.fn<() => ResourcePermissions>(() => FULL_ACCESS_PERMISSIONS)
vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: formMetaPermissions() }),
}))

// This suite isn't about the quick-create "+" (spec 0028): both multi-relation
// fields on this form (`roles`, `employment.remote_operational_site_ids`) sit
// behind `AsyncPaginatedMultiSelect`, stubbed below so their `QuickCreateButton`
// (gated by `Can`/`useAbilities`, which needs an `AuthProvider` this suite
// doesn't render) never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

// `useCustomFieldsForm` (spec 0021) also reads the resource meta directly,
// bypassing `useUserFormMeta` above: stub it with no custom fields defined, so
// this suite (not about custom fields) renders exactly as the form otherwise would.
const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [],
  locale: [
    { value: 'en', label: 'English', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

const personalDataData = vi.fn<() => PersonalDataCard | null | undefined>()
vi.mock('@/features/personal-data/use-personal-data', () => ({
  usePersonalDataByOwner: () => ({
    data: personalDataData(),
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

/**
 * Controllable stand-in for the network-backed single select: exposes
 * `resource` and the hydrated `selectedItem` label so tests can assert wiring
 * without a real `/for-select` fetch (covered by the component's own tests).
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    selectedItem,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    selectedItem?: { id: number; label: string } | null
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`resource-${labels.triggerLabel}`}>{resource}</span>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`selected-label-${labels.triggerLabel}`}>{selectedItem?.label ?? ''}</span>
      <button type="button" onClick={() => onChange(1)}>{`pick-${labels.triggerLabel}`}</button>
    </div>
  ),
}))

/**
 * Controllable stand-in for the network-backed multi-select: exposes
 * `resource`, the current `value` array and the hydrated `selectedItems`
 * labels, plus a button that appends id 11 to the selection so a test can
 * drive the RHF field without a real `/for-select` fetch.
 */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    resource,
    value,
    onChange,
    selectedItems,
    labels,
  }: {
    resource: string
    value: number[]
    onChange: (value: number[]) => void
    selectedItems: { id: number; label: string }[]
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`resource-${labels.triggerLabel}`}>{resource}</span>
      <span data-testid={`value-${labels.triggerLabel}`}>{value.join(',')}</span>
      <span data-testid={`selected-labels-${labels.triggerLabel}`}>
        {selectedItems.map((item) => item.label).join(',')}
      </span>
      <button type="button" onClick={() => onChange([...value, 11])}>{`pick-${labels.triggerLabel}`}</button>
    </div>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function user(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    created_at: null,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

const BASE_EMPLOYMENT: EmploymentDetail = {
  id: 1,
  is_manager: false,
  job_description: null,
  relationship_type: null,
  qualification_type: null,
  hired_at: null,
  terminated_at: null,
  standard_daily_minutes: null,
  break_daily_minutes: null,
  reports_to_id: null,
  company_id: null,
  primary_operational_site_id: 8,
  remote_operational_site_ids: [9, 10],
  reports_to: null,
  company: null,
  primary_operational_site: { id: 8, label: 'Via Roma 1' },
  remote_operational_sites: [
    { id: 9, label: 'Via Milano 2' },
    { id: 10, label: 'Via Torino 3' },
  ],
}

function userWithSites(): UserDetailWithPermissions {
  return user({ employment: BASE_EMPLOYMENT })
}

/** Same fixture, with no remote sites: the empty-state branch (AC-030). */
function userWithoutRemoteSites(): UserDetailWithPermissions {
  return user({
    employment: { ...BASE_EMPLOYMENT, remote_operational_site_ids: [], remote_operational_sites: [] },
  })
}

/**
 * A role that sees `employment.primary_operational_site_id` but not
 * `employment.remote_operational_site_ids` (AC-029): every other field falls
 * back to `useResourcePermissions()`'s permissive default (visible/editable),
 * only the remote-sites key is explicitly hidden.
 */
function permissionsWithOnlyPrimarySiteVisible(): ResourcePermissions {
  return {
    ...FULL_ACCESS_PERMISSIONS,
    fields: {
      'employment.remote_operational_site_ids': {
        visible: false,
        hidden: true,
        editable: false,
        required: false,
        disabled: true,
        readonly: false,
      },
    },
  }
}

/** A valid, seeded personal-data card, needed so an edit-mode submit is not blocked by AC-014's mandatory-fields gate. */
const validCard: PersonalDataCard = {
  id: 99,
  type: 'individual',
  first_name: 'Ada',
  last_name: 'Lovelace',
  company_name: null,
  full_name: 'Ada Lovelace',
  ceo: null,
  tax_code: null,
  vat_number: null,
  sdi_code: null,
  birth_date: null,
  birth_city_id: null,
  residence_city_id: null,
  gender: null,
  personable_type: 'user',
  personable_id: 7,
  contacts: [],
  addresses: [],
  created_at: null,
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  fetchUserMock.mockReset()
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  createUserMock.mockResolvedValue(user())
  updateUserMock.mockResolvedValue(user())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
  formMetaPermissions.mockReset()
  formMetaPermissions.mockReturnValue(FULL_ACCESS_PERMISSIONS)
})

describe('UserForm — physical site + remote sites (spec 0103 AC-028)', () => {
  it('renders a single-select for the physical site and a multi-select for the remote sites, both bound to the operational-sites resource', () => {
    render(
      <UserForm mode={{ type: 'edit', user: userWithSites() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('resource-Physical site')).toHaveTextContent('operational-sites')
    expect(screen.getByTestId('selected-label-Physical site')).toHaveTextContent('Via Roma 1')

    expect(screen.getByTestId('resource-Remote sites')).toHaveTextContent('operational-sites')
    expect(screen.getByTestId('selected-labels-Remote sites')).toHaveTextContent(
      'Via Milano 2,Via Torino 3',
    )
  })

  it('writes both keys in the payload on save', async () => {
    personalDataData.mockReturnValue(validCard)
    render(
      <UserForm mode={{ type: 'edit', user: userWithSites() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    const payload = updateUserMock.mock.calls[0][1]
    expect(payload.employment.primary_operational_site_id).toBe(8)
    expect(payload.employment.remote_operational_site_ids).toEqual([9, 10])
  })

  it('appends a picked site to the remote selection without replacing it', async () => {
    personalDataData.mockReturnValue(validCard)
    render(
      <UserForm mode={{ type: 'edit', user: userWithSites() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByText('pick-Remote sites'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    const payload = updateUserMock.mock.calls[0][1]
    expect(payload.employment.remote_operational_site_ids).toEqual([9, 10, 11])
  })
})

describe('UserForm — remote sites field permission gate (spec 0103 AC-029)', () => {
  it('does not render the remote-sites multi-select for a role that only sees the physical site', () => {
    formMetaPermissions.mockReturnValue(permissionsWithOnlyPrimarySiteVisible())

    render(
      <UserForm mode={{ type: 'edit', user: userWithSites() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('resource-Physical site')).toBeInTheDocument()
    expect(screen.queryByText('Remote sites')).not.toBeInTheDocument()
    expect(screen.queryByTestId('resource-Remote sites')).not.toBeInTheDocument()
  })
})

describe('UserDetailView — physical + remote sites (spec 0103 AC-030)', () => {
  function renderDetail(userDetail: UserDetailWithPermissions) {
    fetchUserMock.mockResolvedValue(userDetail)
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <UserDetailView userId={userDetail.id} />
      </QueryClientProvider>,
    )
  }

  it('shows the physical site and every remote site, distinguishably', async () => {
    renderDetail(userWithSites())

    await waitFor(() => expect(screen.getByText('Via Roma 1')).toBeInTheDocument())
    const employmentSection = screen.getByText('Employment').closest('section') as HTMLElement
    expect(within(employmentSection).getByText('Physical site')).toBeInTheDocument()
    expect(within(employmentSection).getByText('Via Roma 1')).toBeInTheDocument()
    expect(within(employmentSection).getByText('Remote sites')).toBeInTheDocument()
    expect(within(employmentSection).getByText('Via Milano 2')).toBeInTheDocument()
    expect(within(employmentSection).getByText('Via Torino 3')).toBeInTheDocument()
  })

  it('falls back to the shared empty state when there are no remote sites', async () => {
    renderDetail(userWithoutRemoteSites())

    await waitFor(() => expect(screen.getByText('Via Roma 1')).toBeInTheDocument())
    const remoteSitesRow = screen.getByText('Remote sites').closest('div') as HTMLElement
    expect(within(remoteSitesRow).getByText('—')).toBeInTheDocument()
  })
})
