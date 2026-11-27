import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { fetchWorkOrder, fetchWorkOrderFormContext } from '@/features/work-orders/api'
import { WorkOrderTeamDialog } from '@/features/work-orders/work-order-team-dialog'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0405 AC-004: the grid's "Responsabili e Partecipanti" dialog (legacy
 * `orders.responsabili-partecipanti`) shows the record's Team block, editable
 * with the detail's rules, and "Richiesta Proforma" with `request_proforma`.
 */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/work-orders/proforma-request-dialog', () => ({
  ProformaRequestDialog: ({ workOrderCode }: { workOrderCode: string }) => (
    <div role="alertdialog" aria-label={`proforma-${workOrderCode}`} />
  ),
}))

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, fetchWorkOrder: vi.fn(), fetchWorkOrderFormContext: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

function workOrder(actions: Record<string, boolean>, update = true): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0004',
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
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    description: null,
    is_multi_quote: false,
    source_quotes: [],
    contract_number: null,
    quote: null,
    contract: null,
    task_template: null,
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    locked_attributes: [],
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-01-01T09:00:00Z',
    permissions: {
      resource: { view: true, create: false, update, delete: false, export: false, import: false },
      fields: {},
      actions,
    },
  } as WorkOrderDetailWithPermissions
}

function renderDialog(record: WorkOrderDetailWithPermissions) {
  vi.mocked(fetchWorkOrder).mockResolvedValue(record)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <WorkOrderTeamDialog workOrderId={record.id} onOpenChange={onOpenChange} />
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return { onOpenChange }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(fetchWorkOrder).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockResolvedValue({ applicable_attributes: [], attribute_layout: null, locked_attributes: [] })
})

describe('WorkOrderTeamDialog (spec 0405)', () => {
  it('shows the supervisors and participants of the work order, editable with update', async () => {
    renderDialog(workOrder({}))

    expect(await screen.findByText('Ada Alberti')).toBeInTheDocument()
    expect(screen.getByText('Bruno Bianchi')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: label('workOrders.actions.team.label') })).toBeInTheDocument()
    expect(fetchWorkOrder).toHaveBeenCalledWith(4)
    expect(
      screen.getByRole('button', { name: label('common.inlineEdit.edit', { field: label('workOrders.detail.supervisors') }) }),
    ).toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (user directive 2026-11-27): the Team block left the Info tab for this popup, opened from
  // the detail's "..." menu too, so the participants' gap rule (spec 0195) is checked here.
  it('hydrates the participants editor without compacting a gap', async () => {
    const record = workOrder({})
    renderDialog({ ...record, participants: [{ id: 31, name: 'Bruno Bianchi', position: 3 }] })

    fireEvent.click(
      await screen.findByRole('button', {
        name: label('common.inlineEdit.edit', { field: label('workOrders.detail.participants') }),
      }),
    )

    expect(screen.getByText('Participant 1')).toBeInTheDocument()
    expect(screen.getByText('Participant 3')).toBeInTheDocument()
  })

  // User request 2026-11-27: the popup names its commessa, does not repeat its title as a section, counts the people.
  it('names the commessa under the title, with no section heading repeating it, and counts each role', async () => {
    renderDialog(workOrder({}))

    expect(await screen.findByText('COM-0004 · Installazione impianto')).toBeInTheDocument()
    expect(screen.getAllByRole('heading').map((heading) => heading.textContent)).toEqual([
      label('workOrders.actions.team.label'),
    ])
    expect(screen.getAllByRole('term').map((term) => term.textContent)).toEqual([
      `${label('workOrders.detail.supervisors')}(1)`,
      `${label('workOrders.detail.participants')}(1)`,
    ])
  })

  it('opens the proforma request with request_proforma, and closes', async () => {
    const { onOpenChange } = renderDialog(workOrder({ request_proforma: true }))

    fireEvent.click(await screen.findByRole('button', { name: label('proformaRequests.detailAction') }))
    expect(screen.getByRole('alertdialog', { name: 'proforma-COM-0004' })).toBeInTheDocument()

    fireEvent.click(screen.getAllByRole('button', { name: label('common.close') }).at(-1)!)
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('has no proforma request without request_proforma', async () => {
    renderDialog(workOrder({ request_proforma: false }))

    expect(await screen.findByText('Ada Alberti')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('proformaRequests.detailAction') })).not.toBeInTheDocument()
  })
})
