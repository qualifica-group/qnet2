import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { WorkOrderCostsSection } from '@/features/work-order-costs/work-order-costs-section'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'
import { OVERVIEW } from '@/features/work-order-costs/work-order-costs-fixtures'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchCostsMock = vi.fn<(id: number) => Promise<WorkOrderCostOverview>>()
const syncCostsMock = vi.fn<(id: number, payload: unknown) => Promise<WorkOrderCostOverview>>()
vi.mock('@/features/work-order-costs/api', () => ({
  fetchWorkOrderCosts: (id: number) => fetchCostsMock(id),
  syncWorkOrderCosts: (id: number, payload: unknown) => syncCostsMock(id, payload),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const PAGINATION = { offset: 0, limit: 25, total: 1 }
const PRODUCT_ITEM = {
  id: 9,
  label: 'Noleggio auto',
  subtitle: 'Spese',
  meta: {
    category_id: null,
    description: null,
    code: 'CST-0009',
    price: null,
    cost: '12.50',
    vat_rate_id: 3,
    vat_rate_name: 'IVA 22%',
    vat_rate: '22.00',
    unit_of_measure: { id: 1, name: 'Giorno', symbol: 'gg' },
    product_typology: null,
    cost_type: { id: 6, name: 'Travel', color: 'blue' },
  },
}

function renderSection(canManage: boolean) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderCostsSection workOrderId={4} canManage={canManage} />
    </QueryClientProvider>,
  )
}

async function openEditor(canManage: boolean) {
  renderSection(canManage)
  fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Actual costs' }), { button: 0 })
  fireEvent.click(screen.getByRole('tab', { name: 'Actual costs' }))
}

/**
 * Spec 0270: rows come from the catalogue beside the cards. REQUIREMENT
 * CHANGED: no "Add row" + per-row product combobox any more.
 */
async function addFromCatalogue(times = 1) {
  for (let pick = 0; pick < times; pick += 1) {
    fireEvent.click(await screen.findByRole('option', { name: /Noleggio auto/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Add 1 selected' }))
  }
}

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { message: 'invalid', errors },
  } as AxiosResponse)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCostsMock.mockReset().mockResolvedValue(OVERVIEW)
  syncCostsMock.mockReset().mockResolvedValue(OVERVIEW)
  fetchForSelectMock.mockReset().mockImplementation((resource: string) =>
    Promise.resolve({
      items:
        resource === 'products'
          ? [PRODUCT_ITEM]
          : resource === 'cost-types'
            ? [{ id: 12, label: 'Fuel', meta: { color: 'amber' } }]
            : [{ id: 41, label: 'Fornitore Due' }],
      pagination: PAGINATION,
      export_link: null,
    }),
  )
})

describe('WorkOrderCostsSection — Comparison tab (AC-011)', () => {
  it('renders totals, per-line rows, unattributed line and the unallocated budget block', async () => {
    renderSection(false)

    expect(await screen.findByRole('tab', { name: 'Comparison' })).toBeInTheDocument()
    expect(screen.getAllByText('Budgeted cost')[0].parentElement).toHaveTextContent('80.00')
    expect(screen.getAllByText('Actual margin')[0].parentElement).toHaveTextContent('700.00')

    const table = screen.getByRole('table', { name: /per revenue line/i })
    expect(within(table).getByRole('rowheader', { name: /Consulenza/ })).toBeInTheDocument()
    expect(within(table).getByRole('rowheader', { name: /Installazione/ })).toBeInTheDocument()
    expect(within(table).getByRole('rowheader', { name: 'Unattributed' })).toBeInTheDocument()

    expect(screen.getByText('Unallocated offer costs (1)')).toBeInTheDocument()
    expect(screen.getByText('30.00')).toBeInTheDocument()
  })

  it('flags an overrun with a visible label, not only colour', async () => {
    renderSection(false)

    await screen.findByRole('tab', { name: 'Comparison' })

    expect(screen.getAllByText('Overrun').length).toBeGreaterThan(0)
  })

  it('shows an error state with retry when the overview fails to load', async () => {
    fetchCostsMock.mockRejectedValueOnce(new Error('boom'))
    renderSection(false)

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the work order costs.')

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('tab', { name: 'Comparison' })).toBeInTheDocument()
  })
})

