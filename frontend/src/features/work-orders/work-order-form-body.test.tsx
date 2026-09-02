import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { WorkOrderFormBody } from '@/features/work-orders/work-order-form-body'
import type { WorkOrderDetailWithPermissions, WorkOrderFormMode } from '@/features/work-orders/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0093: AC-073 (the reason field is form-state-gated, not just
 * permission-gated), AC-074 (`code`/`quote_id` render read-only in edit and
 * never re-enable via a permission override).
 */

const fetchWorkOrderFormContextMock = vi.fn()

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>(
    '@/features/work-orders/api',
  )
  return {
    ...actual,
    createWorkOrder: vi.fn(),
    updateWorkOrder: vi.fn(),
    fetchWorkOrderFormContext: (...args: [number[]]) => fetchWorkOrderFormContextMock(...args),
  }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

// `quote_id` resolves to `quotes`, which has no registered quick-create entry,
// but `RelationSelectField` still reads abilities to gate any action slot.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const READONLY: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** `code`/`quote_id` read-only, everything else editable, matching an existing record's metadata (D-1/D-5). */
const EDIT_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    code: READONLY,
    quote_id: READONLY,
    title: EDITABLE,
    type: EDITABLE,
    callback_date: EDITABLE,
    start_date: EDITABLE,
    supervisor_ids: EDITABLE,
    participant_slots: EDITABLE,
    quote_line_ids: EDITABLE,
    is_force_closed: EDITABLE,
    force_close_reason: EDITABLE,
    description: EDITABLE,
    internal_notes: EDITABLE,
  },
  actions: {},
}

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function workOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 9,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    is_force_closed: false,
    force_close_reason: null,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    description: null,
    internal_notes: null,
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: EDIT_PERMISSIONS,
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(mode: WorkOrderFormMode, permissions: ResourcePermissions, initialCode?: string) {
  return render(
    <ResourcePermissionsProvider permissions={permissions}>
      <WorkOrderFormBody mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode={initialCode} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  fetchWorkOrderFormContextMock.mockReset()
  fetchWorkOrderFormContextMock.mockResolvedValue({ applicable_attributes: [], attribute_layout: null })
})

describe('WorkOrderFormBody — code/quote_id read-only in edit (AC-074)', () => {
  it('renders "Work order no." and "Linked offer" disabled', () => {
    renderForm({ type: 'edit', workOrder: workOrder() }, EDIT_PERMISSIONS)

    expect(screen.getByLabelText('Work order no.')).toBeDisabled()
    // `AsyncPaginatedSelect`'s trigger is `role="combobox"`, not "button".
    expect(screen.getByRole('combobox', { name: 'Linked offer' })).toBeDisabled()
  })
})

describe('WorkOrderFormBody — force close reason (AC-073)', () => {
  // Queried by role+name (not `getByLabelText`): the field is `required`,
  // so `FormLabel` appends a visual `*` (`aria-hidden`) to the label's text
  // content — the ARIA accessible-name algorithm `getByRole` uses correctly
  // drops it, a plain label textContent match would not.
  const reasonField = () => screen.queryByRole('textbox', { name: 'Force close reason' })

  it('hides the reason field until "Force closed" is switched on', () => {
    renderForm({ type: 'create' }, FULL_ACCESS_PERMISSIONS, 'COM-0002')

    expect(reasonField()).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('switch'))

    expect(reasonField()).toBeInTheDocument()
  })

  it('hides the reason field again once switched back off', () => {
    renderForm({ type: 'create' }, FULL_ACCESS_PERMISSIONS, 'COM-0002')

    const toggle = screen.getByRole('switch')
    fireEvent.click(toggle)
    expect(reasonField()).toBeInTheDocument()

    fireEvent.click(toggle)
    expect(reasonField()).not.toBeInTheDocument()
  })
})

