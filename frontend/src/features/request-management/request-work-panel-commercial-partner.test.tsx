import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0417 AC-026: the work panel offers "Commercial partner" right after the
 * "Commerciale" (labelled "Sales rep" in English).
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/opportunities/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/opportunities/api')>()),
  fetchCategoryManagerLabels: (...args: unknown[]) => fetchCategoryManagerLabelsMock(...args),
}))

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

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    // Deliberately different from `opportunity_id` (spec 0086 D-9/D-10): not
    // this suite's own concern, but kept apart everywhere so a coincidental
    // pass never hides an inverted wiring elsewhere.
    id: 4001,
    opportunity_id: 8001,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    registry_lock_reason: null,
    referent: { id: 20, name: 'Mario Rossi' },
    commercial_id: null,
    commercial: null,
    commercial_partner_id: null,
    commercial_partner: null,
    source_id: 30,
    source: { id: 30, name: 'Web' },
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'default', distinct_count: 0, entries: [] },
    product_lines: [],
    offer_lines: [],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    next_callback_at: null,
    attribute_values: {},
    applicable_attributes: [],
    attribute_layout: null,
    quote_workflow_status_id: null,
    quote_workflow_status: null,
    quote_workflow_statuses: [],
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  fetchCategoryManagerLabelsMock.mockReset()
  fetchCategoryManagerLabelsMock.mockResolvedValue({})
})

describe('Work panel — Commercial partner (spec 0417)', () => {
  it('shows the partner field right after the Commerciale', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    const commercial = await screen.findByRole('button', { name: 'Sales rep' })
    const partner = screen.getByRole('button', { name: 'Commercial partner' })
    expect(commercial.compareDocumentPosition(partner) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })
})
