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
 * User directive 2026-09-11: competence and Sedi are ONE configuration — the
 * one that decides whether an offer can be matched to a person — so the form
 * gathers them in a single section and both surfaces tell the operator whether
 * the configuration actually works.
 *
 * What is asserted here is the WIRING of that verdict, not the rule itself
 * (`user-assignment.test.ts` owns the rule) nor the row editor
 * (`features/product-lines` owns it, stubbed below): the section groups the
 * three controls, the identity bar and the side column agree with the fields
 * live, the scheda counts them in its KPI strip, and a role that cannot see
 * the fields is told nothing about them.
 *
 * Same boilerplate shape as `user-form-employment-sites.test.tsx`, this
 * codebase's established per-concern test split.
 */

const fetchUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: vi.fn(),
  updateUser: vi.fn(),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true, view_activity: false },
}

const formMetaPermissions = vi.fn<() => ResourcePermissions>(() => FULL_ACCESS_PERMISSIONS)
vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: formMetaPermissions() }),
}))

// The quick-create "+" (spec 0028) is gated by `Can`/`useAbilities`, which needs
// an `AuthProvider` this suite does not render: deny everything so it never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

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
 * The row editor has its own suite: here it only has to publish how many rows
 * the field holds and let a test append a COMPLETE one, which is all the
 * verdict reads.
 */
vi.mock('@/features/product-lines/product-lines-field', () => ({
  ProductLinesField: ({
    value,
    onChange,
  }: {
    value: { business_function_id: number | null; product_category_id: number | null }[]
    onChange: (rows: { business_function_id: number | null; product_category_id: number | null }[]) => void
  }) => (
    <div>
      <output aria-label="competence rows">{value.length}</output>
      <button
        type="button"
        onClick={() => onChange([...value, { business_function_id: 4, product_category_id: 21 }])}
      >
        add competence
      </button>
    </div>
  ),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    onChange,
    labels,
  }: {
    resource: string
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`resource-${labels.triggerLabel}`}>{resource}</span>
      <button type="button" onClick={() => onChange(8)}>{`pick-${labels.triggerLabel}`}</button>
    </div>
  ),
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    resource,
    value,
    onChange,
    labels,
  }: {
    resource: string
    value: number[]
    onChange: (value: number[]) => void
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`resource-${labels.triggerLabel}`}>{resource}</span>
      <button type="button" onClick={() => onChange([...value, 9])}>{`pick-${labels.triggerLabel}`}</button>
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

const UNCONFIGURED_EMPLOYMENT: EmploymentDetail = {
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
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  product_lines: [],
  reports_to: null,
  company: null,
  primary_operational_site: null,
  remote_operational_sites: [],
}

const CONFIGURED_EMPLOYMENT: EmploymentDetail = {
  ...UNCONFIGURED_EMPLOYMENT,
  primary_operational_site_id: 8,
  remote_operational_site_ids: [9, 10],
  product_lines: [
    {
      id: 91,
      business_function: { id: 4, name: 'Sales' },
      product_category: { id: 21, name: 'Photovoltaic' },
    },
  ],
  primary_operational_site: { id: 8, label: 'Via Roma 1' },
  remote_operational_sites: [
    { id: 9, label: 'Via Milano 2' },
    { id: 10, label: 'Via Torino 3' },
  ],
}

function user(employment: EmploymentDetail): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    created_at: null,
    employment,
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

/** A role that sees none of the three assignment fields. */
function permissionsWithAssignmentHidden(): ResourcePermissions {
  const hidden = {
    visible: false,
    hidden: true,
    editable: false,
    required: false,
    disabled: true,
    readonly: false,
  }
  return {
    ...FULL_ACCESS_PERMISSIONS,
    fields: {
      'employment.product_lines': hidden,
      'employment.primary_operational_site_id': hidden,
      'employment.remote_operational_site_ids': hidden,
    },
  }
}

function renderForm(employment: EmploymentDetail) {
  render(
    <UserForm mode={{ type: 'edit', user: user(employment) }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    { wrapper: wrapper() },
  )
}

/** The identity bar, where the one-word verdict lives. */
function identityBar(): HTMLElement {
  return screen.getByRole('banner')
}

/**
 * The form's verdict band. Scoped to the side column (`<aside>` =
 * `complementary`): the personal-data card raises its own `role="status"`
 * notices elsewhere on the same screen.
 */
function formVerdictBand(): HTMLElement {
  return within(screen.getByRole('complementary')).getByRole('status')
}

/** The scheda's band — the only `role="status"` on that surface. */
function detailVerdictBand(): HTMLElement {
  return screen.getByRole('status')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchUserMock.mockReset()
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
  formMetaPermissions.mockReset()
  formMetaPermissions.mockReturnValue(FULL_ACCESS_PERMISSIONS)
})

