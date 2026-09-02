import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { workOrders as workOrdersEn } from '@/i18n/locales/en-work-orders'
import { ContractProgramDialog } from '@/features/contracts/contract-program-dialog'
import { createContractWorkOrder, fetchContractProgrammableLines } from '@/features/contracts/api'
import type { ContractProgrammableLine } from '@/features/contracts/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0095 AC-060/061/062: occupied lines are visible but not selectable,
 * the submit is blocked without a title or without any selected line, and a
 * successful generation closes the dialog and reports the new Commessa up.
 * Spec 0096 AC-060: "Data inizio" and "Responsabili" are required too.
 */

vi.mock('@/features/contracts/api', () => ({
  fetchContractProgrammableLines: vi.fn(),
  createContractWorkOrder: vi.fn(),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchWorkOrderFormContextMock = vi.fn()
vi.mock('@/features/work-orders/api', () => ({
  fetchWorkOrderFormContext: (...args: [number[]]) => fetchWorkOrderFormContextMock(...args),
}))

/** The Responsabili picker's own options source (spec 0096); one user is enough. */
const SUPERVISOR = { id: 21, label: 'Ada Alberti' }

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    ...actual,
    useForSelect: () => ({
      data: { pages: [{ items: [{ id: 21, label: 'Ada Alberti' }] }] },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    }),
    useForSelectLabels: () => new Map([[SUPERVISOR.id, SUPERVISOR.label]]),
  }
})

const FREE_LINE: ContractProgrammableLine = {
  id: 10,
  sort_order: 0,
  product: { id: 1, code: 'P-1', name: 'Consulenza', category: { id: 5, name: 'Servizi' } },
  quantity: '2.00',
  unit_of_measure: { id: 1, name: 'Ora', symbol: 'h' },
  work_order: null,
}

const OCCUPIED_LINE: ContractProgrammableLine = {
  id: 11,
  sort_order: 1,
  product: { id: 2, code: 'P-2', name: 'Installazione', category: null },
  quantity: '1.00',
  unit_of_measure: null,
  work_order: { id: 99, code: 'COM-0099' },
}

function renderDialog(onCreated = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    onCreated,
    ...render(
      <QueryClientProvider client={client}>
        <ContractProgramDialog open onOpenChange={vi.fn()} contractId={7} onCreated={onCreated} />
      </QueryClientProvider>,
    ),
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn, workOrders: workOrdersEn }, true, true)
})

beforeEach(() => {
  vi.mocked(fetchContractProgrammableLines).mockReset()
  vi.mocked(createContractWorkOrder).mockReset()
  vi.mocked(fetchContractProgrammableLines).mockResolvedValue([FREE_LINE, OCCUPIED_LINE])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [],
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
  })
  fetchWorkOrderFormContextMock.mockReset()
  fetchWorkOrderFormContextMock.mockResolvedValue({ applicable_attributes: [], attribute_layout: null })
})

describe('ContractProgramDialog', () => {
  it('shows an occupied line as visible but not selectable, naming the occupying commessa (AC-060)', async () => {
    renderDialog()

    expect(await screen.findByText('Installazione')).toBeInTheDocument()
    expect(screen.getByText('Already in COM-0099')).toBeInTheDocument()

    const occupiedCheckbox = screen.getByRole('checkbox', { name: 'Select Installazione' })
    expect(occupiedCheckbox).toBeDisabled()

    const freeCheckbox = screen.getByRole('checkbox', { name: 'Select Consulenza' })
    expect(freeCheckbox).not.toBeDisabled()
  })

  it('blocks submit without a title and without any selected line (AC-061)', async () => {
    renderDialog()
    await screen.findByText('Consulenza')

    fireEvent.click(screen.getByRole('button', { name: 'Generate work order' }))

    expect(await screen.findByText('Title is required.')).toBeInTheDocument()
    expect(screen.getByText('Select at least one line.')).toBeInTheDocument()
    // Spec 0096, AC-060: the two new required fields block the submit too.
    expect(screen.getByText('The start date is required.')).toBeInTheDocument()
    expect(screen.getByText('Pick at least one supervisor.')).toBeInTheDocument()
    expect(createContractWorkOrder).not.toHaveBeenCalled()
  })

  it('generates the work order from the selected free line and reports it up (AC-062)', async () => {
    const created = { id: 501, code: 'COM-0501' }
    vi.mocked(createContractWorkOrder).mockResolvedValue(created as never)
    const onCreated = vi.fn()
    renderDialog(onCreated)
    await screen.findByText('Consulenza')

    fireEvent.change(screen.getByLabelText(/^Title/), { target: { value: 'Installazione impianto' } })
    fireEvent.change(screen.getByLabelText(/^Start date/), { target: { value: '2026-03-01' } })
    fireEvent.click(screen.getByRole('button', { name: /Supervisors/ }))
    fireEvent.click(await screen.findByRole('option', { name: /Ada Alberti/ }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Select Consulenza' }))
    fireEvent.click(screen.getByRole('button', { name: 'Generate work order' }))

    await waitFor(() =>
      expect(createContractWorkOrder).toHaveBeenCalledWith(7, {
        title: 'Installazione impianto',
        type: 'processing',
        start_date: '2026-03-01',
        supervisor_ids: [21],
        quote_line_ids: [10],
        attribute_values: {},
      }),
    )
    expect(onCreated).toHaveBeenCalledWith(created)
  })
})

/** Spec 0098 (AC-019): resolved live from the lines picked in this dialog. */
describe('ContractProgramDialog — dynamic attribute fields (spec 0098)', () => {
  it('is not mounted before any line is selected, and appears once one is', async () => {
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

    renderDialog()
    await screen.findByText('Consulenza')

    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
    expect(fetchWorkOrderFormContextMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select Consulenza' }))

    expect(await screen.findByText('Additional information')).toBeInTheDocument()
    await waitFor(() => expect(fetchWorkOrderFormContextMock).toHaveBeenCalledWith([10]))
    expect(await screen.findByLabelText('Site access')).toBeInTheDocument()
  })
})
