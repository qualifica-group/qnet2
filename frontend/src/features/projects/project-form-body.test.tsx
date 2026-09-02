import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { FieldPermission, ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0025 PARTE A: `code` is a manual, optional field — an enabled input
 * with a fallback-declaring placeholder in create (AC-010), disabled/
 * read-only showing the saved value in edit (AC-011), with a 422 duplicate
 * mapped onto the field itself (AC-012). Gated by the `code` field
 * permission, exactly like every other `MetaField`-driven control.
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
 * `resolveLabel`), bypassing the mocked `AsyncPaginatedSelect` component —
 * left un-mocked it fires a real, unauthenticated request (mirrors
 * `opportunity-form-body.test.tsx`'s own `fetchForSelectMock`).
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

/** Spec 0039 D-3: the create form preselects this resolved "Nuovo" status id. */
const fetchSystemStatusIdMock = vi.fn<() => Promise<number | null>>()
vi.mock('@/features/status-reorder/api', () => ({
  fetchSystemStatusId: () => fetchSystemStatusIdMock(),
}))

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `registry-form-metadata.test.tsx`). Renders as a button calling
 * `onChange` so create-mode tests can satisfy the required Status select
 * without a real dropdown.
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

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03); this suite is about the surrounding form, so it stands in for
 * the picker with a local, clickable double (mirrors the AsyncPaginatedSelect
 * stub above) rather than the shared read-only
 * `product-category-tree-select-stub` (which exposes no "pick" affordance,
 * so it cannot satisfy a full happy-path submit).
 */
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
 * The geo cascade is covered end-to-end by `geo-select.test.tsx`; here it is
 * stubbed to a single button that fills `country_id` (spec 0027 BR-4: the
 * only field this suite's submit flows need to satisfy).
 */
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
  // `projects` is not yet wired into `en.ts` (pending the wiring lane, see
  // handoff): registered here so the feature's own copy renders for real.
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

/**
 * Fills the create-form fields made mandatory alongside name/status/country:
 * the product_lines row 1 (stubbed selects, spec 0094) and the planning
 * dates (in the collapsed Planning & budget section, opened first). `code` is
 * auto-filled by the form from `fetchProjectNextCode`.
 */
function completeRequiredCreateFields() {
  fireEvent.click(screen.getByTestId('select-Business function 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
  fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))
  fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-01-01' } })
  fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-12-31' } })
}

describe('ProjectForm — default status preselection (spec 0039 D-3, AC-012)', () => {
  it('preselects the resolved "Nuovo" status id on create once it resolves', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Status')).toHaveTextContent('42'))
  })

  it('does not preselect in edit mode', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)

    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Status')).toBeInTheDocument())
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
    expect(fetchSystemStatusIdMock).not.toHaveBeenCalled()
  })
})

describe('ProjectForm — manual code (spec 0025 AC-010/AC-011)', () => {
  it('auto-fills the enabled, required code field with the next sequential suggestion on create (AC-010)', async () => {
    fetchProjectNextCodeMock.mockResolvedValue('PRJ-0042')
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    const code = screen.getByRole('textbox', { name: 'Code' }) as HTMLInputElement
    expect(code).not.toBeDisabled()
    expect(code.value).toBe('PRJ-0042')
  })

  it('sends the trimmed manual code on create submit when the user fills it (AC-010)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProjectMock.mockResolvedValue(project({ code: 'ACME-2026' }))

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: '  ACME-2026  ' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    const payload = createProjectMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.code).toBe('ACME-2026')
  })

  it('shows the saved code value, disabled/read-only, on edit (AC-011)', () => {
    render(
      <ProjectForm
        mode={{ type: 'edit', project: project({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const code = screen.getByRole('textbox', { name: 'Code' }) as HTMLInputElement
    expect(code).toBeDisabled()
    expect(code).toHaveAttribute('readonly')
    expect(code.value).toBe('PRJ-0007')
  })

  it('never sends a code field on edit submit (AC-011)', async () => {
    updateProjectMock.mockResolvedValue(project({ name: 'Renamed rollout' }))

    render(
      <ProjectForm
        mode={{ type: 'edit', project: project({ permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_READONLY_PERMISSION } } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Renamed rollout' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProjectMock).toHaveBeenCalledTimes(1))
    const payload = updateProjectMock.mock.calls[0][1] as Record<string, unknown>
    expect(payload).not.toHaveProperty('code')
    expect(payload).toEqual({ name: 'Renamed rollout' })
  })
})

describe('ProjectForm — 422 duplicate code (spec 0025 AC-012)', () => {
  it('maps a 422 on the code field onto the field itself, not only a toast', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: { ...FULL_PERMISSIONS, fields: { code: CODE_EDITABLE_PERMISSION } },
    })
    createProjectMock.mockRejectedValue(
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

    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Code' })).toBeInTheDocument())
    fireEvent.change(screen.getByRole('textbox', { name: 'Code' }), { target: { value: 'ACME-2026' } })
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByTestId('geo-select'))
    completeRequiredCreateFields()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The code has already been taken.')).toBeInTheDocument(),
    )
    expect(screen.queryByText('Something went wrong. Please try again.')).not.toBeInTheDocument()

    vi.restoreAllMocks()
  })
})

describe('ProjectForm — geo hierarchy (spec 0027 BR-4/AC-010)', () => {
  it('blocks the submit with the required message when no country is chosen', async () => {
    render(<ProjectForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByLabelText('Name')).toBeInTheDocument())
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme rollout' } })
    fireEvent.click(screen.getByTestId('select-Status'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Country is required.')).toBeInTheDocument())
    expect(createProjectMock).not.toHaveBeenCalled()
  })

  it('submits once a country is chosen', async () => {
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
    expect(payload.country_id).toBe(1)
  })
})

describe('ProjectForm — end_date validation (BR-6)', () => {
  it('rejects an end_date earlier than the start_date', async () => {
    render(
      <ProjectForm mode={{ type: 'edit', project: project() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // Planning & budget is a secondary section, collapsed by default (graphical refactor): open it first.
    fireEvent.click(screen.getByRole('button', { name: /Planning & budget/ }))

    fireEvent.change(screen.getByLabelText('Start date'), { target: { value: '2026-06-01' } })
    fireEvent.change(screen.getByLabelText('End date'), { target: { value: '2026-01-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('End date must not be earlier than the start date.')).toBeInTheDocument(),
    )
    expect(updateProjectMock).not.toHaveBeenCalled()
  })
})

// Sede (operational site) and product_lines (spec 0094) coverage lives in
// `project-form-product-lines.test.tsx` (engineering.md §6 size split).