describe('WorkOrderCostsSection — Actual costs editor (AC-012)', () => {
  it('is read-only without manage_costs: no catalogue, no save, disabled fields', async () => {
    await openEditor(false)

    expect(await screen.findByText('You do not have permission to edit the actual costs.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Product catalogue' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Quantity, row 1' })).toBeDisabled()
  })

  it('adds a catalogue product as a row dated today', async () => {
    await openEditor(true)

    await addFromCatalogue()

    expect(screen.getByLabelText('Cost date, row 2')).toHaveValue(todayIsoDate())
  })

  it('AC-003: lists the whole COST catalogue and lets the same product be added twice', async () => {
    await openEditor(true)

    await addFromCatalogue(2)

    expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ params: { usage: 'COST' } }))
    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 2' })).toHaveValue(12.5)
    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 3' })).toHaveValue(12.5)
    expect(screen.queryByRole('button', { name: 'Add row' })).not.toBeInTheDocument()
  })

  it('saves the exact contract payload for an edited row and a new picked row', async () => {
    await openEditor(true)

    fireEvent.change(await screen.findByRole('spinbutton', { name: 'Quantity, row 1' }), { target: { value: '3' } })

    await addFromCatalogue()
    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier, row 2' }))
    fireEvent.click(await screen.findByRole('option', { name: /Fornitore Due/ }))
    fireEvent.change(screen.getByLabelText('Document reference, row 2'), { target: { value: 'FT-99' } })

    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 2' })).toHaveValue(12.5)
    // AC-004: the block's net total follows the cards (3 x 50 + 1 x 12.50).
    expect(within(screen.getByRole('region', { name: 'Actual costs' })).getAllByText('162.50').length).toBeGreaterThan(0)

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(syncCostsMock).toHaveBeenCalledTimes(1))
    expect(syncCostsMock).toHaveBeenCalledWith(4, {
      lines: [
        {
          id: 5,
          product_id: 7,
          quantity: 3,
          unit_price: 50,
          vat_rate_id: 3,
          quote_line_id: 11,
          cost_type_id: null,
          incurred_on: '2026-09-15',
          supplier_id: 40,
          document_reference: 'FT-12',
          additional_description: null,
        },
        {
          product_id: 9,
          quantity: 1,
          unit_price: 12.5,
          vat_rate_id: 3,
          quote_line_id: null,
          cost_type_id: 6,
          incurred_on: todayIsoDate(),
          supplier_id: 41,
          document_reference: 'FT-99',
          additional_description: null,
        },
      ],
    })
  })

  it('AC-008: precompiles the cost type from the picked product and lets the user change it', async () => {
    await openEditor(true)

    await addFromCatalogue()

    expect(screen.getByRole('combobox', { name: 'Cost type, row 2' })).toHaveTextContent('Travel')

    fireEvent.click(screen.getByRole('combobox', { name: 'Cost type, row 2' }))
    fireEvent.click(await screen.findByRole('option', { name: /Fuel/ }))
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Cost type, row 2' })).toHaveTextContent('Fuel'))

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(syncCostsMock).toHaveBeenCalledTimes(1))
    const lines = (syncCostsMock.mock.calls[0][1] as { lines: Array<{ cost_type_id: number | null }> }).lines
    expect(lines.map((line) => line.cost_type_id)).toEqual([null, 12])
  })

  it('does not call the server while a row is invalid and shows the field error', async () => {
    await openEditor(true)

    // A catalogue row always carries its product, so the invalid row is an emptied quantity.
    fireEvent.change(await screen.findByRole('spinbutton', { name: 'Quantity, row 1' }), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findAllByText('Enter the quantity.')).toHaveLength(1)
    expect(syncCostsMock).not.toHaveBeenCalled()
  })

  it('Cancel restores the persisted rows', async () => {
    await openEditor(true)

    await addFromCatalogue()
    expect(screen.getByRole('spinbutton', { name: 'Quantity, row 2' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('spinbutton', { name: 'Quantity, row 2' })).not.toBeInTheDocument())
  })
})

describe('WorkOrderCostsSection — 422 mapping (AC-013)', () => {
  it('shows the server error on the field with the accessible triad', async () => {
    syncCostsMock.mockRejectedValueOnce(unprocessable({ 'lines.0.quantity': ['Quantity is not allowed.'] }))
    await openEditor(true)

    fireEvent.click(await screen.findByRole('button', { name: 'Save' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Quantity is not allowed.')

    const quantity = screen.getByRole('spinbutton', { name: 'Quantity, row 1' })
    expect(quantity).toHaveAttribute('aria-invalid', 'true')
    expect(quantity).toHaveAttribute('aria-describedby', alert.id)
  })
})
