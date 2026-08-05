import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0052 AC-008 — the "next callback" control: shows the panel's current
 * value (or blank), saves a SPARSE diff (`next_callback_at` alone), sends an
 * explicit `null` on clear, and is disabled for a read-only actor. Mounted
 * through the real panel (mirrors `request-work-panel.test.tsx`) because the
 * sparse-diff behaviour lives in `useRequestWorkForm` +
 * `buildRequestWorkPayload`, not in the section component itself.
 *
 * User directive 2026-07-31: the control is a date plus an OPTIONAL time, and
 * a date saved without one travels as midnight — the wire contract is
 * unchanged.
 */

const DATE_FIELD = 'Callback date'
const TIME_FIELD = 'Callback time (optional)'

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

// The panel now hosts the collaboration card, which reads the actor's client
// abilities to gate its Documents tab: stub them, this suite has no AuthProvider.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

/** Mirrors the backend's derivation for a visible-but-not-editable field (readonly = visible && !editable && !disabled). */
const READ_ONLY_PERMISSIONS = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {
    next_callback_at: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
  },
  actions: {},
}

const WORKFLOW_OPEN = { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false }
const WORKFLOW_IN_PROGRESS = { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false }

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
    workflow_statuses: [WORKFLOW_OPEN, WORKFLOW_IN_PROGRESS],
    product_lines: [],
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
    ...overrides,
  }
}

function renderPanel(id = 1) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={id} />
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
})

describe('RequestCallbackSection — current value (AC-008)', () => {
  it('splits the panel value across the date and the time input', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T15:30' }))

    renderPanel()

    expect(await screen.findByLabelText(DATE_FIELD)).toHaveValue('2026-08-03')
    expect(screen.getByLabelText(TIME_FIELD)).toHaveValue('15:30')
  })

  it('shows a blank time for a callback planned without one', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T00:00' }))

    renderPanel()

    expect(await screen.findByLabelText(DATE_FIELD)).toHaveValue('2026-08-03')
    expect(screen.getByLabelText(TIME_FIELD)).toHaveValue('')
  })

  it('shows both fields blank when the panel has no callback scheduled', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: null }))

    renderPanel()

    expect(await screen.findByLabelText(DATE_FIELD)).toHaveValue('')
    expect(screen.getByLabelText(TIME_FIELD)).toHaveValue('')
  })
})

describe('RequestCallbackSection — sparse diff submit (AC-008)', () => {
  it('sends a payload containing ONLY next_callback_at when the field changes', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: null }))
    updateRequestWorkMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T15:30' }))

    renderPanel()
    fireEvent.change(await screen.findByLabelText(DATE_FIELD), { target: { value: '2026-08-03' } })
    fireEvent.change(screen.getByLabelText(TIME_FIELD), { target: { value: '15:30' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateRequestWorkMock.mock.calls[0]
    expect(id).toBe(1)
    expect(payload).toEqual({ next_callback_at: '2026-08-03T15:30' })
  })

  it('a date saved without a time travels as midnight — the hour is not mandatory', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: null }))
    updateRequestWorkMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T00:00' }))

    renderPanel()
    fireEvent.change(await screen.findByLabelText(DATE_FIELD), { target: { value: '2026-08-03' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateRequestWorkMock.mock.calls[0]
    expect(payload).toEqual({ next_callback_at: '2026-08-03T00:00' })
  })

  it('clearing the date sends an explicit null, not an empty string or an absent key', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T15:30' }))
    updateRequestWorkMock.mockResolvedValue(panel({ next_callback_at: null }))

    renderPanel()
    fireEvent.change(await screen.findByLabelText(DATE_FIELD), { target: { value: '' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateRequestWorkMock.mock.calls[0]
    expect(payload).toEqual({ next_callback_at: null })
    expect(payload.next_callback_at).not.toBe('')
  })

  it('omits next_callback_at entirely when it is left untouched, even though another field changed', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ next_callback_at: '2026-08-03T15:30' }))
    updateRequestWorkMock.mockResolvedValue(
      panel({ next_callback_at: '2026-08-03T15:30', workflow_status: WORKFLOW_IN_PROGRESS }),
    )

    renderPanel()
    await screen.findByLabelText(DATE_FIELD)

    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))
    fireEvent.click(screen.getByRole('option', { name: 'In progress' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateRequestWorkMock.mock.calls[0]
    expect(payload).not.toHaveProperty('next_callback_at')
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })
})

describe('RequestCallbackSection — read-only actor (AC-008)', () => {
  it('disables the control for an actor whose field permission is not editable', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ next_callback_at: '2026-08-03T15:30', permissions: READ_ONLY_PERMISSIONS }),
    )

    renderPanel()
    const date = await screen.findByLabelText(DATE_FIELD)

    expect(date).toBeDisabled()
    expect(date).toHaveValue('2026-08-03')
    expect(screen.getByLabelText(TIME_FIELD)).toBeDisabled()
    // No update permission at the resource level either: no Save button to submit through.
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
  })
})
