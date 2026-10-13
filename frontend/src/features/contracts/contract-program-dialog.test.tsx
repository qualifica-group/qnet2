import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { workOrders as workOrdersEn } from '@/i18n/locales/en-work-orders'
import { ContractProgramDialog } from '@/features/contracts/contract-program-dialog'
import { createContractWorkOrdersBatch, fetchContractProgrammableLines } from '@/features/contracts/api'
import type { ContractProgrammableLine } from '@/features/contracts/types'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'

/**
 * Spec 0215 (AC-020..AC-028): the dialog builds N groups from the offer
 * lines, shows the group of every line, saves them all with ONE batch call,
 * maps a 422 `groups.{i}.*` onto the right group and guards unsaved groups.
 * Decisione utente 2026-09-04: gli attributi dinamici NON compaiono qui.
 */

vi.mock('@/features/contracts/api', () => ({
  fetchContractProgrammableLines: vi.fn(),
  createContractWorkOrdersBatch: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchWorkOrderFormContextMock = vi.fn()
vi.mock('@/features/work-orders/api', () => ({
  fetchWorkOrderFormContext: (...args: [number[]]) => fetchWorkOrderFormContextMock(...args),
}))

const SUPERVISOR = { id: 21, label: 'Ada Alberti' }
const TASK_TEMPLATE = { id: 5, label: 'Onboarding cliente' }

function forSelectPage(items: { id: number; label: string }[]) {
  return {
    data: { pages: [{ items }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    ...actual,
    useForSelect: ({ resource }: { resource: string }) =>
      resource === 'task-templates' ? forSelectPage([TASK_TEMPLATE]) : forSelectPage([SUPERVISOR]),
    useForSelectLabels: () => new Map([[SUPERVISOR.id, SUPERVISOR.label]]),
  }
})

function line(id: number, name: string, category: string | null, workOrder = false): ContractProgrammableLine {
  return {
    id,
    sort_order: id,
    product: { id, code: `P-${id}`, name, category: category ? { id: category.length, name: category } : null },
    quantity: '2.00',
    unit_of_measure: { id: 1, name: 'Ora', symbol: 'h' },
    work_order: workOrder ? { id: 99, code: 'COM-0099' } : null,
  }
}

const LINES = [
  line(10, 'Consulenza', 'Servizi'),
  line(11, 'Installazione', null, true),
  line(12, 'Audit', 'Servizi'),
  line(13, 'Formazione', 'Corsi'),
  line(14, 'Licenza', null),
  line(15, 'Supporto', null),
]

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onCreated = vi.fn()
  const onOpenChange = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <ContractProgramDialog open onOpenChange={onOpenChange} contractId={7} onCreated={onCreated} />
    </QueryClientProvider>,
  )
  return { onCreated, onOpenChange }
}

const click = (name: string | RegExp) => fireEvent.click(screen.getByRole('button', { name }))
const selectLines = (...names: string[]) =>
  names.forEach((name) => fireEvent.click(screen.getByRole('checkbox', { name: `Select ${name}` })))
const group = (n: number) => within(screen.getByRole('region', { name: `Group ${n}` }))
const linesPanel = () => within(screen.getByRole('region', { name: 'Offer lines' }))

/** Fills "Valori comuni" (the only form fields on screen before a group exists). */
async function fillCommon() {
  fireEvent.change(screen.getByLabelText(/^Start date/), { target: { value: '2026-03-01' } })
  fireEvent.click(screen.getByRole('button', { name: /Supervisors/ }))
  fireEvent.click(await screen.findByRole('option', { name: /Ada Alberti/ }))
}

function axiosValidationError(errors: Record<string, string[]>) {
  return Object.assign(new Error('Unprocessable'), { isAxiosError: true, response: { status: 422, data: { errors } } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn, workOrders: workOrdersEn }, true, true)
})

beforeEach(() => {
  vi.mocked(fetchContractProgrammableLines).mockReset().mockResolvedValue(LINES)
  vi.mocked(createContractWorkOrdersBatch).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
  fetchWorkOrderFormContextMock.mockReset()
})

describe('ContractProgramDialog - lines (AC-028)', () => {
  it('shows an occupied line as visible but not selectable, naming the occupying commessa', async () => {
    renderDialog()

    expect(await screen.findByText('Installazione')).toBeInTheDocument()
    expect(screen.getByText('Already in COM-0099')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Select Installazione' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'Select Consulenza' })).not.toBeDisabled()
  })

  it('selects every free line from the header checkbox, leaving the occupied one out', async () => {
    renderDialog()
    await screen.findByText('Consulenza')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all free lines' }))

    expect(screen.getByRole('checkbox', { name: 'Select Consulenza' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Select Installazione' })).not.toBeChecked()
    expect(screen.getByText('5 lines selected')).toBeInTheDocument()
  })
})

describe('ContractProgramDialog - groups (AC-020, AC-021)', () => {
  it('turns 3 selected lines into Group 1, badges them "G1" and offers "Create 1 work order"', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    expect(screen.getByRole('button', { name: /^Create \d+ work orders?$/ })).toBeDisabled()

    selectLines('Consulenza', 'Audit', 'Formazione')
    click('New group')

    expect(group(1).getByText('Consulenza')).toBeInTheDocument()
    expect(group(1).getByText('Audit')).toBeInTheDocument()
    expect(group(1).getByText('Formazione')).toBeInTheDocument()
    expect(linesPanel().getAllByLabelText('Group 1')).toHaveLength(3)
    expect(screen.getByRole('checkbox', { name: 'Select Consulenza' })).not.toBeChecked()
    expect(screen.getByRole('button', { name: 'Create 1 work order' })).toBeEnabled()
  })

  it('adds lines to a group, opens a second group and MOVES a line between groups', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    selectLines('Consulenza', 'Audit', 'Formazione')
    click('New group')

    selectLines('Licenza', 'Supporto')
    fireEvent.keyDown(screen.getByRole('button', { name: 'Add to group' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('menuitem', { name: /^G1/ }))
    expect(group(1).getByText('Supporto')).toBeInTheDocument()

    selectLines('Audit')
    click('New group')
    expect(group(2).getByText('Audit')).toBeInTheDocument()
    expect(group(1).queryByText('Audit')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Create 2 work orders' })).toBeEnabled()
  })

  it('frees a line removed from its group and every line of a deleted group', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    selectLines('Consulenza', 'Audit')
    click('New group')

    fireEvent.click(group(1).getByRole('button', { name: 'Remove Audit from the group' }))
    expect(linesPanel().getAllByLabelText('Group 1')).toHaveLength(1)

    fireEvent.click(group(1).getByRole('button', { name: 'Remove group' }))
    expect(screen.queryByRole('region', { name: 'Group 1' })).not.toBeInTheDocument()
    expect(linesPanel().queryByLabelText('Group 1')).not.toBeInTheDocument()
    expect(screen.getByText('No work order yet')).toBeInTheDocument()
  })
})

describe('ContractProgramDialog - quick actions and common values (AC-022, AC-023)', () => {
  it('"One work order per line" makes one group per selected line', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    selectLines('Consulenza', 'Audit', 'Formazione')

    click('One work order per line')

    expect(screen.getByRole('button', { name: 'Create 3 work orders' })).toBeEnabled()
    expect(group(3).getByText('Formazione')).toBeInTheDocument()
  })

  it('"Group by category" uses every free line when nothing is selected, one group per category', async () => {
    renderDialog()
    await screen.findByText('Consulenza')

    click('Group by category')

    // Servizi (Consulenza, Audit), Corsi (Formazione), no category (Licenza, Supporto).
    expect(screen.getByRole('button', { name: 'Create 3 work orders' })).toBeEnabled()
    expect(group(1).getByText('Audit')).toBeInTheDocument()
    expect(group(3).getByText('Supporto')).toBeInTheDocument()
  })

  it('prefills the common start date with today, editable, and a new group inherits it', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    expect(screen.getByLabelText(/^Start date/)).toHaveValue(todayIsoDate())

    selectLines('Consulenza')
    click('New group')
    expect(group(1).getByLabelText(/^Start date/)).toHaveValue(todayIsoDate())
  })

  it('a new group inherits the common values; "Apply to all" overwrites them but never titles or lines; "Duplicate" copies them without lines', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    await fillCommon()
    selectLines('Consulenza')
    click('New group')

    expect(group(1).getByLabelText(/^Start date/)).toHaveValue('2026-03-01')
    fireEvent.change(group(1).getByLabelText(/^Title/), { target: { value: 'Mia commessa' } })

    fireEvent.change(screen.getAllByLabelText(/^Start date/)[0], { target: { value: '2026-05-05' } })
    expect(group(1).getByLabelText(/^Start date/)).toHaveValue('2026-03-01')
    click('Apply to all groups')
    expect(group(1).getByLabelText(/^Start date/)).toHaveValue('2026-05-05')
    expect(group(1).getByLabelText(/^Title/)).toHaveValue('Mia commessa')
    expect(group(1).getByText('Consulenza')).toBeInTheDocument()

    fireEvent.click(group(1).getByRole('button', { name: 'Duplicate group' }))
    expect(group(2).getByLabelText(/^Start date/)).toHaveValue('2026-05-05')
    expect(group(2).getByLabelText(/^Title/)).toHaveValue('')
    expect(group(2).getByText('No lines: select lines on the left and add them to this group.')).toBeInTheDocument()
  })
})

describe('ContractProgramDialog - save (AC-024, AC-025, AC-026)', () => {
  it('previews the automatic title as the placeholder and sends title null when blank (AC-024)', async () => {
    vi.mocked(createContractWorkOrdersBatch).mockResolvedValue([])
    renderDialog()
    await screen.findByText('Consulenza')
    await fillCommon()
    selectLines('Consulenza', 'Audit', 'Licenza')
    click('New group')

    expect(group(1).getByLabelText(/^Title/)).toHaveAttribute('placeholder', 'Automatic: COM-… - Consulenza + Audit + Licenza')
    click('Create 1 work order')

    await waitFor(() =>
      expect(createContractWorkOrdersBatch).toHaveBeenCalledWith(7, {
        groups: [
          {
            title: null,
            type: 'processing',
            start_date: '2026-03-01',
            supervisor_ids: [21],
            task_template_id: null,
            quote_line_ids: [10, 12, 14],
          },
        ],
      }),
    )
  })

  it('sends ONE batch with every group in order, then toasts, reports up and closes (AC-025)', async () => {
    const created = [{ id: 1 }, { id: 2 }]
    vi.mocked(createContractWorkOrdersBatch).mockResolvedValue(created as never)
    const { onCreated, onOpenChange } = renderDialog()
    await screen.findByText('Consulenza')
    await fillCommon()
    selectLines('Consulenza')
    click('New group')
    selectLines('Audit')
    click('New group')
    fireEvent.change(group(2).getByLabelText(/^Title/), { target: { value: 'Seconda' } })

    click('Create 2 work orders')

    await waitFor(() => expect(onCreated).toHaveBeenCalledWith(created))
    expect(createContractWorkOrdersBatch).toHaveBeenCalledTimes(1)
    const groups = vi.mocked(createContractWorkOrdersBatch).mock.calls[0][1].groups
    expect(groups.map((g) => [g.title, g.quote_line_ids])).toEqual([[null, [10]], ['Seconda', [12]]])
    expect(toast.success).toHaveBeenCalledWith('Created 2 work orders')
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('maps a 422 on groups.1.start_date to Group 2, expands it and flags its header (AC-025)', async () => {
    vi.mocked(createContractWorkOrdersBatch).mockRejectedValue(
      axiosValidationError({ 'groups.1.start_date': ['The start date is not valid.'] }),
    )
    const { onCreated } = renderDialog()
    await screen.findByText('Consulenza')
    await fillCommon()
    selectLines('Consulenza')
    click('New group')
    selectLines('Audit')
    click('New group')
    fireEvent.click(screen.getByRole('button', { name: 'Show or hide Group 2' }))
    expect(screen.queryByLabelText('Remove Audit from the group')).not.toBeInTheDocument()

    click('Create 2 work orders')

    expect(await screen.findByText('The start date is not valid.')).toBeInTheDocument()
    expect(group(2).getByRole('img', { name: 'This group has errors' })).toBeInTheDocument()
    expect(group(1).queryByRole('img', { name: 'This group has errors' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Create 2 work orders' })).toBeEnabled()
    expect(onCreated).not.toHaveBeenCalled()
  })

  it('shows a generic toast for a non-validation failure and keeps the groups', async () => {
    vi.mocked(createContractWorkOrdersBatch).mockRejectedValue(new Error('boom'))
    renderDialog()
    await screen.findByText('Consulenza')
    await fillCommon()
    selectLines('Consulenza')
    click('New group')

    click('Create 1 work order')

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Unable to create the work orders. Please try again.'))
    expect(screen.getByRole('region', { name: 'Group 1' })).toBeInTheDocument()
  })

  it('blocks the request when a group misses date, supervisors or lines (AC-026)', async () => {
    renderDialog()
    await screen.findByText('Consulenza')
    click('New group')
    fireEvent.change(group(1).getByLabelText(/^Start date/), { target: { value: '' } })

    click('Create 1 work order')

    expect(await screen.findByText('The start date is required.')).toBeInTheDocument()
    expect(screen.getByText('Pick at least one supervisor.')).toBeInTheDocument()
    expect(screen.getByText('Select at least one line.')).toBeInTheDocument()
    expect(group(1).getByRole('img', { name: 'This group has errors' })).toBeInTheDocument()
    expect(createContractWorkOrdersBatch).not.toHaveBeenCalled()
  })
})

describe('ContractProgramDialog - closing (AC-027)', () => {
  it('closes at once when there are no groups', async () => {
    const { onOpenChange } = renderDialog()
    await screen.findByText('Consulenza')

    click('Cancel')

    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('asks for confirmation with groups; "Keep editing" keeps them, "Leave" closes', async () => {
    const { onOpenChange } = renderDialog()
    await screen.findByText('Consulenza')
    selectLines('Consulenza')
    click('New group')

    click('Cancel')
    const alert = await screen.findByRole('alertdialog')
    expect(within(alert).getByText('You have 1 unsaved work order. Leave anyway?')).toBeInTheDocument()
    fireEvent.click(within(alert).getByRole('button', { name: 'Keep editing' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(onOpenChange).not.toHaveBeenCalled()
    expect(screen.getByRole('region', { name: 'Group 1' })).toBeInTheDocument()

    click('Cancel')
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Leave without saving' }))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
