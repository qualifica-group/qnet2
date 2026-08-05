import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Spec 0047 (AC-026): the Regione (read-only once the opportunity is
 * lead-linked) and the working-state select (limited to the resolved set,
 * edit-only — hidden on create with a hint instead). Split out of
 * `opportunity-form-body.test.tsx` for size (engineering.md §6).
 */

const createOpportunityMock = vi.fn()
const updateOpportunityMock = vi.fn()

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03) and mounts its own quick-create affordance; this suite is about
 * the surrounding form, so it stands in for the picker with the shared double.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
    updateOpportunity: (...args: unknown[]) => updateOpportunityMock(...args),
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

/** Stubs every single-relation select, keyed by its accessible trigger label (mirrors `opportunity-form-body.test.tsx`). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    disabled,
    labels,
  }: {
    value: number | null
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
    </div>
  ),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: vi.fn(async () => EMPTY_PAGE),
  }
})

/** The products-of-interest picker opens the shared confirm dialog, so its provider is required. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function editOpportunity(
  overrides: Partial<OpportunityDetailWithPermissions> = {},
): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: 10,
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    state_id: null,
    state: null,
    status: { source: 'workflow', distinct_count: 0, entries: [] },
    opportunity_workflow_status_id: 100,
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', group: 'open', description: null, requires_note: false },
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', group: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, group: 'open', description: null, requires_note: false },
      { id: 102, name: 'Closed', color: 'green', system_key: 'closed_won', group: 'closed_won', description: null, requires_note: false },
    ],
    product_lines: [
      {
        id: 1,
        business_function: { id: 40, name: 'Sales' },
        product_category: { id: 500, name: 'Consulting' },
      },
    ],
    // Mandatory since the user directive 2026-07-23: without one the form
    // never submits, so every fixture that saves carries a product.
    products_of_interest: [{ id: 700, name: 'Fibra 1000', product_category: { id: 500, name: 'Consulting' } }],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOpportunityMock.mockReset()
  updateOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
})

describe('OpportunityFormBody — Regione + working-state (spec 0047, AC-026)', () => {
  it('create mode: Regione renders editable and the working-state field is not rendered', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Region')).toBeInTheDocument())
    expect(screen.getByTestId('disabled-Region')).toHaveTextContent('false')
    expect(screen.queryByRole('combobox', { name: 'Working status' })).not.toBeInTheDocument()
    expect(screen.queryByText('Assigned automatically on save.')).not.toBeInTheDocument()
  })

  it('edit mode, standalone opportunity: Regione stays editable', async () => {
    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Region')).toBeInTheDocument())
    expect(screen.getByTestId('disabled-Region')).toHaveTextContent('false')
  })

  it('edit mode, lead-linked opportunity: Regione is pre-filled from the Lead but stays editable', async () => {
    render(
      <OpportunityForm
        mode={{
          type: 'edit',
          opportunity: editOpportunity({
            lead_id: 5,
            lead: { id: 5, label: 'Mario Rossi' },
            state_id: 3,
            state: { id: 3, name: 'Lombardia' },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Region')).toBeInTheDocument())
    expect(screen.getByTestId('disabled-Region')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Region')).toHaveTextContent('3')
  })

  it('edit mode: the working-state select lists the resolved set and submits the picked id', async () => {
    updateOpportunityMock.mockResolvedValue(editOpportunity())

    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open'),
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))
    fireEvent.click(screen.getByRole('option', { name: 'In progress' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateOpportunityMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateOpportunityMock.mock.calls[0]
    expect(payload.opportunity_workflow_status_id).toBe(101)
  })

  it('shows the server 422 error when the picked working-state falls outside the resolved set', async () => {
    updateOpportunityMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { opportunity_workflow_status_id: ['The selected working status is invalid.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The selected working status is invalid.')).toBeInTheDocument(),
    )

    vi.restoreAllMocks()
  })
})
