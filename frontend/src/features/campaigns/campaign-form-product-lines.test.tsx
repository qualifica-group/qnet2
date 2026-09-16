import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { campaigns as campaignsEn } from '@/i18n/locales/en-campaigns'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0132 (AC-045): the campaign form's `product_lines` row editor (shared
 * `ProductLinesField`) for a STANDALONE campaign, plus the Sede field — split
 * out of `campaign-form-body.test.tsx` to stay within the 500-line hard limit
 * (engineering.md §6). The linked (BR-2 read-only, AC-046) behaviour lives in
 * `campaign-project-link.test.tsx`.
 */

const createCampaignMock = vi.fn()
const updateCampaignMock = vi.fn()

/**
 * `useProductLinesField` (inside the real, unmocked `ProductLinesField`)
 * reads the category tree directly — for `rootCategoryFor`'s edit-mode
 * resolution (spec 0132 AC-017) to find category 4 under a root, the tree
 * fetch needs a real answer here, unlike the two row pickers themselves
 * (mocked below with clickable doubles).
 */
function node(overrides: Partial<ProductCategoryTreeNode> & { id: number }): ProductCategoryTreeNode {
  return {
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    ...overrides,
  }
}

const CATEGORY_TREE: ProductCategoryTreeNode[] = [
  node({ id: 2, name: 'Hardware root', children: [node({ id: 4, name: 'Hardware', parent_id: 2 })] }),
]

vi.mock('@/features/product-categories/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/product-categories/api')>(
    '@/features/product-categories/api',
  )
  return {
    ...actual,
    fetchProductCategoryTree: () => Promise.resolve(CATEGORY_TREE),
  }
})
const fetchCampaignNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/campaigns/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/campaigns/api')>(
    '@/features/campaigns/api',
  )
  return {
    ...actual,
    createCampaign: (...args: unknown[]) => createCampaignMock(...args),
    updateCampaign: (...args: unknown[]) => updateCampaignMock(...args),
    fetchCampaignNextCode: () => fetchCampaignNextCodeMock(),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: vi.fn().mockResolvedValue({
      items: [],
      pagination: { offset: 0, limit: 25, total: 0 },
      export_link: null,
    }),
  }
})

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchSystemStatusIdMock = vi.fn<() => Promise<number | null>>()
vi.mock('@/features/status-reorder/api', () => ({
  fetchSystemStatusId: () => fetchSystemStatusIdMock(),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      disabled={disabled}
      data-testid={`select-${labels.triggerLabel}`}
      onClick={() => onChange(3)}
    >
      {value ?? ''}
    </button>
  ),
}))

/** Local, clickable double (mirrors `AsyncPaginatedSelect` above): the shared read-only stub exposes no "pick" affordance. */
vi.mock('@/features/product-lines/product-category-tree-select', () => ({
  ProductCategoryTreeSelect: ({
    value,
    onChange,
    scope,
    disabled,
    triggerLabel,
  }: {
    value: number | null
    onChange: (id: number) => void
    scope: { kind: 'root'; rootCategoryId: number | null }
    disabled?: boolean
    triggerLabel: string
  }) => {
    const isDisabled = Boolean(disabled) || scope.rootCategoryId === null
    return (
      <div>
        <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
        <span data-testid={`disabled-${triggerLabel}`}>{String(isDisabled)}</span>
        <button
          type="button"
          disabled={isDisabled}
          data-testid={`select-${triggerLabel}`}
          onClick={() => onChange(4)}
        >
          {`select ${triggerLabel}`}
        </button>
      </div>
    )
  },
}))

/** The row's FIRST step (spec 0132): same clickable-double style as the category picker above. */
vi.mock('@/features/product-lines/product-category-root-select', () => ({
  ProductCategoryRootSelect: ({
    value,
    onChange,
    disabled,
    triggerLabel,
  }: {
    value: number | null
    onChange: (rootCategoryId: number) => void
    disabled?: boolean
    triggerLabel: string
  }) => (
    <button
      type="button"
      disabled={disabled}
      data-testid={`select-${triggerLabel}`}
      onClick={() => onChange(3)}
    >
      {value ?? ''}
    </button>
  ),
}))

vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
  }: {
    value: { country_id: number | null }
    onChange: (next: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }) => void
  }) => (
    <button
      type="button"
      data-testid="geo-select"
      data-country={value.country_id ?? ''}
      onClick={() => onChange({ country_id: 10, state_id: null, province_id: null, city_id: null })}
    >
      geo
    </button>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function campaign(
  overrides: Partial<CampaignDetailWithPermissions> = {},
): CampaignDetailWithPermissions {
  return {
    id: 9,
    code: 'CMP-0009',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    derived_from_project: false,
    pipeline_status_id: 1,
    pipeline_status: { id: 1, name: 'Active', color: 'blue' },
    country_id: 10,
    country: { id: 10, name: 'Italy' },
    state_id: 3,
    state: { id: 3, name: 'Lombardy' },
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'state',
    geo_locked_levels: [],
    product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Hardware' } }],
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { campaigns: campaignsEn }, true, true)
})

beforeEach(() => {
  createCampaignMock.mockReset()
  updateCampaignMock.mockReset()
  fetchCampaignNextCodeMock.mockReset()
  fetchCampaignNextCodeMock.mockResolvedValue('CMP-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
})

function fillRequiredDates() {
  fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
  fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
  fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
}

function fillRequiredClassification() {
  fireEvent.click(screen.getByTestId('select-Parent category 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
}

describe('CampaignForm — product_lines, standalone (spec 0132, AC-045)', () => {
  it('opens the create form on ONE empty row', async () => {
    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Parent category 1')).toBeInTheDocument())
    expect(screen.queryByTestId('select-Parent category 2')).not.toBeInTheDocument()
  })

  it('disables the row category until its own root category is chosen, then enables it', async () => {
    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeInTheDocument())
    expect(screen.getByTestId('select-Product category 1')).toBeDisabled()

    fireEvent.click(screen.getByTestId('select-Parent category 1'))

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeEnabled())
  })

  it('leaves the row category enabled in edit mode of a standalone campaign', async () => {
    render(
      <CampaignForm mode={{ type: 'edit', campaign: campaign() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeInTheDocument())
    expect(screen.getByTestId('select-Product category 1')).toBeEnabled()
  })

  it('sends every complete row on a standalone create submit', async () => {
    createCampaignMock.mockResolvedValue(campaign())

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Spring push' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fillRequiredClassification()
    fireEvent.click(screen.getByTestId('geo-select'))
    fillRequiredDates()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0] as Record<string, unknown>
    // Spec 0132 AC-018: only `product_category_id` travels — the picked root
    // (`select-Parent category 1`) is UI-only state, never sent.
    expect(payload.product_lines).toEqual([{ product_category_id: 4 }])
  })
})

describe('CampaignForm — Sede (operational site)', () => {
  it('renders the Site field, always editable, and pre-fills it from the loaded campaign in edit mode', async () => {
    render(
      <CampaignForm
        mode={{
          type: 'edit',
          campaign: campaign({ operational_site_id: 8, operational_site: { id: 8, label: 'Warehouse A' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    expect(screen.getByTestId('select-Site')).toHaveTextContent('8')
    expect(screen.getByTestId('select-Site')).not.toBeDisabled()
  })

  it('sends the picked operational_site_id on a standalone create', async () => {
    createCampaignMock.mockResolvedValue(campaign())

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Spring push' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fillRequiredClassification()
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByTestId('select-Site'))
    fillRequiredDates()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.operational_site_id).toBe(3)
  })
})
