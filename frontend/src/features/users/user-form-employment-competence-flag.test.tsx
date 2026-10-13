import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailView } from '@/features/users/user-detail'
import { confirmOpenRow, pencilOf, userFixture, userWrapper } from '@/features/users/user-test-fixtures'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'
import type { ResourcePermissions, ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'

/**
 * REQUIREMENT CHANGED (spec 0222): the switches and the row editor are three
 * in-place rows of the detail, each saved on its own.
 *
 * Spec 0129 D-1/D-2 (AC-020), D-3/D-5 (AC-021), D-6/D-7 (AC-022), AC-023: the
 * "competent for all categories" switch, the per-row "All" checkbox and the
 * container category `CompetenceLinesField` makes
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

const fetchUserMock = vi.fn()

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => null }))

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
    is_active: true,
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
        is_active: true,
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
  reports_to_ids: [],
  company_id: null,
  primary_operational_site_id: null,
  remote_operational_site_ids: [],
  is_assignable: true,
  covers_all_product_categories: false,
  product_lines: [
    {
      id: 91,
      business_function: { id: SALES_FUNCTION, name: 'Sales' },
      product_category: { id: PHOTOVOLTAIC_CATEGORY, name: 'Photovoltaic' },
    },
  ],
  reports_to: [],
  company: null,
  primary_operational_site: null,
  remote_operational_sites: [],
}

function userWithCompetence(employment: EmploymentDetail = BASE_EMPLOYMENT): UserDetailWithPermissions {
  return userFixture({ permissions: FULL_ACCESS_PERMISSIONS, employment })
}


beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  fetchUserMock.mockReset()
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

/** Renders the detail and opens the row labelled `row`. */
async function openRow(row: string, user: UserDetailWithPermissions = userWithCompetence()) {
  fetchUserMock.mockResolvedValue(user)
  const { wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  fireEvent.click(await screen.findByRole('button', { name: `Edit ${row}` }))
}

async function submittedEmployment() {
  await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
  return updateUserMock.mock.calls[0][1].employment
}

const COVERS_ALL_SWITCH_NAME = 'Competent for all categories'

describe('UserDetailView — is_assignable row (spec 0194)', () => {
  it('AC-008 — on by default; switching it off submits false and keeps the competence rows', async () => {
    await openRow('Assignable')

    const assignable = screen.getByRole('switch', { name: 'Assignable' })
    expect(assignable).toBeChecked()
    fireEvent.click(assignable)
    confirmOpenRow('Save')

    const employment = await submittedEmployment()
    expect(employment.is_assignable).toBe(false)
    expect(employment.covers_all_product_categories).toBe(false)
    expect(employment.product_lines).toEqual([{ business_function_id: SALES_FUNCTION, product_category_id: PHOTOVOLTAIC_CATEGORY }])
  })
})

describe('UserDetailView — covers_all_product_categories & "all categories" rows (spec 0129)', () => {
  it('AC-020 — activating the switch submits the flag with the rows cleared', async () => {
    await openRow(COVERS_ALL_SWITCH_NAME)

    fireEvent.click(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME }))
    confirmOpenRow('Save')

    const employment = await submittedEmployment()
    expect(employment.covers_all_product_categories).toBe(true)
    expect(employment.product_lines).toEqual([])
  })

  it('AC-020 — deactivating the switch leaves no dormant rows behind', async () => {
    await openRow(
      COVERS_ALL_SWITCH_NAME,
      userWithCompetence({ ...BASE_EMPLOYMENT, covers_all_product_categories: true, product_lines: [] }),
    )

    fireEvent.click(screen.getByRole('switch', { name: COVERS_ALL_SWITCH_NAME }))
    confirmOpenRow('Save')

    const employment = await submittedEmployment()
    expect(employment.covers_all_product_categories).toBe(false)
    expect(employment.product_lines).toEqual([])
  })

  it('AC-021 — checking "All" on a row disables/clears the category, and the row is not incomplete', async () => {
    await openRow('Competence', userWithCompetence({ ...BASE_EMPLOYMENT, product_lines: [] }))

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${SALES_FUNCTION}` }))
    fireEvent.click(screen.getByRole('checkbox'))

    expect(screen.getByRole('checkbox')).toBeChecked()
    expect(screen.getByLabelText('Product category 1 disabled')).toHaveTextContent('true')

    confirmOpenRow('Save')

    expect((await submittedEmployment()).product_lines).toEqual([
      { business_function_id: SALES_FUNCTION, product_category_id: null },
    ])
  })

  it('AC-022 — a container category (is_selectable=false) is pickable in this variant', async () => {
    await openRow('Competence')

    expect(screen.getByLabelText('Product category 1 options')).toHaveTextContent(
      `100,${PHOTOVOLTAIC_CATEGORY},${HEAT_PUMPS_CATEGORY},${AUDIT_CATEGORY}`,
    )
  })

  it('AC-023 — a persisted (function, null) row opens with "All" checked', async () => {
    await openRow(
      'Competence',
      userWithCompetence({
        ...BASE_EMPLOYMENT,
        product_lines: [{ id: 93, business_function: { id: SALES_FUNCTION, name: 'Sales' }, product_category: null }],
      }),
    )

    expect(screen.getByRole('checkbox')).toBeChecked()
    expect(screen.getByLabelText('Product category 1 disabled')).toHaveTextContent('true')
  })

  it('AC-023 — a user with the flag shows it on and has no competence row', async () => {
    fetchUserMock.mockResolvedValue(
      userWithCompetence({ ...BASE_EMPLOYMENT, covers_all_product_categories: true, product_lines: [] }),
    )
    const { wrapper } = userWrapper()
    render(<UserDetailView userId={7} />, { wrapper })

    expect(await screen.findByRole('button', { name: `Edit ${COVERS_ALL_SWITCH_NAME}` })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Competence' })).not.toBeInTheDocument()
    expect(pencilOf('Assignable')).toBeInTheDocument()
  })
})
