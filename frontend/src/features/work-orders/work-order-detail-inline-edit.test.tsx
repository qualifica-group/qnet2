import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import axios from 'axios'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { fetchWorkOrderFormContext, updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { OPEN_WORKFLOW_STATUS, WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import type { FieldPermission } from '@/features/authorization/types'
import type { ApplicableAttributeSummary, WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0195 applied to Commesse (user directive 2026-10-06): no edit page,
 * a pencil per editable row — the flexible fields included, one row each —
 * Save PATCHes that field alone, Cancel restores it without any request; the
 * forced closure is a header action, not a row.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))

const fetchContractDataMock = vi.hoisted(() => vi.fn())
vi.mock('@/features/work-order-contract-data/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-order-contract-data/api')>('@/features/work-order-contract-data/api')
  return { ...actual, fetchWorkOrderContractData: fetchContractDataMock }
})

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrderFormContext: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const pencilName = (field: string) => label('common.inlineEdit.edit', { field })
const queryPencil = (field: string) => screen.queryByRole('button', { name: pencilName(field) })

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

const SITE_ACCESS: ApplicableAttributeSummary = {
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
  is_synced: false,
  sort_order: 0,
  options: [],
}

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
    force_close_reason: null,
    open_tasks_count: 2,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 3 }],
    description: null,
    internal_notes: null,
    is_multi_quote: false,
    source_quotes: [],
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: null,
    task_template: null,
    quote_lines: [{ id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' }, registry: null }],
    applicable_attributes: [SITE_ACCESS],
    attribute_layout: null,
    locked_attributes: [],
    attribute_values: { site_access: 'Gate 3' },
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      // As the backend ceiling reports a persisted record (D-1/D-5, spec 0124 D-5).
      fields: { code: READ_ONLY_FIELD, quote_id: READ_ONLY_FIELD, task_template_id: READ_ONLY_FIELD },
      actions: { force_close: true, reopen: false },
    },
    ...overrides,
  }
}

function renderDetail(record: WorkOrderDetailWithPermissions, onChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  // The force-close dialog reads the closing statuses from the detail cache the page host fills.
  client.setQueryData(workOrderDetailQueryKey(record.id), record)
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <WorkOrderDetailView workOrder={record} onChanged={onChanged} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return { onChanged }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockResolvedValue({ applicable_attributes: [], attribute_layout: null, locked_attributes: [] })
})

