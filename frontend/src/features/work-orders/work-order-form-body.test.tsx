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

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>(
    '@/features/work-orders/api',
  )
  return {
    ...actual,
    createWorkOrder: vi.fn(),
    updateWorkOrder: vi.fn(),
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
    description: null,
    internal_notes: null,
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    quote_lines: [],
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
