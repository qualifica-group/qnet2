import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * AC-045 (work panel side): the GA2 "Operatore" field relabels from the
 * request's own `manager_labels` (spec 0080), preferring it over a fetch —
 * the work panel already carries it. AC-032: with no resolved label the
 * field keeps today's exact "Operator (GA2)" string. Mirrors the minimal
 * panel/mock shape of `request-attribution-operator-link.test.tsx`.
 */

const fetchRequestWorkPanelMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button" aria-label={labels.triggerLabel} />
  ),
}))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const WORKFLOW_OPEN = {
  id: 100,
  name: 'Open',
  color: 'blue',
  system_key: 'open',
  description: null,
  requires_note: false,
}

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    source_id: 30,
    source: { id: 30, name: 'Web' },
    reporter_id: null,
    reporter: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'workflow', distinct_count: 0, entries: [] },
    workflow_status: WORKFLOW_OPEN,
    workflow_statuses: [WORKFLOW_OPEN],
    product_lines: [],
    products_of_interest: [],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    applicable_attributes: [],
    attribute_values: {},
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={1} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
})

describe('Work panel — Operatore field label (spec 0080)', () => {
  it('AC-032: keeps "Operator (GA2)" when the request has no resolved manager_labels', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Operator (GA2)' })).toBeInTheDocument()
  })

  it("AC-045: shows the request's own resolved level-2 label instead", async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ manager_labels: { '2': 'Consultant' } }))

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Consultant' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Operator (GA2)' })).not.toBeInTheDocument()
  })
})
