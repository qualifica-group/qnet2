import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { WorkOrderCostsSection } from '@/features/work-order-costs/work-order-costs-section'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'
import { OVERVIEW } from '@/features/work-order-costs/work-order-costs-fixtures'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

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
    code: 'CST-0009',
    price: null,
    cost: '12.50',
    vat_rate_id: 3,
    vat_rate_name: 'IVA 22%',
    vat_rate: '22.00',
    unit_of_measure: { id: 1, name: 'Giorno', symbol: 'gg' },
    product_typology: null,
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
      items: resource === 'products' ? [PRODUCT_ITEM] : [{ id: 41, label: 'Fornitore Due' }],
      pagination: PAGINATION,
      export_link: null,
    }),
  )
})

describe('WorkOrderCostsSection — Comparison tab (AC-011)', () => {
  it('renders totals, per-line rows, unattributed line and the unallocated budget block', async () => {
    renderSection(false)

    expect(await screen.findByRole('tab', { name: 'Comparison' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Costs' })).toBeInTheDocument()
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
  it('is read-only without manage_costs: no add row, no save, disabled fields', async () => {
    await openEditor(false)

    expect(await screen.findByText('You do not have permission to edit the actual costs.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add row' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Quantity, row 1' })).toBeDisabled()
  })

  it('adds a row with today as default date', async () => {
    await openEditor(true)

    fireEvent.click(await screen.findByRole('button', { name: 'Add row' }))

    expect(screen.getByLabelText('Cost date, row 2')).toHaveValue(todayIsoDate())
  })

  it('saves the exact contract payload for an edited row and a new picked row', async () => {
    await openEditor(true)

    fireEvent.change(await screen.findByRole('spinbutton', { name: 'Quantity, row 1' }), { target: { value: '3' } })

    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    fireEvent.click(screen.getByRole('combobox', { name: 'Cost product, row 2' }))
    fireEvent.click(await screen.findByRole('option', { name: /Noleggio auto/ }))
    // Radix hands focus back to the product trigger on the next tick; opening the next popover before that dismisses it.
    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
    await new Promise((resolve) => setTimeout(resolve, 0))
    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier, row 2' }))
    fireEvent.click(await screen.findByRole('option', { name: /Fornitore Due/ }))
    fireEvent.change(screen.getByLabelText('Document reference, row 2'), { target: { value: 'FT-99' } })

    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 2' })).toHaveValue(12.5)

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
          incurred_on: todayIsoDate(),
          supplier_id: 41,
          document_reference: 'FT-99',
          additional_description: null,
        },
      ],
    })
  })

  it('does not call the server while a row is invalid and shows the field error', async () => {
    await openEditor(true)

    fireEvent.click(await screen.findByRole('button', { name: 'Add row' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findAllByText('Select a product.')).toHaveLength(1)
    expect(syncCostsMock).not.toHaveBeenCalled()
  })

  it('Cancel restores the persisted rows', async () => {
    await openEditor(true)

    fireEvent.click(await screen.findByRole('button', { name: 'Add row' }))
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
