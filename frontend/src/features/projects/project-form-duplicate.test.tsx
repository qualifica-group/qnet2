import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClientProvider, QueryClient } from '@tanstack/react-query'
import i18n from '@/i18n'
import { projects as projectsEn } from '@/i18n/locales/en-projects'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Row action "duplicate": the create form is pre-filled from a source
 * project — name gets the copy suffix, `code` is discarded for a fresh
 * sequential suggestion, every other field carries over untouched — and
 * still submits via `createProject` (never `updateProject`). Split into its
 * own file (rather than growing `project-form-body.test.tsx` past the 500-line
 * hard limit) but mirrors the same mocking setup.
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
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => <button type="button" data-testid={`select-${labels.triggerLabel}`}>{value ?? ''}</button>,
}))

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/geo/geo-select', () => ({
  GeoSelect: ({ value }: { value: { country_id: number | null } }) => (
    <div data-testid="geo-select">{value.country_id ?? ''}</div>
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

describe('ProjectForm — duplicate (row action "duplicate")', () => {
  it('seeds the name with the copy suffix, discards the source code for a fresh one, and submits via the create API', async () => {
    fetchProjectNextCodeMock.mockResolvedValue('PRJ-0200')
    createProjectMock.mockResolvedValue(project({ id: 99, code: 'PRJ-0200', name: 'Acme rollout (copy)' }))

    render(
      <ProjectForm
        mode={{ type: 'duplicate', source: project({ name: 'Acme rollout' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText('Name')).toHaveValue('Acme rollout (copy)'))
    // The source's own code (PRJ-0007) is discarded in favor of a fresh sequential suggestion.
    expect(screen.getByRole('textbox', { name: 'Code' })).toHaveValue('PRJ-0200')
    // Every other field is carried over from the source, unattended, into the visible controls.
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
    expect(screen.getByTestId('select-Business function 1')).toHaveTextContent('2')
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('4')
    expect(screen.getByTestId('geo-select')).toHaveTextContent('1')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProjectMock).toHaveBeenCalledTimes(1))
    expect(updateProjectMock).not.toHaveBeenCalled()
    const payload = createProjectMock.mock.calls[0][0] as Record<string, unknown>
    expect(payload.name).toBe('Acme rollout (copy)')
    expect(payload.code).toBe('PRJ-0200')
    expect(payload.product_lines).toEqual([{ business_function_id: 2, product_category_id: 4 }])
    expect(payload.country_id).toBe(1)
    expect(payload.pipeline_status_id).toBe(3)
  })

  it('does not overwrite the copied pipeline status with the system default', async () => {
    fetchSystemStatusIdMock.mockResolvedValue(42)

    render(
      <ProjectForm
        mode={{ type: 'duplicate', source: project({ pipeline_status_id: 3 }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Status')).toBeInTheDocument())
    // A regression guard: `useDefaultSystemStatusId` must not even be consulted for duplicate.
    expect(fetchSystemStatusIdMock).not.toHaveBeenCalled()
    expect(screen.getByTestId('select-Status')).toHaveTextContent('3')
  })
})