describe('WorkOrderFormBody — offer picker (AC-071)', () => {
  it('is disabled in a fresh create form (no offer chosen yet)', async () => {
    renderForm({ type: 'create' }, FULL_ACCESS_PERMISSIONS, 'COM-0002')

    expect(screen.getByRole('button', { name: 'Product lines' })).toBeDisabled()
    await waitFor(() => expect(fetchForSelectMock).not.toHaveBeenCalledWith('quote-offer-lines', expect.anything()))
  })
})

/**
 * Spec 0096 AC-070/071/072: the "Responsabili e partecipanti" section renders
 * the three new fields, the slot rows are relabelled "Partecipante n" through
 * `ManagerSlotsField`'s own `labels` prop (the component itself untouched),
 * and edit-mode hydration preserves the persisted `position` gaps.
 */
describe('WorkOrderFormBody — responsabili e partecipanti (spec 0096)', () => {
  it('renders start date, responsabili and the relabelled participant slots in edit mode', async () => {
    renderForm({ type: 'edit', workOrder: workOrder() }, EDIT_PERMISSIONS)

    expect(await screen.findByLabelText(/^Start date/)).toHaveValue('2026-03-01')
    expect(screen.getByRole('button', { name: /Supervisors/ })).toBeInTheDocument()
    // Relabelled rows, not the shared "Gestore account n" default.
    expect(screen.getByText('Participant 1')).toBeInTheDocument()
    expect(screen.queryByText(/Account manager 1/)).not.toBeInTheDocument()
  })

  it('names the people being assigned "participants" everywhere, not "account managers"', () => {
    renderForm({ type: 'edit', workOrder: workOrder() }, EDIT_PERMISSIONS)

    // The shared slot editor defaults to the Gestori Account wording; this
    // module overrides it. Regression guard: the add button was the one place
    // the leftover default was actually visible to the user.
    expect(screen.getByRole('button', { name: 'Add participant' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /account manager/i })).not.toBeInTheDocument()
    expect(screen.getByText(/Participants are ordered/)).toBeInTheDocument()
    expect(screen.queryByText(/account managers are/i)).not.toBeInTheDocument()
  })

  it('hydrates a partecipante parked on a later slot without compacting the gap', async () => {
    const detail = workOrder()
    renderForm(
      {
        type: 'edit',
        workOrder: { ...detail, participants: [{ id: 31, name: 'Bruno Bianchi', position: 3 }] },
      },
      EDIT_PERMISSIONS,
    )

    // Slot 3 is filled and slots 1-2 stay as empty cards: the gap is data.
    expect(await screen.findByText('Participant 3')).toBeInTheDocument()
    expect(screen.getByText('Participant 1')).toBeInTheDocument()
  })
})

/** Spec 0098: the "Informazioni aggiuntive" section, gated on the SET of quote lines currently selected (AC-022/AC-024). */
describe('WorkOrderFormBody — dynamic attribute fields (spec 0098)', () => {
  it('AC-022: is not mounted at all when no quote line is linked', () => {
    renderForm({ type: 'edit', workOrder: workOrder({ quote_lines: [] }) }, EDIT_PERMISSIONS)

    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
    expect(fetchWorkOrderFormContextMock).not.toHaveBeenCalled()
  })

  it('AC-022/AC-024: resolves and renders the section, gated behind the field permission, once lines are linked', async () => {
    fetchWorkOrderFormContextMock.mockResolvedValue({
      applicable_attributes: [
        {
          id: 1,
          code: 'site_access',
          name: 'Site access',
          type: 'text',
          description: null,
          help_text: null,
          placeholder: null,
          icon: null,
          config: null,
          relation_target: null,
          is_required: false,
          sort_order: 0,
          options: [],
        },
      ],
      attribute_layout: null,
    })

    renderForm(
      {
        type: 'edit',
        workOrder: workOrder({
          quote_lines: [{ id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } }],
        }),
      },
      EDIT_PERMISSIONS,
    )

    expect(await screen.findByText('Additional information')).toBeInTheDocument()
    await waitFor(() => expect(fetchWorkOrderFormContextMock).toHaveBeenCalledWith([11]))
    expect(await screen.findByLabelText('Site access')).toBeInTheDocument()
  })
})
