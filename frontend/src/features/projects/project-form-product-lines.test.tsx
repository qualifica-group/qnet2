import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0094 (AC-045): the project form's `product_lines` row editor (shared
 * `ProductLinesField`, spec 0057) and the Sede field — split out of
 * `project-form-body.test.tsx` to stay within the 500-line hard limit
 * (engineering.md §6), mirroring the same mocking setup.
 */

const createProjectMock = vi.fn()
const updateProjectMock = vi.fn()
const fetchProjectNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/projects/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/projects/api')>(
    '@/features/projects/api',
  )
  return {
    ...actual,
    createProject: (...args: unknown[]) => createProjectMock(...args),
    updateProject: (...args: unknown[]) => updateProjectMock(...args),
    fetchProjectNextCode: () => fetchProjectNextCodeMock(),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * `ProductLinesField`'s row picker resolves an unknown business-function
 * label through this API directly (`use-product-lines-field.ts`'s
 * `resolveLabel`), bypassing the mocked `AsyncPaginatedSelect` component.
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

vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({
    value,
    onChange,
  }: {
    value: { country_id: number | null }
    onChange: (value: {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }) => void
  }) => (
    <button
      type="button"
      data-testid="geo-select"
      onClick={() =>
        onChange({ country_id: 1, state_id: null, province_id: null, city_id: null })
      }
    >
      {value.country_id ?? ''}
    </button>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function project(
  overrides: Partial<ProjectDetailWithPermissions> = {},
): ProjectDetailWithPermissions {
  return {
    id: 7,
    code: 'PRJ-0007',
    name: 'Acme rollout',
    description: null,
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Widgets' } }],
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    allocated_budget: '0.00',
    remaining_budget: null,
    campaigns_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { projects: projectsEn }, true, true)
})

beforeEach(() => {
  createProjectMock.mockReset()
  updateProjectMock.mockReset()
  fetchProjectNextCodeMock.mockReset()
  fetchProjectNextCodeMock.mockResolvedValue('PRJ-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
})

/** Fills the fields made mandatory alongside name/status/country: row 1 (spec 0094) and the planning dates. */
function completeRequiredCreateFields() {
  fireEvent.click(screen.getByTestId('select-Business function 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
  fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
  fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
  fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
}

describe('ProjectForm — product_lines (spec 0094, AC-045)', () => {
  it('opens the create form on ONE empty row (mirrors opportunities/request-management)', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument())
    expect(screen.queryByTestId('select-Business function 2')).not.toBeInTheDocument()
  })

  it('disables the row category until its own business function is chosen, then enables it', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeInTheDocument())
    expect(screen.getByTestId('select-Product category 1')).toBeDisabled()

    fireEvent.click(screen.getByTestId('select-Business function 1'))

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeEnabled())
  })

  it('leaves the row category enabled in edit mode (a business function is already set)', async () => {
    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Product category 1')).toBeInTheDocument())
    expect(screen.getByTestId('select-Product category 1')).toBeEnabled()
  })

  it('adds a second independent row via "Add", each scoped by its OWN business function', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('select-Business function 2')).toBeInTheDocument()
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')
  })

  it('blocks the submit and shows an error when the collection is left incomplete (AC-045)', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
    // The default row is left incomplete on purpose (no business function/category picked).
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Each row requires both a business function and a product category.'),
      ).toBeInTheDocument(),
    )
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('sends every complete row on create submit', async () => {
    createProjectMock.mockResolvedValue(project())

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    const payload = createProjectMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.product_lines).toEqual([{ business_function_id: 3, product_category_id: 4 }])
  })
})

describe('ProjectForm — Sede (operational site)', () => {
  it('renders the Site field and pre-fills it from the loaded project in edit mode', async () => {
    render(
      <ProjectForm
        mode={{
          type: 'edit',
          project: project({ operational_site_id: 8, operational_site: { id: 8, label: 'Warehouse A' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    expect(screen.getByTestId('select-Site')).toHaveTextContent('8')
  })

  it('sends the picked operational_site_id in the create payload', async () => {
    createProjectMock.mockResolvedValue(project())

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    fireEvent.click(screen.getByTestId('select-Site'))
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(createProjectMock.mock.calls[0][0].operational_site_id).toBe(3)
  })
})
