import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import type { FieldPermission } from '@/features/authorization/types'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0402: "Stato pagamento" is the commessa's own field, under the title
 * with the "Importo" of its lines, in "Dati contrattuali commessa".
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrderFormContext: vi.fn().mockResolvedValue({ applicable_attributes: [], attribute_layout: null }) }
})

const PAID = { id: 3, name: 'Saldato', color: 'green', allows_delivery: true }
const UNPAID = { id: 9, name: 'Insoluto', color: 'red', allows_delivery: false }

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: vi.fn().mockResolvedValue({
      items: [
        { id: 3, label: 'Saldato', color: 'green', allows_delivery: true },
        { id: 9, label: 'Insoluto', color: 'red', allows_delivery: false },
      ],
      pagination: { offset: 0, limit: 25, total: null, has_more: false },
      export_link: null,
    }),
  }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const EDITABLE: FieldPermission = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }
const READ_ONLY: FieldPermission = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }

function workOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
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
    work_order_payment_status_id: PAID.id,
    payment_status: PAID,
    amount: '1250.50',
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [],
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
      fields: { work_order_payment_status_id: EDITABLE },
      actions: {},
    },
    ...overrides,
  }
}

function renderDetail(record: WorkOrderDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(workOrderDetailQueryKey(record.id), record)
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <WorkOrderDetailView workOrder={record} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

function contractDataSection(): HTMLElement {
  return screen.getByRole('heading', { level: 3, name: label('workOrders.contractData.title') }).closest('section') as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
})

describe('WorkOrderDetailView — payment status and amount (spec 0402)', () => {
  it('shows title, amount and payment status first, in this order', () => {
    renderDetail(workOrder())

    const terms = within(contractDataSection()).getAllByRole('term').map((term) => term.textContent)
    expect(terms.slice(0, 3)).toEqual(['Title', 'Amount', 'Payment status'])
    expect(within(contractDataSection()).getByText('1,250.50')).toBeInTheDocument()
    expect(within(contractDataSection()).getByText('Saldato')).toBeInTheDocument()
  })

  it('has no amount row when the server sends none (no contract data visibility)', () => {
    renderDetail(workOrder({ amount: null }))

    expect(within(contractDataSection()).queryByText('Amount')).not.toBeInTheDocument()
  })

  it('saves a new payment status alone', async () => {
    const record = workOrder()
    vi.mocked(updateWorkOrder).mockResolvedValue({ ...record, work_order_payment_status_id: UNPAID.id, payment_status: UNPAID })
    renderDetail(record)

    const pencil = () => screen.getByRole('button', { name: label('common.inlineEdit.edit', { field: 'Payment status' }) })
    fireEvent.click(pencil())
    fireEvent.click(screen.getByRole('combobox', { name: 'Payment status' }))
    fireEvent.click(await screen.findByRole('option', { name: /Insoluto/ }))
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(4, { work_order_payment_status_id: UNPAID.id }))
  })

  it('offers no pencil to an actor who does not manage payments', () => {
    renderDetail(workOrder({ permissions: { ...workOrder().permissions, fields: { work_order_payment_status_id: READ_ONLY } } }))

    expect(screen.queryByRole('button', { name: label('common.inlineEdit.edit', { field: 'Payment status' }) })).not.toBeInTheDocument()
    expect(within(contractDataSection()).getByText('Saldato')).toBeInTheDocument()
  })
})
