import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'
import type { ResourcePermissions, ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0129 D-1/D-2 (AC-020), D-3/D-5 (AC-021), D-6/D-7 (AC-022), AC-023: the
 * "competent for all categories" switch, the per-row "All" checkbox and the
 * container category the competence variant of `ProductLinesField` makes
 * pickable. Split out of `user-form-employment-competence.test.tsx` (spec
 * 0111) purely to stay under the file-size limit (`engineering.md` §6); same
 * boilerplate shape and fixtures.
 */

const SALES_FUNCTION = 4
const PHOTOVOLTAIC_CATEGORY = 21
const HEAT_PUMPS_CATEGORY = 22
const AUDIT_CATEGORY = 23

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: vi.fn(),
}))

/** Mirrors `user-form-employment-competence.test.tsx`'s fixture: an unselectable root owning Sales with three children. */
const { CATEGORY_TREE } = vi.hoisted(() => {
  const node = (overrides: Record<string, unknown>) => ({
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    ...overrides,
  })

  return {
    CATEGORY_TREE: [
      node({
        id: 100,
        name: 'Energy',
        business_function_id: 4,
        is_selectable: false,
        children: [
          node({ id: 21, name: 'Photovoltaic', parent_id: 100 }),
          node({ id: 22, name: 'Heat pumps', parent_id: 100 }),
          node({ id: 23, name: 'Audit', parent_id: 100, management_mode: 'single' }),
        ],
      }),
      node({ id: 200, name: 'Campaigns', business_function_id: 5 }),
    ],
  }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({
    data: CATEGORY_TREE,
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true, view_activity: false },
}

vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

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

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <div>
      <output aria-label={`${labels.triggerLabel} value`}>{value ?? ''}</output>
      {[4, 5].map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <span>{`multi-${labels.triggerLabel}`}</span>
  ),
}))

vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    options,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    options: { id: number; name: string; disabled?: boolean }[]
    disabled?: boolean
    labels: { triggerLabel?: string }
  }) => (
    <div>
      <output aria-label={`${labels.triggerLabel} value`}>{value ?? ''}</output>
      <output aria-label={`${labels.triggerLabel} disabled`}>{String(Boolean(disabled))}</output>
      <output aria-label={`${labels.triggerLabel} options`}>
        {options.map((option) => `${option.id}${option.disabled ? ':disabled' : ''}`).join(',')}
      </output>
      {options
        .filter((option) => !option.disabled)
        .map((option) => (
          <button key={option.id} type="button" onClick={() => onChange(option.id)}>
            {`select ${labels.triggerLabel} ${option.id}`}
          </button>
        ))}
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
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  covers_all_product_categories: false,
  product_lines: [
    {
      id: 91,
      business_function: { id: SALES_FUNCTION, name: 'Sales' },
      product_category: { id: PHOTOVOLTAIC_CATEGORY, name: 'Photovoltaic' },
    },
  ],
  reports_to: null,
  company: null,
  primary_operational_site: null,
  remote_operational_sites: [],
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
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: [],
    pagination: { offset: 0, limit: 25, total: 0 },
    export_link: null,
  })
})

function renderEditForm(user: UserDetailWithPermissions = userWithCompetence()) {
  render(<UserForm mode={{ type: 'edit', user }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
}

function save() {
  fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
}

async function submittedEmployment() {
  await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
  return updateUserMock.mock.calls[0][1].employment
}

const COVERS_ALL_SWITCH_NAME = 'Competent for all categories'

describe('UserForm — covers_all_product_categories & "all categories" rows (spec 0129)', () => {
  it('AC-020 — activating the switch hides the row editor and submits the flag with empty rows', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME }))

    expect(screen.queryByLabelText('Business function 1 value')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add product line' })).not.toBeInTheDocument()

    save()

    const employment = await submittedEmployment()
    expect(employment.covers_all_product_categories).toBe(true)
    expect(employment.product_lines).toEqual([])
  })

  it('AC-020 — deactivating the switch reopens an EMPTY editor, not the rows it had before', () => {
    renderEditForm()

    fireEvent.click(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME }))
    fireEvent.click(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME }))

    expect(screen.queryByLabelText('Business function 1 value')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('AC-021 — checking "All" on a row disables/clears the category, and the row is not incomplete', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm(userWithCompetence({ ...BASE_EMPLOYMENT, product_lines: [] }))

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${SALES_FUNCTION}` }))
    fireEvent.click(screen.getByRole('checkbox'))

    expect(screen.getByRole('checkbox')).toBeChecked()
    expect(screen.getByLabelText('Product category 1 disabled')).toHaveTextContent('true')

    save()

    expect((await submittedEmployment()).product_lines).toEqual([
      { business_function_id: SALES_FUNCTION, product_category_id: null },
    ])
  })

  it('AC-022 — a container category (is_selectable=false) is pickable in this variant', () => {
    renderEditForm()

    expect(screen.getByLabelText('Product category 1 options')).toHaveTextContent(
      `100,${PHOTOVOLTAIC_CATEGORY},${HEAT_PUMPS_CATEGORY},${AUDIT_CATEGORY}`,
    )
  })

  it('AC-023 — a persisted (function, null) row opens with "All" checked', () => {
    renderEditForm(
      userWithCompetence({
        ...BASE_EMPLOYMENT,
        product_lines: [
          { id: 93, business_function: { id: SALES_FUNCTION, name: 'Sales' }, product_category: null },
        ],
      }),
    )

    expect(screen.getByRole('checkbox')).toBeChecked()
    expect(screen.getByLabelText('Product category 1 disabled')).toHaveTextContent('true')
  })

  it('AC-023 — a user with the flag opens with the switch active and the editor hidden', () => {
    renderEditForm(
      userWithCompetence({ ...BASE_EMPLOYMENT, covers_all_product_categories: true, product_lines: [] }),
    )

    expect(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME })).toBeChecked()
    expect(screen.queryByLabelText('Business function 1 value')).not.toBeInTheDocument()
  })
})
