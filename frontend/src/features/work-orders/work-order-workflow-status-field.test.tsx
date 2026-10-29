import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { fetchWorkOrderFormContext, updateWorkOrder } from '@/features/work-orders/api'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import {
  IN_PROGRESS_WORKFLOW_STATUS,
  OPEN_WORKFLOW_STATUS,
  WORKFLOW_STATUS_FIELDS,
} from '@/features/work-orders/work-order-workflow-fixtures'
import type { FieldPermission } from '@/features/authorization/types'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Spec 0322 AC-014: the "Stato lavorazione" row of the detail, editable in place from the resolved set. */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))
vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrderFormContext: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const FIELD_LABEL = () => label('workOrders.detail.workflowStatus')
const queryPencil = () => screen.queryByRole('button', { name: label('common.inlineEdit.edit', { field: FIELD_LABEL() }) })

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
    status: { value: 'open', is_force_closed: false },
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockResolvedValue({ applicable_attributes: [], attribute_layout: null })
})

describe('WorkOrderDetailView — Stato lavorazione', () => {
  it('shows the current working status next to its advancement', () => {
    renderDetail(workOrder())

    expect(screen.getByText(FIELD_LABEL())).toBeInTheDocument()
    expect(screen.getByText(OPEN_WORKFLOW_STATUS.name)).toBeInTheDocument()
    expect(screen.getByText(`${label('workflowStatuses.progressPercentage')}: 0%`)).toBeInTheDocument()
  })

  it('lists only the resolved set and PATCHes the chosen id alone', async () => {
    const record = workOrder()
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({
      ...record,
      work_order_workflow_status_id: IN_PROGRESS_WORKFLOW_STATUS.id,
      work_order_workflow_status: IN_PROGRESS_WORKFLOW_STATUS,
    })
    renderDetail(record)

    fireEvent.click(queryPencil()!)
    fireEvent.click(screen.getByRole('combobox', { name: FIELD_LABEL() }))
    const options = screen.getAllByRole('option')
    expect(options).toHaveLength(2)
    fireEvent.click(within(screen.getByRole('listbox')).getByRole('option', { name: new RegExp(IN_PROGRESS_WORKFLOW_STATUS.name) }))
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(4, {
        work_order_workflow_status_id: IN_PROGRESS_WORKFLOW_STATUS.id,
      }),
    )
  })

  it('offers no edit when the field permission is read-only', () => {
    renderDetail(workOrder({ ...FIELD_PERMISSION, editable: false, readonly: true }))

    expect(screen.getByText(OPEN_WORKFLOW_STATUS.name)).toBeInTheDocument()
    expect(queryPencil()).not.toBeInTheDocument()
  })

  it('shows the server 422 on the field and keeps the editor open (spec 0323 D-2)', async () => {
    const message = "Lo stato porta l'avanzamento al 20%, che non supera l'avanzamento gia' raggiunto (20%)."
    vi.mocked(updateWorkOrder).mockRejectedValueOnce(
      Object.assign(new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { work_order_workflow_status_id: [message] } },
      } as AxiosResponse), {}),
    )
    renderDetail(workOrder())

    fireEvent.click(queryPencil()!)
    fireEvent.click(screen.getByRole('combobox', { name: FIELD_LABEL() }))
    fireEvent.click(within(screen.getByRole('listbox')).getByRole('option', { name: new RegExp(IN_PROGRESS_WORKFLOW_STATUS.name) }))
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('common.inlineEdit.save') })).toBeInTheDocument()
  })
})
