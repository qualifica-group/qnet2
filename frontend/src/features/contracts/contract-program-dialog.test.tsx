import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { workOrders as workOrdersEn } from '@/i18n/locales/en-work-orders'
import { ContractProgramDialog } from '@/features/contracts/contract-program-dialog'
import { createContractWorkOrder, fetchContractProgrammableLines } from '@/features/contracts/api'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/**
 * Spec 0095 AC-060/061/062: occupied lines are visible but not selectable,
 * the submit is blocked without a title or without any selected line, and a
 * successful generation closes the dialog and reports the new Commessa up.
 * Spec 0096 AC-060: "Data inizio" and "Responsabili" are required too.
 * Decisione utente 2026-09-04: gli attributi dinamici della commessa NON
 * compaiono in questo dialog (revoca AC-019 di spec 0098).
 */

vi.mock('@/features/contracts/api', () => ({
  fetchContractProgrammableLines: vi.fn(),
  createContractWorkOrder: vi.fn(),
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
      }),
    )
    expect(onCreated).toHaveBeenCalledWith(created)
  })
})

/** Decisione utente 2026-09-04: nessun attributo dinamico in questo dialog. */
describe('ContractProgramDialog — no dynamic attribute fields', () => {
  it('does not mount the "Additional information" block, nor resolve attributes, when a line is picked', async () => {
    renderDialog()
    await screen.findByText('Consulenza')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select Consulenza' }))

    await waitFor(() => expect(screen.getByRole('checkbox', { name: 'Select Consulenza' })).toBeChecked())
    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
    expect(fetchWorkOrderFormContextMock).not.toHaveBeenCalled()
  })
})
