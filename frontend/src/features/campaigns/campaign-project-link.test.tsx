import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { campaigns as campaignsEn } from '@/i18n/locales/en-campaigns'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ProjectForSelectMeta } from '@/features/projects/for-select-api'
import type { ProjectGeoMeta } from '@/features/campaigns/campaign-geo'

/**
 * Spec 0023 FRONTEND acceptance criteria:
 * - AC-042: picking a Project prefills Partner (editable) and
 *   forces the 3 classification fields read-only from the project's values,
 *   which are excluded from the payload regardless of what they display.
 * - AC-043: clearing the Project makes the 3 classification fields editable
 *   and required again (client validation error when left empty).
 * - BR-3: the backend's 422 budget message is shown verbatim, not a generic one.
 * Spec 0027 BR-5 (replaces BR-2 for geo, D-3 — REWRITTEN, not tampered: the
 * requirement changed): picking a project also prefills+locks the geo levels
 * the project fills (`<GeoSelect lockedLevels>`), excluded from the payload;
 * clearing the project unlocks and resets all four, re-requiring `country_id`.
 * AC-046 (the `code` field) lives in `campaign-form-body.test.tsx`.
 */

const TEST_PROJECT_ID = 42

const createCampaignMock = vi.fn()
const updateCampaignMock = vi.fn()
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

const fetchProjectsForSelectMock = vi.fn()
vi.mock('@/features/projects/for-select-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/projects/for-select-api')>(
    '@/features/projects/for-select-api',
  )
  return {
    ...actual,
    fetchProjectsForSelect: (...args: unknown[]) => fetchProjectsForSelectMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `project-form-body.test.tsx`), but ALSO exposes a "select"/"clear"
 * affordance per field so the Project picker's onChange (AC-042/AC-043) is
 * exercisable end to end.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <button type="button" onClick={() => onChange(TEST_PROJECT_ID)}>
        {`select ${labels.triggerLabel}`}
      </button>
      <button type="button" onClick={() => onChange(null)}>
        {`clear ${labels.triggerLabel}`}
      </button>
    </div>
  ),
}))

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03); these tests only read the PREFILLED value (never pick one
 * manually), so the shared read-only double is enough.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

/**
 * `GeoSelect` is covered by its own test; here a controllable read-only stub
 * exposes the wired value and `lockedLevels` so BR-5's prefill+lock (AC-042)
 * and unlock-on-unlink (AC-043) are observable without a real cascade.
 */
vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    lockedLevels,
  }: {
    value: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }
    lockedLevels?: readonly string[]
  }) => (
    <div
      data-testid="geo-select"
      data-country={value.country_id ?? ''}
      data-state={value.state_id ?? ''}
      data-province={value.province_id ?? ''}
      data-city={value.city_id ?? ''}
      data-locked={(lockedLevels ?? []).join(',')}
    />
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function projectForSelectItem(overrides: {
  meta?: Partial<ProjectForSelectMeta>
  geo?: Partial<ProjectGeoMeta>
} = {}) {
  return {
    id: TEST_PROJECT_ID,
    label: 'PRJ-0042 — Acme rollout',
    meta: {
      partner: { id: 31, label: 'Jane Partner' },
      pipeline_status: { id: 41, label: 'Active' },
      state: null,
      product_lines: [{ business_function: { id: 51, name: 'Marketing' }, product_category: { id: 71, name: 'Hardware' } }],
      total_budget: '1000.00',
      allocated_budget: '600.00',
      remaining_budget: '400.00',
      operational_site: { id: 81, label: 'Warehouse A' },
      ...overrides.meta,
      geo: {
        country: { id: 61, name: 'Italy' },
        state: null,
        province: null,
        city: null,
        ...overrides.geo,
      },
    },
  }
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
    country_id: 61,
    country: { id: 61, name: 'Italy' },
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
  fetchProjectsForSelectMock.mockReset()
  fetchProjectsForSelectMock.mockResolvedValue({
    items: [projectForSelectItem()],
    export_link: null,
    pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  })
})

