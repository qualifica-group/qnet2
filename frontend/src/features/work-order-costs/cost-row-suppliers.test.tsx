import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrderCostCard } from '@/features/work-order-costs/work-order-cost-card'
import { EMPTY_ROW_DISPLAY, type WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'
import type { ProductSupplierOption } from '@/features/products/product-suppliers'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const apiGetMock = vi.fn()
vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  apiClient: { get: (...args: unknown[]) => apiGetMock(...args) },
}))

const COMMISSION = { configuration_id: 9, type: 'PERCENTAGE', value: '10.00', direction: 'PAID' }

/** `GET /products/supplier-commissions`: only the given supplier earns a commission. */
function commissionedSupplier(supplierId: number | null) {
  return {
    data: {
      data: [40, 41].map((id) => ({ supplier_id: id, commission: id === supplierId ? COMMISSION : null })),
    },
  }
}

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

/** Spec 0320 D-9 / AC-015: the supplier of a cost row follows the product's suppliers. */

const SUPPLIERS: ProductSupplierOption[] = [
  { supplier_id: 40, name: 'Alfa Srl', price: '108.00', cost: '8.00', is_default: true },
  { supplier_id: 41, name: 'Beta Srl', price: '109.50', cost: '9.50', is_default: false },
]

function rowWith(overrides: Partial<WorkOrderCostRowValues> = {}): WorkOrderCostRowValues {
  return {
    id: 5,
    product_id: 7,
    quantity: 1,
    unit_price: 8,
    vat_rate_id: null,
    quote_line_id: null,
    cost_type_id: null,
    incurred_on: '2026-09-15',
    supplier_id: 40,
    document_reference: null,
    additional_description: null,
    display: { ...EMPTY_ROW_DISPLAY, product_name: 'Noleggio auto', supplier_name: 'Alfa Srl' },
    ...overrides,
  }
}

const onChangeField = vi.fn()

function renderCard(row: WorkOrderCostRowValues, error?: Parameters<typeof WorkOrderCostCard>[0]['error']) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ul>
        <WorkOrderCostCard index={0} row={row} disabled={false} revenueOptions={[]} error={error} onChangeField={onChangeField} onRemove={vi.fn()} />
      </ul>
    </QueryClientProvider>,
  )
}

function forSelectReturns(suppliers: ProductSupplierOption[]) {
  fetchForSelectMock.mockImplementation((resource: string) => {
    const items = resource === 'products' ? [{ id: 7, label: 'Noleggio auto', meta: { code: 'CST-0009', cost: '12.50', suppliers } }] : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  onChangeField.mockReset()
  apiGetMock.mockReset()
  apiGetMock.mockResolvedValue(commissionedSupplier(null))
  forSelectReturns([])
})

describe('WorkOrderCostCard supplier (spec 0320)', () => {
  it('offers only the product suppliers and reprices on a change', async () => {
    renderCard(rowWith({ display: { ...rowWith().display, product_suppliers: SUPPLIERS } }))

    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier, row 1' }))
    expect(screen.getAllByRole('option')).toHaveLength(2)
    fireEvent.click(await screen.findByRole('option', { name: /Beta Srl/ }))

    expect(onChangeField).toHaveBeenLastCalledWith({
      supplier_id: 41,
      unit_price: 9.5,
      display: expect.objectContaining({ supplier_name: 'Beta Srl' }),
    })
  })

  // Spec 0320 rev.3 D-23 / AC-032.
  it('locks the unit price and notes the commission when the row supplier earns one', async () => {
    apiGetMock.mockResolvedValue(commissionedSupplier(40))
    renderCard(rowWith({ unit_price: 0, display: { ...rowWith().display, product_suppliers: SUPPLIERS } }))

    expect(await screen.findByText('Commission 10% paid')).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 1' })).toBeDisabled()
    expect(apiGetMock).toHaveBeenCalledWith('/products/supplier-commissions', { params: { product_id: 7, supplier_ids: [40, 41] } })
    expect(apiGetMock).toHaveBeenCalledTimes(1)
  })

  it('zeroes the unit price when the supplier is changed to a commissioned one', async () => {
    apiGetMock.mockResolvedValue(commissionedSupplier(41))
    renderCard(rowWith({ display: { ...rowWith().display, product_suppliers: SUPPLIERS } }))
    await waitFor(() => expect(apiGetMock).toHaveBeenCalled())
    await act(async () => {})

    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier, row 1' }))
    fireEvent.click(await screen.findByRole('option', { name: /Beta Srl/ }))

    expect(onChangeField).toHaveBeenLastCalledWith(expect.objectContaining({ supplier_id: 41, unit_price: 0 }))
  })

  it('keeps the unit price editable and shows no note when the supplier has no commission', async () => {
    renderCard(rowWith({ display: { ...rowWith().display, product_suppliers: SUPPLIERS } }))
    await waitFor(() => expect(apiGetMock).toHaveBeenCalled())
    await act(async () => {})

    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 1' })).toBeEnabled()
    expect(screen.queryByText(/Commission/)).not.toBeInTheDocument()
  })

  it('keeps the free supplier picker for a product without suppliers', async () => {
    renderCard(rowWith({ supplier_id: null, display: { ...rowWith().display, supplier_name: null, product_suppliers: [] } }))

    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier, row 1' }))
    await screen.findByText('No supplier found.')
    expect(fetchForSelectMock).toHaveBeenCalledWith('registries', expect.objectContaining({ params: { is_supplier: 1 } }))
  })

  it('looks up the suppliers of a saved row and shows a no-longer-listed supplier as fallback', async () => {
    forSelectReturns(SUPPLIERS)
    renderCard(rowWith({ supplier_id: 99, display: { ...rowWith().display, supplier_name: 'Vecchio Fornitore' } }))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ ids: [7] })))
    await act(async () => {})
    const trigger = screen.getByRole('combobox', { name: 'Supplier, row 1' })
    expect(trigger).toHaveTextContent('Vecchio Fornitore')
    fireEvent.click(trigger)
    expect(await screen.findByRole('option', { name: /Alfa Srl/ })).toBeInTheDocument()
  })

  it('wires a supplier error on the select with the accessible triad', () => {
    renderCard(rowWith({ supplier_id: null, display: { ...rowWith().display, product_suppliers: SUPPLIERS } }), {
      supplier_id: { type: 'server', message: 'Pick a product supplier.' },
    })

    const select = screen.getByRole('combobox', { name: 'Supplier, row 1' })
    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('Pick a product supplier.')
    expect(select).toHaveAttribute('aria-invalid', 'true')
    expect(select).toHaveAttribute('aria-describedby', alert.id)
  })
})
