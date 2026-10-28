import { useState } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { ForSelectParams, ForSelectItem, PaginatedResponse } from '@/features/for-select/types'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import { QuoteLineEditor, type QuoteLineEditorProps } from './quote-line-editor'
import type { QuoteCatalogProduct, QuoteLineCommissionsAdapter } from './types'

/**
 * Spec 0320 AC-014 (Offerte): the supplier select of a row card, the cost it
 * precompiles and the commission defaults a revenue supplier change asks for.
 */

const SUPPLIERS = [
  { supplier_id: 5, name: 'Acme', price: '170.00', cost: '70.00', is_default: true },
  { supplier_id: 6, name: 'Beta', price: '190.00', cost: '90.00', is_default: false },
]

function product(id: number, label: string, suppliers?: typeof SUPPLIERS): QuoteCatalogProduct {
  return {
    id,
    label,
    subtitle: null,
    meta: {
      category_id: 1,
      code: `P-${id}`,
      description: null,
      price: '120.00',
      cost: '40.00',
      vat_rate_id: 7,
      vat_rate_name: 'IVA 22%',
      vat_rate: '22.00',
      unit_of_measure: { id: 1, name: 'Pezzo', symbol: 'pz' },
      product_typology: null,
      ...(suppliers ? { suppliers } : {}),
    },
  }
}

const MULTI = product(900, 'Corso Multi', SUPPLIERS)
const PLAIN = product(901, 'Corso Semplice')

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null }
}

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const apiGetMock = vi.fn()
vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  apiClient: { get: (...args: unknown[]) => apiGetMock(...args) },
}))

/** `GET /products/supplier-commissions` answer: the listed suppliers earn a PAID 10% commission, the others none. */
function commissionsFor(supplierIds: number[]) {
  return {
    data: {
      data: [5, 6].map((supplierId) => ({
        supplier_id: supplierId,
        commission: supplierIds.includes(supplierId)
          ? { configuration_id: 9, type: 'PERCENTAGE', value: '10.00', direction: 'PAID' }
          : null,
      })),
    },
  }
}

const onChangeSpy = vi.fn()

type HarnessProps = Partial<Omit<QuoteLineEditorProps, 'value' | 'onChange'>> & { initialRows?: QuoteLineFormValues[] }

function Harness({ initialRows = [EMPTY_LINE_ROW], ...props }: HarnessProps) {
  const [rows, setRows] = useState(initialRows)
  return (
    <QuoteLineEditor
      value={rows}
      onChange={(next) => {
        onChangeSpy(next)
        setRows(next)
      }}
      variant="cost"
      disabled={false}
      catalogScope={{ kind: 'all' }}
      categoryTree={[]}
      maxRows={200}
      singleCategoryMode={false}
      knownLines={[]}
      vatRatePercentFor={() => 22}
      rememberVatRatePercent={() => {}}
      {...props}
    />
  )
}

function renderEditor(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

const lastRows = () => onChangeSpy.mock.lastCall?.[0] as QuoteLineFormValues[]

async function pickAndAdd(name: string) {
  fireEvent.click(await screen.findByRole('option', { name: new RegExp(name) }))
  fireEvent.click(screen.getByRole('button', { name: /^Aggiungi 1 selezionato/ }))
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  onChangeSpy.mockReset()
  fetchForSelectMock.mockReset()
  apiGetMock.mockReset()
  apiGetMock.mockResolvedValue(commissionsFor([]))
  fetchForSelectMock.mockImplementation((_resource: string, params: ForSelectParams = {}) =>
    Promise.resolve(page(params.ids?.length ? [MULTI].filter((item) => params.ids?.includes(item.id)) : [MULTI, PLAIN])),
  )
})

describe('QuoteLineEditor — fornitore della riga (cost)', () => {
  it('preselects the default supplier and prices the row from its cost, then follows a supplier change', async () => {
    renderEditor()
    await pickAndAdd('Corso Multi')

    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ product_id: 900, supplier_id: 5, unit_price: 70 }))
    const trigger = screen.getByRole('combobox', { name: 'Fornitore riga 1' })
    expect(trigger).toHaveTextContent('Acme')

    fireEvent.click(trigger)
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))

    await waitFor(() => expect(lastRows()[0]).toMatchObject({ supplier_id: 6, supplier: { id: 6, name: 'Beta' }, unit_price: 90 }))
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toHaveValue(90)
  })

  it('shows no supplier select for a product without suppliers and prices it from the standard cost', async () => {
    renderEditor()
    await pickAndAdd('Corso Semplice')

    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ product_id: 901, supplier_id: null, unit_price: 40 }))
    expect(screen.queryByRole('combobox', { name: 'Fornitore riga 1' })).not.toBeInTheDocument()
  })

  it('offers the options of a saved row by looking its product up', async () => {
    renderEditor({
      initialRows: [{ ...EMPTY_LINE_ROW, id: 3, product_id: 900, quantity: 1, unit_price: 70, vat_rate_id: 7, supplier_id: 5, supplier: { id: 5, name: 'Acme' } }],
    })

    const trigger = await screen.findByRole('combobox', { name: 'Fornitore riga 1' })
    expect(trigger).toHaveTextContent('Acme')
    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ ids: [900] })))
  })

  it('wires a server supplier error with the accessible triad', async () => {
    const errors: (QuoteLineRowErrors | undefined)[] = [{ supplier_id: { type: 'server', message: 'Il fornitore è obbligatorio.' } }]
    renderEditor({
      initialRows: [{ ...EMPTY_LINE_ROW, product_id: 900, quantity: 1, unit_price: 70, product_suppliers: SUPPLIERS, supplier_id: null }],
      errors,
    })

    const trigger = screen.getByRole('combobox', { name: 'Fornitore riga 1' })
    const message = screen.getByRole('alert')
    expect(trigger).toHaveAttribute('aria-invalid', 'true')
    expect(trigger).toHaveAttribute('aria-describedby', message.id)
  })
})