describe('UserForm — the assignment configuration is one section', () => {
  it('gathers the competence rows and BOTH Sedi in the same block', () => {
    renderForm(CONFIGURED_EMPLOYMENT)

    const section = screen.getByText('Assignment configuration').closest('section') as HTMLElement
    expect(within(section).getByLabelText('competence rows')).toHaveTextContent('1')
    expect(within(section).getByTestId('resource-Physical site')).toHaveTextContent(
      'operational-sites',
    )
    expect(within(section).getByTestId('resource-Remote sites')).toHaveTextContent(
      'operational-sites',
    )
  })
})

describe('UserForm — live assignment verdict', () => {
  it('refuses nothing but says the person is unreachable, and names BOTH reasons', () => {
    renderForm(UNCONFIGURED_EMPLOYMENT)

    expect(within(identityBar()).getByText('Not assignable')).toBeInTheDocument()
    expect(formVerdictBand()).toHaveTextContent(/No competence configured/)
    expect(formVerdictBand()).toHaveTextContent(/No operational site/)
    // The save is never blocked by the verdict: it is guidance, not a gate.
    expect(within(identityBar()).getByRole('button', { name: 'Save' })).toBeEnabled()
  })

  it('flips to assignable as soon as a competence row and a Sede are set', async () => {
    renderForm(UNCONFIGURED_EMPLOYMENT)

    fireEvent.click(screen.getByRole('button', { name: 'add competence' }))
    fireEvent.click(screen.getByText('pick-Physical site'))

    await waitFor(() =>
      expect(within(identityBar()).getByText('Assignable')).toBeInTheDocument(),
    )
    expect(formVerdictBand()).not.toHaveTextContent(/No operational site/)
  })

  it('counts a REMOTE site as a full Sede, exactly as the server does', async () => {
    renderForm(UNCONFIGURED_EMPLOYMENT)

    fireEvent.click(screen.getByRole('button', { name: 'add competence' }))
    fireEvent.click(screen.getByText('pick-Remote sites'))

    await waitFor(() =>
      expect(within(identityBar()).getByText('Assignable')).toBeInTheDocument(),
    )
  })

  it('tells a role that cannot see the fields nothing at all about assignment', () => {
    formMetaPermissions.mockReturnValue(permissionsWithAssignmentHidden())
    renderForm(UNCONFIGURED_EMPLOYMENT)

    expect(screen.queryByText('Assignment configuration')).not.toBeInTheDocument()
    expect(screen.queryByText('Not assignable')).not.toBeInTheDocument()
    expect(
      within(screen.getByRole('complementary')).queryByRole('status'),
    ).not.toBeInTheDocument()
    // Not even the recap may name a field the actor is not allowed to see.
    expect(screen.queryByText('Physical site')).not.toBeInTheDocument()
    expect(screen.queryByText('Remote sites')).not.toBeInTheDocument()
  })
})

describe('UserDetailView — the assignment configuration on the scheda', () => {
  function renderDetail(employment: EmploymentDetail) {
    fetchUserMock.mockResolvedValue(user(employment))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <UserDetailView userId={7} />
      </QueryClientProvider>,
    )
  }

  it('counts competence and Sedi in the KPI strip, with no warning band when it all works', async () => {
    renderDetail(CONFIGURED_EMPLOYMENT)

    await waitFor(() => expect(screen.getByText('Ada Lovelace')).toBeInTheDocument())
    expect(screen.getByText('Competences').parentElement).toHaveTextContent('Competences')
    expect(screen.getByText('Matching').parentElement?.parentElement).toHaveTextContent('Assignable')
    expect(screen.getByText('1 physical · 2 remote')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('raises the band, and names the reason, for a person no record can reach', async () => {
    renderDetail(UNCONFIGURED_EMPLOYMENT)

    await waitFor(() => expect(screen.getByText('Ada Lovelace')).toBeInTheDocument())
    expect(detailVerdictBand()).toHaveTextContent(/No competence configured/)
    expect(detailVerdictBand()).toHaveTextContent(/No operational site/)
  })
})
