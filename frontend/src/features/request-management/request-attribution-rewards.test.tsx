import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * AC-031 (spec 0059 D-3): the same "abbinamento buono" control as the
 * Opportunity form (its own interaction suite is
 * `reward-assignment-field.test.tsx`), mounted through the real work panel
 * (mirrors `request-callback-section.test.tsx`) so the wiring in
 * `RequestAttributionSection`/`buildRequestWorkPayload` is exercised, not
 * just the shared component in isolation.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

const createContactMock = vi.fn()
const updateContactMock = vi.fn()
const deleteContactMock = vi.fn()
vi.mock('@/features/personal-data/api', () => ({
  createContact: (...args: unknown[]) => createContactMock(...args),
  updateContact: (...args: unknown[]) => updateContactMock(...args),
  deleteContact: (...args: unknown[]) => deleteContactMock(...args),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const fetchRewardTypeMock = vi.fn()
vi.mock('@/features/reward-types/api', () => ({
  fetchRewardType: (id: number) => fetchRewardTypeMock(id),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const WORKFLOW_OPEN = { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false }

const AMAZON = {
  id: 900,
  reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' },
  assigned_at: '2026-01-01',
  notes: null,
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
    opportunity_status: { id: 5, name: 'New', color: 'slate' },
    workflow_status: WORKFLOW_OPEN,
    workflow_statuses: [WORKFLOW_OPEN],
    product_lines: [],
    // Mandatory since the user directive 2026-07-23 (>=1 product) — not the
    // concern of this suite, kept non-empty so submit is never blocked by it.
    products_of_interest: [{ id: 700, name: 'Fibra 1000', product_category: { id: 500, name: 'Consulting' } }],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    applicable_attributes: [],
    attribute_values: {},
    next_callback_at: null,
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: FULL_PERMISSIONS,
    rewards: [],
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
  updateRequestWorkMock.mockReset()
  createContactMock.mockReset()
  updateContactMock.mockReset()
  deleteContactMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  fetchRewardTypeMock.mockReset()
})

describe('RequestAttributionSection — reward assignment (AC-031)', () => {
  it('disables the add control with no reporter, and re-enables once one is set', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ reporter_id: null, reporter: null }))

    renderPanel()

    expect(await screen.findByRole('button', { name: 'Add reward' })).toBeDisabled()
    expect(screen.getByText('Select a reporter first to assign a reward.')).toBeInTheDocument()
  })

  it('shows the reporter’s persisted rewards read-only when no reporter is set', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ reporter_id: null, reporter: null, rewards: [AMAZON] }))

    renderPanel()

    expect(await screen.findByText('Amazon 10€')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove Amazon 10€' })).not.toBeInTheDocument()
  })

  it('adds a reward and submits the id set in the sparse PATCH payload', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ reporter_id: 20, reporter: { id: 20, name: 'Mario Rossi' } }),
    )
    updateRequestWorkMock.mockResolvedValue(panel({ reporter_id: 20, reporter: { id: 20, name: 'Mario Rossi' } }))
    fetchForSelectMock.mockResolvedValue({
      items: [{ id: 5, label: 'Buono spesa' }],
      pagination: { offset: 0, limit: 25, total: 1 },
      export_link: null,
    })
    fetchRewardTypeMock.mockResolvedValue({
      id: 5,
      name: 'Buono spesa',
      color: 'green',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    })

    renderPanel()
    fireEvent.click(await screen.findByRole('button', { name: 'Add reward' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Buono spesa' }))
    await screen.findByRole('button', { name: 'Remove Buono spesa' })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({ rewards: [{ reward_type_id: 5 }] })
  })
})
