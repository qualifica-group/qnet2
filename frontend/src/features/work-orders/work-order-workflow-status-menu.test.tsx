import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { notifyFormErrors } from '@/features/auth/form-errors'
import { fetchWorkOrderFormContext, updateWorkOrder } from '@/features/work-orders/api'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import {
  IN_PROGRESS_WORKFLOW_STATUS,
  OPEN_WORKFLOW_STATUS,
  WORKFLOW_STATUS_FIELDS,
} from '@/features/work-orders/work-order-workflow-fixtures'
import type { FieldPermission } from '@/features/authorization/types'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0322 AC-014: the "Stato lavorazione", changed from the resolved set.
 * REQUIREMENT CHANGED (user directive 2026-11-27): it left the Info tab for
 * the record header, beside the working-phase pill; a pick saves at once.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/auth/form-errors', async () => {
  const actual = await vi.importActual<typeof import('@/features/auth/form-errors')>('@/features/auth/form-errors')
  return { ...actual, notifyFormErrors: vi.fn() }
})
vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))
vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrderFormContext: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const trigger = (status = OPEN_WORKFLOW_STATUS.name) =>
  screen.queryByRole('button', { name: label('workOrders.detail.workflowStatusChange', { status }) })

const FIELD_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

function workOrder(statusPermission: FieldPermission = FIELD_PERMISSION): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    title_is_manual: true,
    type: 'processing',
    status: { value: 'to_plan', is_force_closed: false },
    completion_percentage: 0,
    is_force_closed: false,
    ...WORKFLOW_STATUS_FIELDS,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [],
    description: null,
    internal_notes: null,
    is_multi_quote: false,
    source_quotes: [],
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: null,
    task_template: null,
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    locked_attributes: [],
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: { work_order_workflow_status_id: statusPermission },
      actions: { force_close: true, reopen: false },
    },
  }
}

function renderDetail(record: WorkOrderDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <WorkOrderDetailView workOrder={record} onChanged={vi.fn()} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

async function openMenu(): Promise<HTMLElement> {
  fireEvent.pointerDown(trigger()!, { button: 0, ctrlKey: false })
  return screen.findByRole('menu')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(notifyFormErrors).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockResolvedValue({ applicable_attributes: [], attribute_layout: null, locked_attributes: [] })
})

describe('WorkOrderDetailView — Stato lavorazione in the header', () => {
  it('shows the current working status beside the phase pill, no longer as an Info section', () => {
    renderDetail(workOrder())

    const pill = trigger()!
    expect(within(pill).getByText(OPEN_WORKFLOW_STATUS.name)).toHaveAttribute('title', OPEN_WORKFLOW_STATUS.name)
    expect(pill.parentElement).toBe(screen.getByRole('button', { name: label('workOrders.detail.actionsMenu') }).parentElement)
    expect(screen.queryByRole('heading', { level: 3, name: label('workOrders.detail.workflowStatus') })).not.toBeInTheDocument()
  })

  it('lists only the resolved set and PATCHes the chosen id alone', async () => {
    const record = workOrder()
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({
      ...record,
      work_order_workflow_status_id: IN_PROGRESS_WORKFLOW_STATUS.id,
      work_order_workflow_status: IN_PROGRESS_WORKFLOW_STATUS,
    })
    renderDetail(record)

    const menu = await openMenu()
    expect(within(menu).getAllByRole('menuitemradio')).toHaveLength(2)
    expect(within(menu).getByRole('menuitemradio', { name: new RegExp(OPEN_WORKFLOW_STATUS.name) })).toHaveAttribute('aria-checked', 'true')
    fireEvent.click(within(menu).getByRole('menuitemradio', { name: new RegExp(IN_PROGRESS_WORKFLOW_STATUS.name) }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(4, { work_order_workflow_status_id: IN_PROGRESS_WORKFLOW_STATUS.id }),
    )
  })

  it('does not save when the current status is picked again', async () => {
    renderDetail(workOrder())

    const menu = await openMenu()
    fireEvent.click(within(menu).getByRole('menuitemradio', { name: new RegExp(OPEN_WORKFLOW_STATUS.name) }))

    expect(updateWorkOrder).not.toHaveBeenCalled()
  })

  it('shows the bare pill, with no menu, when the field permission is read-only', () => {
    renderDetail(workOrder({ ...FIELD_PERMISSION, editable: false, readonly: true }))

    expect(screen.getByText(OPEN_WORKFLOW_STATUS.name)).toBeInTheDocument()
    expect(trigger()).not.toBeInTheDocument()
  })

  it('shows nothing when the field is hidden', () => {
    renderDetail(workOrder({ ...FIELD_PERMISSION, visible: false, hidden: true }))

    expect(screen.queryByText(OPEN_WORKFLOW_STATUS.name)).not.toBeInTheDocument()
  })

  it('raises the server 422 reasons in the foreground toast (spec 0323 D-2)', async () => {
    const message = "Lo stato porta l'avanzamento al 20%, che non supera l'avanzamento gia' raggiunto (20%)."
    vi.mocked(updateWorkOrder).mockRejectedValueOnce(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { work_order_workflow_status_id: [message] } },
      } as AxiosResponse),
    )
    renderDetail(workOrder())

    const menu = await openMenu()
    fireEvent.click(within(menu).getByRole('menuitemradio', { name: new RegExp(IN_PROGRESS_WORKFLOW_STATUS.name) }))

    await waitFor(() => expect(notifyFormErrors).toHaveBeenCalledWith([message]))
    expect(trigger()).toBeInTheDocument()
  })
})
