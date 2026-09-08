import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0080 AC-045 (work panel side), rebound by spec 0097 AC-001: the TEAM
 * slots carry the G.A. denominations of the request's categories. With none
 * resolved each slot keeps the editor's default "Account manager n". Mirrors
 * the minimal panel/mock shape of `request-attribution-operator-link.test.tsx`.
 *
 * User directive 2026-09-08: the names follow the FORM, not the load — the
 * categoria prodotto is editable two sections above, and the server-resolved
 * `manager_labels` only stands in while the live resolution has nothing to
 * say (the rule itself is covered by `use-request-manager-labels.test.tsx`).
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
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
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

/** One "funzione aziendale + categoria prodotto" row, the classification the labels resolve from. */
function productLine(categoryId: number) {
  return {
    id: 1,
    business_function: { id: 1, name: 'Energy' },
    product_category: { id: categoryId, name: 'Photovoltaic' },
  }
}

describe('Work panel — team slot labels (spec 0080/0097)', () => {
  it('keeps the default denominations when the request has no resolved manager_labels', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
  })

  /** AC-001: the WHOLE team is on screen, not the operator slot alone. */
  it('renders one trigger per G.A. slot', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 3' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 4' })).toBeInTheDocument()
  })

  /**
   * Data-loss guard (backend note on spec 0097 AC-007): the panel PADS the
   * loaded pivot up to the default card count, it never TRUNCATES to it. The
   * PATCH is a full replace, so a G.A. sitting past the default count — legal
   * up to `ManagerPositions::MAX`, and reachable from the Offerte form — would
   * be wiped by the next save if the editor stopped rendering its slot.
   */
  it('renders every slot of a team reaching beyond the default count', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        managers: [
          { id: 5, name: 'Ada Lovelace', position: 2 },
          { id: 9, name: 'Grace Hopper', position: 6 },
        ],
      }),
    )

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Account manager 6' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 5' })).toBeInTheDocument()
  })

  it("shows the request's own resolved labels instead, per position", async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ manager_labels: { '1': 'Senior consultant', '2': 'Consultant' } }),
    )

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Consultant' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Senior consultant' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Account manager 2' })).not.toBeInTheDocument()
  })

  /**
   * User directive 2026-09-08: the categoria prodotto the FORM carries names
   * the slots, even when the server resolved something else for the record as
   * persisted — the field is editable, `manager_labels` is not recomputed
   * until the save.
   */
  it("resolves the labels of the form's own categoria prodotto over the loaded ones", async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ manager_labels: { '2': 'Consultant' }, product_lines: [productLine(9)] }),
    )
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '2': 'Energy manager' })

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Energy manager' })).toBeInTheDocument()
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(9)
    expect(screen.queryByRole('button', { name: 'Consultant' })).not.toBeInTheDocument()
  })

  /** Until that resolution settles the loaded labels stand in, so no slot flashes its default denomination. */
  it('keeps the loaded labels while the category resolution is in flight', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ manager_labels: { '2': 'Consultant' }, product_lines: [productLine(9)] }),
    )
    fetchCategoryManagerLabelsMock.mockReturnValue(new Promise(() => {}))

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Consultant' })).toBeInTheDocument()
  })
})