/** Spec 0320 rev.3 D-23 / AC-032: a supplier that earns a commission makes a cost row free of charge. */
describe('QuoteLineEditor — commissione del fornitore', () => {
  it('opens a cost row at price 0, locked, with the note, when the default supplier earns a commission', async () => {
    apiGetMock.mockResolvedValue(commissionsFor([5]))
    renderEditor()
    await pickAndAdd('Corso Multi')

    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ supplier_id: 5, unit_price: 0 }))
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeDisabled()
    expect(await screen.findByText('Commissione 10% pagata')).toBeInTheDocument()
    expect(apiGetMock).toHaveBeenCalledWith('/products/supplier-commissions', { params: { product_id: 900, supplier_ids: [5, 6] } })
  })

  it('zeroes the price when the supplier is changed to a commissioned one, and restores the cost on the way back', async () => {
    apiGetMock.mockResolvedValue(commissionsFor([6]))
    renderEditor()
    await pickAndAdd('Corso Multi')
    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ supplier_id: 5, unit_price: 70 }))
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeEnabled()

    fireEvent.click(screen.getByRole('combobox', { name: 'Fornitore riga 1' }))
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))
    await waitFor(() => expect(lastRows()[0]).toMatchObject({ supplier_id: 6, unit_price: 0 }))
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeDisabled()

    fireEvent.click(screen.getByRole('combobox', { name: 'Fornitore riga 1' }))
    fireEvent.click(screen.getByRole('option', { name: /Acme/ }))
    await waitFor(() => expect(lastRows()[0]).toMatchObject({ supplier_id: 5, unit_price: 70 }))
  })

  it('asks once per product, however many rows show it', async () => {
    apiGetMock.mockResolvedValue(commissionsFor([5]))
    const row = { ...EMPTY_LINE_ROW, product_id: 900, quantity: 1, unit_price: 0, product_suppliers: SUPPLIERS, supplier_id: 5 }
    renderEditor({ initialRows: [row, { ...row }] })

    expect(await screen.findAllByText('Commissione 10% pagata')).toHaveLength(2)
    expect(apiGetMock).toHaveBeenCalledTimes(1)
  })

  it('only notes the commission on a revenue row and leaves the price editable', async () => {
    apiGetMock.mockResolvedValue(commissionsFor([5]))
    const row = { ...EMPTY_LINE_ROW, product_id: 900, quantity: 1, unit_price: 170, product_suppliers: SUPPLIERS, supplier_id: 5 }
    renderEditor({ variant: 'revenue', initialRows: [row] })

    expect(await screen.findByText('Commissione 10% pagata')).toBeInTheDocument()
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeEnabled()
  })

  it('makes no request for a product without suppliers', async () => {
    renderEditor()
    await pickAndAdd('Corso Semplice')
    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ product_id: 901, unit_price: 40 }))

    expect(apiGetMock).not.toHaveBeenCalled()
  })
})

describe('QuoteLineEditor — fornitore della riga (revenue)', () => {
  const COMMISSIONS = [{
    recipient_role: 'SUPPLIER' as const,
    recipient_type: 'registry' as const,
    recipient_id: 6,
    commission_type: 'PERCENTAGE' as const,
    value: 3,
    internal_note: null,
    origin: 'PRODUCT' as const,
    commission_configuration_id: 1,
  }]

  it('reprices from the new supplier price and asks its commission defaults (rev.2 D-17)', async () => {
    const defaultsForSupplier = vi.fn().mockResolvedValue({ commissions: COMMISSIONS, supplierCommissionDirection: 'PAID' })
    const commissions: QuoteLineCommissionsAdapter = {
      defaultsForNewRows: vi.fn().mockResolvedValue(new Map([[900, { commissions: [], supplierCommissionDirection: null }]])),
      defaultsForReplacement: vi.fn(),
      defaultsForSupplier,
    }
    renderEditor({ variant: 'revenue', commissions })
    await pickAndAdd('Corso Multi')
    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ supplier_id: 5, unit_price: 170 }))

    fireEvent.click(await screen.findByRole('combobox', { name: 'Fornitore riga 1' }))
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))

    await waitFor(() => expect(lastRows()[0]).toMatchObject({ supplier_id: 6, unit_price: 190, commissions: COMMISSIONS, supplier_commission_direction: 'PAID' }))
    expect(defaultsForSupplier).toHaveBeenCalledWith(expect.objectContaining({ product_id: 900, supplier_id: 5 }), 6)
  })

  it('leaves the row untouched when the commission regeneration is declined', async () => {
    const commissions: QuoteLineCommissionsAdapter = {
      defaultsForNewRows: vi.fn().mockResolvedValue(new Map([[900, { commissions: [], supplierCommissionDirection: null }]])),
      defaultsForReplacement: vi.fn(),
      defaultsForSupplier: vi.fn().mockResolvedValue(null),
    }
    renderEditor({ variant: 'revenue', commissions })
    await pickAndAdd('Corso Multi')
    const callsBefore = onChangeSpy.mock.calls.length

    fireEvent.click(await screen.findByRole('combobox', { name: 'Fornitore riga 1' }))
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))

    await waitFor(() => expect(commissions.defaultsForSupplier).toHaveBeenCalled())
    expect(onChangeSpy.mock.calls).toHaveLength(callsBefore)
  })
})