describe('CampaignForm — selecting a Project (AC-042)', () => {
  it('prefills Partner, forces the 3 classification fields read-only, and locks the geo levels the project fills (BR-5)', async () => {
    createCampaignMock.mockResolvedValue(campaign({ project_id: TEST_PROJECT_ID }))

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Linked campaign' } })
    fireEvent.click(screen.getByRole('button', { name: 'select Project' }))

    await waitFor(() => expect(screen.getByTestId('value-Partner')).toHaveTextContent('31'))
    expect(screen.getByTestId('value-Status')).toHaveTextContent('41')
    // Spec 0094: the project's product_lines row(s), rendered read-only by
    // the shared ProductLinesField (AC-046).
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('51')
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('71')
    // The Sede is prefilled from the project's own meta (project -> campaign -> lead chain).
    expect(screen.getByTestId('value-Site')).toHaveTextContent('81')

    // Status and the product_lines row are forced read-only while linked; Partner/Sede stay editable.
    expect(screen.getByTestId('disabled-Status')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Business function 1')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Partner')).toHaveTextContent('false')
    expect(screen.getByTestId('disabled-Site')).toHaveTextContent('false')

    // BR-5: the project's own country is prefilled and locked; the rest stay editable/empty.
    await waitFor(() => expect(screen.getByTestId('geo-select')).toHaveAttribute('data-country', '61'))
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', 'country')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-state', '')

    // Dates are required even for a linked campaign (not inherited): fill them
    // in the collapsed Planning & budget section before submitting.
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('product_lines')
    expect(payload).not.toHaveProperty('country_id')
    expect(payload.project_id).toBe(TEST_PROJECT_ID)
    expect(payload.partner_id).toBe(31)
    expect(payload.operational_site_id).toBe(81)
  })

  it('prefills the SELECTED project meta, not items[0]: the for-select page returns other projects first, the picked one is appended', async () => {
    // The endpoint returns the first page (ordered by name) PLUS the requested
    // id appended at the end, so the picked project is NOT items[0]. A decoy
    // sits first with a different Sede; the prefill must read the picked one.
    const decoy = {
      id: 777,
      label: 'PRJ-0001 — Alpha',
      meta: { ...projectForSelectItem().meta, operational_site: { id: 1, label: 'Decoy site' } },
    }
    fetchProjectsForSelectMock.mockResolvedValue({
      items: [decoy, projectForSelectItem()],
      export_link: null,
      pagination: { total: 2, offset: 0, limit: 25, total_pages: 1 },
    })

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'select Project' }))

    // The picked project's Sede (81), never the decoy's (1).
    await waitFor(() => expect(screen.getByTestId('value-Site')).toHaveTextContent('81'))
  })
})

describe('CampaignForm — deselecting the Project (AC-043)', () => {
  it('clears and unlocks the 3 classification fields and the 4 geo levels, requiring Country again (spec 0039 D-3: Status is no longer required)', async () => {
    const linkedCampaign = campaign({
      project_id: TEST_PROJECT_ID,
      project: { id: TEST_PROJECT_ID, code: 'PRJ-0042', name: 'Acme rollout' },
      derived_from_project: true,
      geo_locked_levels: ['country'],
      operational_site_id: 81,
      operational_site: { id: 81, label: 'Warehouse A' },
    })

    render(
      <CampaignForm mode={{ type: 'edit', campaign: linkedCampaign }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('disabled-Status')).toHaveTextContent('true')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', 'country')

    fireEvent.click(screen.getByRole('button', { name: 'clear Project' }))

    await waitFor(() => expect(screen.getByTestId('disabled-Status')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Status')).toHaveTextContent('')
    // Spec 0094: unlinking resets product_lines to an EMPTY collection (not a
    // blanked single row) — no row testid survives, "Add" is the only affordance left.
    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-locked', '')
    expect(screen.getByTestId('geo-select')).toHaveAttribute('data-country', '')
    // The Sede is an always-own field (like Partner): unlinking never resets it.
    expect(screen.getByTestId('value-Site')).toHaveTextContent('81')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Country is required when the linked project does not provide one.'),
      ).toBeInTheDocument(),
    )
    expect(updateCampaignMock).not.toHaveBeenCalled()
  })
})

describe('CampaignForm — BR-3 budget 422', () => {
  it('shows the backend insufficient-budget message verbatim, not a generic error', async () => {
    const backendMessage =
      'Budget insufficiente sul progetto PRJ-0042: budget 1000.00, già allocato 600.00, residuo 400.00, richiesto 1000.00.'

    updateCampaignMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed.', errors: { total_budget: [backendMessage] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <CampaignForm mode={{ type: 'edit', campaign: campaign() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // "Planning & budget" is now a collapsible, default-closed section
    // (UX-only refactor): open it before reaching the "Total budget" field.
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
    fireEvent.change(screen.getByLabelText('Total budget'), { target: { value: '1000' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText(backendMessage)).toBeInTheDocument())
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})