// REQUIREMENT CHANGED (spec 0403): the Info tab no longer has the Title row, the description is the sample field.
describe('WorkOrderDetailView — in-place editing', () => {
  it('saves the edited field alone and reports the change', async () => {
    const record = workOrder()
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({ ...record, description: 'Nuovo testo' })
    const { onChanged } = renderDetail(record)

    fireEvent.click(queryPencil(label('workOrders.detail.description'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.detail.description') }), {
      target: { value: 'Nuovo testo' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(4, { description: 'Nuovo testo' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalledOnce())
    expect(screen.queryByRole('button', { name: label('common.inlineEdit.save') })).not.toBeInTheDocument()
  })

  it('restores the persisted value on cancel, without any request', () => {
    renderDetail(workOrder())

    fireEvent.click(queryPencil(label('workOrders.detail.description'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.detail.description') }), {
      target: { value: 'Da scartare' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))

    expect(updateWorkOrder).not.toHaveBeenCalled()
    expect(screen.queryByRole('textbox', { name: label('workOrders.detail.description') })).not.toBeInTheDocument()
  })

  it('keeps the editor open with the server message on a refused save', async () => {
    vi.mocked(updateWorkOrder).mockRejectedValueOnce(
      new axios.AxiosError('422', '422', undefined, undefined, {
        status: 422,
        data: { errors: { description: ['Descrizione non valida.'] } },
      } as never),
    )
    renderDetail(workOrder())

    fireEvent.click(queryPencil(label('workOrders.detail.description'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.detail.description') }), {
      target: { value: 'X' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    expect(await screen.findByText('Descrizione non valida.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: label('workOrders.detail.description') })).toBeInTheDocument()
  })

  it('offers no edit on the fields fixed at creation (code, task template)', () => {
    renderDetail(workOrder())

    expect(queryPencil(label('workOrders.form.code'))).not.toBeInTheDocument()
    expect(queryPencil(label('workOrders.detail.taskTemplate'))).not.toBeInTheDocument()
    expect(queryPencil(label('workOrders.detail.description'))).toBeInTheDocument()
  })

  it('offers no edit at all to an actor whose fields are read-only', () => {
    const record = workOrder()
    renderDetail({
      ...record,
      permissions: {
        ...record.permissions,
        fields: Object.fromEntries(
          ['code', 'task_template_id', 'title', 'type', 'start_date', 'callback_date', 'description', 'internal_notes', 'supervisor_ids',
            'participant_slots', 'quote_line_ids', 'attribute_values', 'work_order_workflow_status_id'].map((key) => [key, READ_ONLY_FIELD]),
        ),
      },
    })

    expect(screen.queryByRole('button', { name: /^Edit / })).not.toBeInTheDocument()
  })

  it('hydrates the participants editor without compacting a gap', () => {
    renderDetail(workOrder())

    fireEvent.click(queryPencil(label('workOrders.detail.participants'))!)

    expect(screen.getByText('Participant 1')).toBeInTheDocument()
    expect(screen.getByText('Participant 3')).toBeInTheDocument()
  })

  it('offers no way to edit the product lines: they are fixed at creation', () => {
    renderDetail(workOrder())

    expect(queryPencil(label('workOrders.detail.lines'))).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /lines/i })).not.toBeInTheDocument()
    expect(fetchWorkOrderFormContext).not.toHaveBeenCalled()
  })
})

describe('WorkOrderDetailView — flexible fields, one in-place row each', () => {
  it('edits one Attribute and PATCHes the map', async () => {
    const record = workOrder()
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({ ...record, attribute_values: { site_access: 'Gate 4' } })
    renderDetail(record)

    expect(screen.getByText('Gate 3')).toBeInTheDocument()
    fireEvent.click(queryPencil('Site access')!)
    fireEvent.change(screen.getByRole('textbox', { name: 'Site access' }), { target: { value: 'Gate 4' } })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(4, { attribute_values: { site_access: 'Gate 4' } }),
    )
  })

  it('offers no edit on the Attributes when attribute_values is read-only', () => {
    const record = workOrder()
    renderDetail({
      ...record,
      permissions: { ...record.permissions, fields: { ...record.permissions.fields, attribute_values: READ_ONLY_FIELD } },
    })

    expect(screen.getByText('Gate 3')).toBeInTheDocument()
    expect(queryPencil('Site access')).not.toBeInTheDocument()
  })
})

describe('WorkOrderDetailView — forced closure as an action (user directive 2026-10-06)', () => {
  it('has no closure row to edit, only the header action', () => {
    renderDetail(workOrder())

    expect(queryPencil(label('workOrders.form.isForceClosed'))).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('actions.forceClose') })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('actions.reopen') })).not.toBeInTheDocument()
  })

  it('force-closes with the reason through the dialog, warning about the open tasks', async () => {
    const closedStatus = { ...OPEN_WORKFLOW_STATUS, id: 9, name: 'Chiusa', group: 'closed' as const, system_key: 'closed' as const }
    const record = workOrder({
      work_order_workflow_statuses: [...WORKFLOW_STATUS_FIELDS.work_order_workflow_statuses, closedStatus],
    })
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({
      ...record,
      is_force_closed: true,
      force_close_reason: 'Cliente insolvente',
      permissions: { ...record.permissions, actions: { force_close: false, reopen: true } },
    })
    const { onChanged } = renderDetail(record)

    fireEvent.click(screen.getByRole('button', { name: label('actions.forceClose') }))
    expect(await screen.findByRole('alert')).toHaveTextContent('2 open tasks will be closed with a negative outcome.')
    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.form.forceCloseReason') }), {
      target: { value: 'Cliente insolvente' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('workOrders.actions.forceClose.submit') }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(4, {
        is_force_closed: true,
        force_close_reason: 'Cliente insolvente',
        force_close_status_id: 9,
      }),
    )
    await waitFor(() => expect(onChanged).toHaveBeenCalledOnce())
  })

  it('reopens a force-closed record after the confirmation, showing its reason read-only', async () => {
    const record = workOrder({
      is_force_closed: true,
      force_close_reason: 'Cliente insolvente',
      status: { value: 'cancelled', is_force_closed: true },
    })
    record.permissions.actions = { force_close: false, reopen: true }
    vi.mocked(updateWorkOrder).mockResolvedValueOnce({ ...record, is_force_closed: false, force_close_reason: null })
    renderDetail(record)

    expect(screen.getByText('Cliente insolvente')).toBeInTheDocument()
    expect(queryPencil(label('workOrders.detail.forceCloseReason'))).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: label('actions.reopen') }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(4, { is_force_closed: false, force_close_reason: null }),
    )
  })
})

// Spec 0431: a project work order born without an offer owns its references.
describe('WorkOrderDetailView — work order born without an offer', () => {
  const standalone = () =>
    workOrder({
      type: 'project',
      is_standalone: true,
      quote: null,
      contract_number: null,
      quote_lines: [],
      registry: { id: 5, name: 'Cliente Interno' },
      company: { id: 6, name: 'Societa Uno' },
      company_site: { id: 7, name: 'Sede Nord' },
      operational_site: { id: 8, label: 'Via Roma 1, Bari' },
      product_lines: [{ id: 1, business_function: { id: 3, name: 'Formazione' }, product_category: { id: 2, name: 'Corsi' } }],
      products: [{ id: 40, code: 'PRD-0040', name: 'Audit di prima certificazione' }],
    })

  it('edits client, company, sites and business functions in place, with no contract row', () => {
    renderDetail(standalone())

    for (const field of [
      'workOrders.form.references.registry',
      'workOrders.detail.company',
      'workOrders.detail.companySite',
      'workOrders.detail.operationalSite',
      'workOrders.form.references.productLines',
      'workOrders.form.references.products',
    ]) {
      expect(queryPencil(label(field))).toBeInTheDocument()
    }
    expect(screen.getAllByText('Cliente Interno').length).toBeGreaterThan(0)
    expect(screen.getByText('Audit di prima certificazione')).toBeInTheDocument()
    // D-18: the business function is read from the product line, never edited.
    expect(screen.getAllByText(/Formazione/).length).toBeGreaterThan(0)
    expect(queryPencil(label('workOrders.detail.businessFunction'))).not.toBeInTheDocument()
    expect(screen.queryByText(label('workOrders.detail.contract'))).not.toBeInTheDocument()
  })

  // User directives 2026-11-25: lines and products in a section of their own, no amount.
  it('shows the product lines and products in their own section, and no amount', () => {
    // The server reports 0.00 for a work order with no lines: the row must still not show.
    renderDetail({ ...standalone(), amount: '0.00' })

    expect(screen.getByText(label('workOrders.detail.sections.products'))).toBeInTheDocument()
    expect(screen.queryByText(label('workOrders.detail.amount'))).not.toBeInTheDocument()
  })

  it('shows no offer-lines block in the contract data, even to who may see it', () => {
    const record = standalone()
    renderDetail({
      ...record,
      permissions: { ...record.permissions, actions: { ...record.permissions.actions, view_costs: true, view_contract_data: true } },
    })

    expect(fetchContractDataMock).not.toHaveBeenCalled()
    expect(screen.queryByText(label('workOrders.contractData.empty'))).not.toBeInTheDocument()
  })

  it('places the business function under the operational site', () => {
    renderDetail(standalone())

    const operationalSite = screen.getByText(label('workOrders.detail.operationalSite'))
    const businessFunction = screen.getByText(label('workOrders.detail.businessFunction'))
    expect(operationalSite.compareDocumentPosition(businessFunction) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(screen.getAllByText('Formazione').length).toBeGreaterThan(0)
  })

  it('keeps the type read-only: always a project work order', () => {
    renderDetail(standalone())

    expect(queryPencil(label('workOrders.form.type'))).not.toBeInTheDocument()
    expect(screen.getAllByText(label('workOrders.options.type.project')).length).toBeGreaterThan(0)
  })

  it('shows no editable reference on a work order born from an offer', () => {
    renderDetail(workOrder({ company: { id: 6, name: 'Societa Uno' } }))

    expect(queryPencil(label('workOrders.form.references.registry'))).not.toBeInTheDocument()
    expect(queryPencil(label('workOrders.detail.company'))).not.toBeInTheDocument()
  })
})
