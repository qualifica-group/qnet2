import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0110 (assignment competence), microtask MT-5 / AC-040: the Profile
 * section carries a "Product categories" multi-select next to the business
 * function, hydrated from `employment.product_categories` in edit mode, gated
 * by its own field-permission key, and written to
 * `employment.product_category_ids` in the save payload. Same boilerplate
 * shape as `user-form-employment-sites.test.tsx` (this codebase's established
 * per-concern test split, `.claude/rules/engineering.md` §6).
 */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: vi.fn(),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true, view_activity: false },
}

/** Controllable per-test, defaulting to full access (the gate case overrides it). */
const formMetaPermissions = vi.fn<() => ResourcePermissions>(() => FULL_ACCESS_PERMISSIONS)
vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: formMetaPermissions() }),
}))

// The quick-create "+" (spec 0028) is gated by `Can`/`useAbilities`, which needs
// an `AuthProvider` this suite does not render: deny everything so it never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

// `useCustomFieldsForm` (spec 0021) reads the resource meta directly, bypassing
// `useUserFormMeta`: stub it with no custom fields defined.
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

/** Stand-in for the network-backed single select (business function, reports to). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <span>{`single-${labels.triggerLabel}`}</span>
  ),
}))

/**
 * Stand-in for the network-backed multi-select: publishes the bound `resource`,
 * the current field value and the hydrated labels under accessible names, plus
 * a button appending id 11 so a test can drive the RHF field without a real
 * `/for-select` fetch (covered by the component's own tests).
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
      <output aria-label={`${labels.triggerLabel} resource`}>{resource}</output>
      <output aria-label={`${labels.triggerLabel} value`}>{value.join(',')}</output>
      <output aria-label={`${labels.triggerLabel} hydrated`}>
        {selectedItems.map((item) => item.label).join(',')}
      </output>
      <button type="button" onClick={() => onChange([...value, 11])}>
        {`pick-${labels.triggerLabel}`}
      </button>
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
  business_function_id: 4,
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  product_category_ids: [21, 22],
  reports_to: null,
  business_function: { id: 4, label: 'Sales' },
  company: null,
  primary_operational_site: null,
  remote_operational_sites: [],
  product_categories: [
    { id: 21, label: 'Photovoltaic' },
    { id: 22, label: 'Heat pumps' },
  ],
}

function userWithCompetence(
  employment: EmploymentDetail = BASE_EMPLOYMENT,
): UserDetailWithPermissions {
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
    employment,
  }
}

/**
 * A role that sees the business function but not the competence field: every
 * other key falls back to `useResourcePermissions()`'s permissive default.
 */
function permissionsWithCompetenceHidden(): ResourcePermissions {
  return {
    ...FULL_ACCESS_PERMISSIONS,
    fields: {
      'employment.product_category_ids': {
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

/** A valid, seeded card, so an edit-mode submit is not blocked by the mandatory-identity gate. */
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
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  createUserMock.mockResolvedValue(userWithCompetence())
  updateUserMock.mockResolvedValue(userWithCompetence())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
  formMetaPermissions.mockReset()
  formMetaPermissions.mockReturnValue(FULL_ACCESS_PERMISSIONS)
})

function renderEditForm(user: UserDetailWithPermissions = userWithCompetence()) {
  render(<UserForm mode={{ type: 'edit', user }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
}

describe('UserForm — product-category competence (spec 0110 AC-040)', () => {
  it('renders the competence multi-select bound to the product-categories resource, prefilled with the current categories', () => {
    renderEditForm()

    expect(screen.getByLabelText('Product categories resource')).toHaveTextContent(
      'product-categories',
    )
    expect(screen.getByLabelText('Product categories value')).toHaveTextContent('21,22')
    expect(screen.getByLabelText('Product categories hydrated')).toHaveTextContent(
      'Photovoltaic,Heat pumps',
    )
  })

  it('writes employment.product_category_ids in the save payload', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment.product_category_ids).toEqual([21, 22])
  })

  it('appends a picked category to the selection instead of replacing it', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('button', { name: 'pick-Product categories' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment.product_category_ids).toEqual([21, 22, 11])
  })

  it('sends an empty array when the user clears the selection', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm(
      userWithCompetence({ ...BASE_EMPLOYMENT, product_category_ids: [], product_categories: [] }),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    expect(updateUserMock.mock.calls[0][1].employment.product_category_ids).toEqual([])
  })

  it('does not render the field for a role whose matrix hides it', () => {
    formMetaPermissions.mockReturnValue(permissionsWithCompetenceHidden())

    renderEditForm()

    expect(screen.queryByLabelText('Product categories resource')).not.toBeInTheDocument()
    expect(screen.queryByText('Product categories')).not.toBeInTheDocument()
  })
})
