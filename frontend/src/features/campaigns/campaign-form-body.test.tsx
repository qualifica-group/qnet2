import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { campaigns as campaignsEn } from '@/i18n/locales/en-campaigns'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'
import type { FieldPermission, ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0025 PARTE A: `code` is a manual, optional field — an enabled input
 * with a fallback-declaring placeholder in create (AC-010), disabled/
 * read-only showing the saved value in edit (AC-011), with a 422 duplicate
 * mapped onto the field itself (AC-012). Gated by the `code` field
 * permission, exactly like every other `MetaField`-driven control. The
 * AC-042/AC-043/BR-3 Project-link behaviour lives in
 * `campaign-project-link.test.tsx` (split for file-size, engineering.md §6).
 */

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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * `ProductLinesField`'s row picker resolves an unknown business-function
 * label through this API directly (`use-product-lines-field.ts`'s
 * `resolveLabel`), bypassing the mocked `AsyncPaginatedSelect` component —
 * left un-mocked it fires a real, unauthenticated request.
 */
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

/** Spec 0039 D-3: the standalone create form preselects this resolved "Nuovo" status id. */
const fetchSystemStatusIdMock = vi.fn<() => Promise<number | null>>()
vi.mock('@/features/status-reorder/api', () => ({
  fetchSystemStatusId: () => fetchSystemStatusIdMock(),
}))

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `project-form-body.test.tsx`). Renders as a button calling
 * `onChange` so standalone-create tests can satisfy the 3 required
 * classification selects without a real dropdown.
 */
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
    businessFunctionId,
    disabled,
    triggerLabel,
  }: {
    value: number | null
    onChange: (id: number) => void
    businessFunctionId: number | null
    disabled?: boolean
    triggerLabel: string
  }) => {
    const isDisabled = Boolean(disabled) || businessFunctionId === null
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

/**
 * `GeoSelect` is covered by its own test (`features/geo/geo-select.test.tsx`);
 * here a controllable stub lets standalone-create tests satisfy the
 * `country_id` required field (spec 0027 BR-4) without a real cascade.
 */
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

/** `code` field-permission fixtures mirroring the spec 0025 contract's create/update ceilings. */
const CODE_EDITABLE_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}
const CODE_READONLY_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

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
  // `campaigns` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
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

/**
 * Fills the now-required planning dates on the create form (collapsed
 * Planning & budget section, opened first). `code` is auto-filled from
 * `fetchCampaignNextCode`; classification selects are clicked per-test.
 */
function fillRequiredDates() {
  fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
  fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
  fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
}

/** Fills the standalone-required product_lines row 1 (spec 0094). */
function fillRequiredClassification() {
  fireEvent.click(screen.getByTestId('select-Business function 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
}

describe('CampaignForm — default status preselection (spec 0039 D-3, AC-012)', () => {
  it('preselects the resolved "Nuovo" status id on a standalone create once it resolves', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Status')).toHaveTextContent('42'))
  })

  it('does not preselect in edit mode', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)

    render(
      <CampaignForm mode={{ type: 'edit', campaign: campaign() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Status')).toBeInTheDocument())
    expect(screen.getByTestId('select-Status')).toHaveTextContent('1')
    expect(fetchSystemStatusIdMock).not.toHaveBeenCalled()
  })
})

describe('CampaignForm — manual code (spec 0025 AC-010/AC-011)', () => {
  it('auto-fills the enabled, required code field with the next sequential suggestion on create (AC-010)', async () => {
    fetchCampaignNextCodeMock.mockResolvedValue('CMP-0042')
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).not.toBeDisabled()
    expect(code.value).toBe('CMP-0042')
  })

  it('sends the trimmed manual code on create submit when the user fills it (AC-010)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createCampaignMock.mockResolvedValue(campaign({ code: 'ACME-2026' }))

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Code'), { target: { value: '  ACME-2026  ' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Spring push' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fillRequiredClassification()
    fireEvent.click(screen.getByTestId('geo-select'))
    fillRequiredDates()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createCampaignMock).toHaveBeenCalledTimes(1))
    const payload = createCampaignMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.code).toBe('ACME-2026')
  })

  it('shows the saved code value, disabled/read-only, on edit (AC-011)', () => {
    render(
      <CampaignForm
        mode={{
          type: 'edit',
          campaign: campaign({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const code = screen.getByLabelText('Code') as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code).toHaveAttribute('readonly')
    expect(code.value).toBe('CMP-0009')
  })

  it('never sends a code field on edit submit (AC-011)', async () => {
    updateCampaignMock.mockResolvedValue(campaign({ name: 'Renamed push' }))

    render(
      <CampaignForm
        mode={{
          type: 'edit',
          campaign: campaign({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Renamed push' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateCampaignMock).toHaveBeenCalledTimes(1))
    const payload = updateCampaignMock.mock.calls[0][1] as Record<string, unknown>
    expect(payload).not.toHaveProperty('code')
    expect(payload).toEqual({ name: 'Renamed push' })
  })
})

describe('CampaignForm — 422 duplicate code (spec 0025 AC-012)', () => {
  it('maps a 422 on the code field onto the field itself, not only a toast', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createCampaignMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed.', errors: { code: ['The code has already been taken.'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(<CampaignForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Code')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'ACME-2026' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Spring push' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fillRequiredClassification()
    fireEvent.click(screen.getByTestId('geo-select'))
    fillRequiredDates()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The code has already been taken.')).toBeInTheDocument(),
    )
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})

// `product_lines` (spec 0094, AC-045) and Sede (operational site) coverage
// lives in `campaign-form-product-lines.test.tsx` (engineering.md §6 size split).
