import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0111 AC-023/AC-024: the Profile section configures the user's
 * competence as ROWS ("funzione aziendale -> categoria prodotto") with the
 * shared `ProductLinesField`, gated by the single `employment.product_lines`
 * field-permission key and written to `employment.product_lines` in the save
 * payload. The row editor itself is covered by its own suite
 * (`features/product-lines`); what is asserted here is the user form's wiring:
 * hydration from the loaded pairs, the per-row category scoping, the opted-out
 * `single` row cap (D-5) and the payload. Same boilerplate shape as
 * `user-form-employment-sites.test.tsx` (this codebase's established
 * per-concern test split, `.claude/rules/engineering.md` §6).
 */

const SALES_FUNCTION = 4
const MARKETING_FUNCTION = 5
const PHOTOVOLTAIC_CATEGORY = 21
const HEAT_PUMPS_CATEGORY = 22
/** A `single`-mode category: the row cap it triggers elsewhere must NOT apply here (D-5). */
const AUDIT_CATEGORY = 23
const CAMPAIGNS_CATEGORY = 200

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
  fetchUser: vi.fn(),
}))

/**
 * The category picker reads the structural TREE, so the fixture IS a tree: an
 * unselectable root owning the Sales function with three children (one of them
 * `single`-mode), plus a second branch under Marketing. `vi.hoisted` because
 * the `vi.mock` factory below is hoisted above this module's consts.
 */
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

/**
 * Stand-in for the network-backed single select: publishes the bound value
 * under an accessible name and offers one button per selectable id, so a test
 * drives the row's business function without a real `/for-select` fetch
 * (covered by the component's own tests).
 */
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

/**
 * Picking a function whose label is unknown makes the row editor resolve it
 * with a one-shot `/for-select` lookup: stub the transport so no real request
 * escapes (the resolution itself is covered by the field's own suite).
 */
const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

/** The other employment multi-selects (roles, remote sites) are not this suite's concern. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <span>{`multi-${labels.triggerLabel}`}</span>
  ),
}))

/** Exposes the options the real scoping builder handed over, plus a picker per pickable one. */
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

/**
 * A role that does not see the competence rows: every other key falls back to
 * `useResourcePermissions()`'s permissive default.
 */
function permissionsWithCompetenceHidden(): ResourcePermissions {
  return {
    ...FULL_ACCESS_PERMISSIONS,
    fields: {
      'employment.product_lines': {
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

/** The employment object of the single PATCH the form fired. */
async function submittedEmployment() {
  await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
  return updateUserMock.mock.calls[0][1].employment
}

describe('UserForm — competence rows (spec 0111 AC-023)', () => {
  it('renders one row per persisted pair, hydrated from the loaded product_lines', () => {
    renderEditForm()

    expect(screen.getByLabelText('Business function 1 value')).toHaveTextContent(
      String(SALES_FUNCTION),
    )
    expect(screen.getByLabelText('Product category 1 value')).toHaveTextContent(
      String(PHOTOVOLTAIC_CATEGORY),
    )
  })

  it('adds a row, scopes its category to the chosen function and writes the pair to the payload', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    // Before a function is chosen the category picker has nothing to offer.
    expect(screen.getByLabelText('Product category 2 disabled')).toHaveTextContent('true')

    fireEvent.click(
      screen.getByRole('button', { name: `select Business function 2 ${MARKETING_FUNCTION}` }),
    )
    // Only the Marketing branch is pickable for that function.
    expect(screen.getByLabelText('Product category 2 options')).toHaveTextContent(
      String(CAMPAIGNS_CATEGORY),
    )
    expect(screen.getByLabelText('Product category 2 options')).not.toHaveTextContent(
      String(HEAT_PUMPS_CATEGORY),
    )

    fireEvent.click(
      screen.getByRole('button', { name: `select Product category 2 ${CAMPAIGNS_CATEGORY}` }),
    )
    save()

    expect((await submittedEmployment()).product_lines).toEqual([
      { business_function_id: SALES_FUNCTION, product_category_id: PHOTOVOLTAIC_CATEGORY },
      { business_function_id: MARKETING_FUNCTION, product_category_id: CAMPAIGNS_CATEGORY },
    ])
  })

  it('removes a row, and the pair leaves the payload with it', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('button', { name: 'Remove product line' }))
    save()

    expect((await submittedEmployment()).product_lines).toEqual([])
  })

  it('refuses the save while a row is half-filled, and sends nothing', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm()

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(
      screen.getByRole('button', { name: `select Business function 2 ${MARKETING_FUNCTION}` }),
    )
    save()

    await waitFor(() =>
      expect(
        screen.getByText('Each row requires both a business function and a product category.'),
      ).toBeInTheDocument(),
    )
    expect(updateUserMock).not.toHaveBeenCalled()
  })

  it('D-8 — sends an empty array for a user with no competence at all', async () => {
    personalDataData.mockReturnValue(validCard)
    renderEditForm(userWithCompetence({ ...BASE_EMPLOYMENT, product_lines: [] }))

    save()

    expect((await submittedEmployment()).product_lines).toEqual([])
  })

  it('AC-024 — keeps "Add" enabled on a single-mode category (the card cap is opted out)', () => {
    renderEditForm(
      userWithCompetence({
        ...BASE_EMPLOYMENT,
        product_lines: [
          {
            id: 92,
            business_function: { id: SALES_FUNCTION, name: 'Sales' },
            product_category: { id: AUDIT_CATEGORY, name: 'Audit' },
          },
        ],
      }),
    )

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('surfaces a per-row 422 under the field, since no RHF path matches the indexed key', async () => {
    personalDataData.mockReturnValue(validCard)
    updateUserMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: {
            'employment.product_lines.0.business_function_id': [
              'That category does not belong to the chosen function.',
            ],
          },
        },
      } as never),
    )
    renderEditForm()

    save()

    await waitFor(() =>
      expect(
        screen.getByText('That category does not belong to the chosen function.'),
      ).toBeInTheDocument(),
    )
  })

  it('does not render the field for a role whose matrix hides it', () => {
    formMetaPermissions.mockReturnValue(permissionsWithCompetenceHidden())

    renderEditForm()

    expect(screen.queryByLabelText('Business function 1 value')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add product line' })).not.toBeInTheDocument()
  })
})
