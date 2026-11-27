import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { toast } from 'sonner'
import { fetchForSelect } from '@/features/for-select/api'
import { fetchWorkOrder, updateWorkOrder } from '@/features/work-orders/api'
import { WorkOrderTeamDialog } from '@/features/work-orders/work-order-team-dialog'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import type { FieldPermission } from '@/features/authorization/types'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0405 AC-004: the "Responsabili e Partecipanti" dialog (legacy
 * `orders.responsabili-partecipanti`) shows the commessa's team and
 * "Richiesta Proforma" with `request_proforma`.
 *
 * REQUIREMENT CHANGED (user request 2026-11-27, "come i migliori CRM"): the
 * team is a CRM team panel, each add/removal/move saved at once with Undo,
 * instead of the detail's in-place rows with a pencil.
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
  return { ...actual, fetchWorkOrder: vi.fn(), updateWorkOrder: vi.fn() }
})

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

const READ_ONLY: FieldPermission = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }
const HIDDEN: FieldPermission = { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false }

/** Presses the "Undo" of the last success toast. */
function pressUndoOfLastToast(): void {
  const action = vi.mocked(toast.success).mock.calls.at(-1)?.[1]?.action
  if (action === undefined || !('onClick' in (action as object))) {
    throw new Error('The last toast has no Undo action')
  }
  ;(action as { onClick: (event: unknown) => void }).onClick({})
}

function workOrder(
  actions: Record<string, boolean>,
  update = true,
  fields: Record<string, FieldPermission> = {},
): WorkOrderDetailWithPermissions {
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
    participants: [
      { id: 31, name: 'Bruno Bianchi', position: 1 },
      { id: 32, name: 'Carla Conti', position: 3 },
    ],
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
      fields,
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
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(fetchForSelect).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('WorkOrderTeamDialog (spec 0405)', () => {
  it('names the commessa, counts each group and numbers the partecipanti, free slots included', async () => {
    renderDialog(workOrder({}))

    expect(await screen.findByText('COM-0004 · Installazione impianto')).toBeInTheDocument()
    const supervisors = screen.getByRole('region', { name: /^Supervisors\s*1$/ })
    expect(within(supervisors).getByText('Ada Alberti')).toBeInTheDocument()
    const participants = screen.getByRole('region', { name: /^Participants\s*2$/ })
    expect(within(participants).getAllByRole('listitem').map((item) => item.getAttribute('aria-label'))).toEqual([
      'Participant 1: Bruno Bianchi',
      'Participant 2',
      'Participant 3: Carla Conti',
    ])
    expect(within(participants).getByText('Free slot')).toBeInTheDocument()
    expect(fetchWorkOrder).toHaveBeenCalledWith(4)
  })

  it('names a generated title once, without repeating the code it starts with', async () => {
    renderDialog({ ...workOrder({}), title: 'COM-0004 - Adeguamento normativo' })

    expect(await screen.findByText('COM-0004 - Adeguamento normativo')).toBeInTheDocument()
    expect(screen.queryByText(/COM-0004 · COM-0004/)).not.toBeInTheDocument()
  })

  it('removes a responsabile with the cross at once, and Undo puts them back', async () => {
    const record = workOrder({})
    vi.mocked(updateWorkOrder).mockResolvedValue({ ...record, supervisors: [] })
    renderDialog(record)

    fireEvent.click(await screen.findByRole('button', { name: 'Remove Ada Alberti' }))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(4, { supervisor_ids: [] }))
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Team updated.', expect.anything()))
    pressUndoOfLastToast()
    await waitFor(() => expect(updateWorkOrder).toHaveBeenLastCalledWith(4, { supervisor_ids: [21] }))
  })

  it('adds a partecipante from the Add list into the first free slot', async () => {
    const record = workOrder({})
    vi.mocked(fetchForSelect).mockResolvedValue({
      items: [{ id: 41, label: 'Dario Dini' }],
      pagination: { offset: 0, limit: 25, total: 1 },
      export_link: null,
    } as Awaited<ReturnType<typeof fetchForSelect>>)
    vi.mocked(updateWorkOrder).mockResolvedValue(record)
    renderDialog(record)

    fireEvent.click(await screen.findByRole('button', { name: 'Add participant' }))
    // Inside the dialog's own node: portaled to `body`, the modal would block the list's wheel scrolling.
    expect(within(screen.getByRole('dialog', { name: 'Supervisors and Participants' })).getByRole('listbox', { name: 'Add participant' })).toBeInTheDocument()
    fireEvent.click(await screen.findByRole('option', { name: /Dario Dini/ }))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(4, { participant_slots: [31, 41, 32] }))
  })

  it('moves a partecipante down from the row menu, swapping with the slot below', async () => {
    const record = workOrder({})
    vi.mocked(updateWorkOrder).mockResolvedValue(record)
    renderDialog(record)

    fireEvent.pointerDown(await screen.findByRole('button', { name: 'Actions for Bruno Bianchi' }), { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Move down' }))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(4, { participant_slots: [null, 31, 32] }))
  })

  it('shows the server refusal and keeps the team as it was', async () => {
    vi.mocked(updateWorkOrder).mockRejectedValue(new Error('network'))
    renderDialog(workOrder({}))

    fireEvent.click(await screen.findByRole('button', { name: 'Remove Ada Alberti' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('workOrders.form.genericError')))
    expect(screen.getByText('Ada Alberti')).toBeInTheDocument()
  })

  it('offers no change on a read-only field and leaves a hidden one out', async () => {
    renderDialog(workOrder({}, true, { supervisor_ids: READ_ONLY, participant_slots: HIDDEN }))

    expect(await screen.findByText('Ada Alberti')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add supervisor' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove Ada Alberti' })).not.toBeInTheDocument()
    expect(screen.queryByRole('region', { name: /Participants/ })).not.toBeInTheDocument()
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
