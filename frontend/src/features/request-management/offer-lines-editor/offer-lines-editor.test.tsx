import { useState } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { ForSelectParams, ForSelectItem, PaginatedResponse } from '@/features/for-select/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import { OfferLinesEditor, type OfferLinesEditorProps } from './offer-lines-editor'
import type { OfferCatalogProduct } from './types'

/**
 * Spec 0268: the "catalogo + righe" editor of Gestione Richieste / Iscritti,
 * exercised on its own with the for-select endpoint mocked at the API seam.
 */

const ROOT_ID = 10
const CATEGORY_A = 500
const CATEGORY_B = 501

function treeNode(id: number, name: string, children: ProductCategoryTreeNode[] = [], parentId: number | null = null): ProductCategoryTreeNode {
  return {
    id,
    name,
    parent_id: parentId,
    children,
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    multi_quote_work_order: false,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
  }
}

const TREE: ProductCategoryTreeNode[] = [
  treeNode(ROOT_ID, 'Formazione', [treeNode(CATEGORY_A, 'Sicurezza', [], ROOT_ID), treeNode(CATEGORY_B, 'Primo soccorso', [], ROOT_ID)]),
]

function product(id: number, label: string, overrides: Partial<OfferCatalogProduct['meta']> = {}): OfferCatalogProduct {
  return {
    id,
    label,
    subtitle: null,
    meta: {
      category_id: CATEGORY_A,
      code: `SIC-${id}`,
      description: 'Corso base per lavoratori',
      price: '120.00',
      cost: '40.00',
      vat_rate_id: 7,
      vat_rate_name: 'IVA 22%',
      vat_rate: '22.00',
      unit_of_measure: { id: 1, name: 'Pezzo', symbol: 'pz' },
      product_typology: { id: 3, name: 'Corso', color: 'blue' },
      ...overrides,
    },
  }
}

const SAFETY = product(900, 'Corso Sicurezza Base')
const FIRST_AID = product(901, 'Corso Primo Soccorso', { category_id: CATEGORY_B, price: null })

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null }
}

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const onChangeSpy = vi.fn()

type HarnessProps = Partial<Omit<OfferLinesEditorProps, 'value' | 'onChange'>> & { initialRows?: QuoteLineFormValues[] }

function Harness({ initialRows = [EMPTY_LINE_ROW], ...props }: HarnessProps) {
  const [rows, setRows] = useState(initialRows)
  return (
    <OfferLinesEditor
      value={rows}
      onChange={(next) => {
        onChangeSpy(next)
        setRows(next)
      }}
      disabled={false}
      categoryIds={[CATEGORY_A, CATEGORY_B]}
      categoryTree={TREE}
      maxRows={200}
      singleCategoryMode={false}
      simplified={false}
      knownLines={[]}
      knownVatRates={[]}
      vatRatePercentFor={(id) => (id === 7 ? 22 : null)}
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

/** The last for-select request on the products resource (the catalogue list, not a label lookup). */
function lastCatalogRequest(): ForSelectParams {
  const calls = fetchForSelectMock.mock.calls.filter(([resource, params]) => resource === 'products' && !(params as ForSelectParams).ids?.length)
  return calls[calls.length - 1]?.[1] as ForSelectParams
}

const option = (name: string) => screen.getByRole('option', { name: new RegExp(name) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  onChangeSpy.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: ForSelectParams = {}) => {
    if (resource !== 'products') {
      return Promise.resolve(page([]))
    }
    const categoryIds = (params.params?.category_ids ?? []) as number[]
    const search = params.search ?? ''
    return Promise.resolve(
      page([SAFETY, FIRST_AID].filter((item) => categoryIds.includes(item.meta.category_id as number) && item.label.includes(search))),
    )
  })
})

describe('OfferLinesEditor — catalogo', () => {
  it('AC-002: scopes the list to the classification and narrows it with a category chip', async () => {
    renderEditor()

    await screen.findByText('Corso Sicurezza Base')
    expect(lastCatalogRequest().params).toEqual({ usage: 'SALE', category_ids: [CATEGORY_A, CATEGORY_B] })
    const chips = screen.getByRole('group', { name: 'Filtra per categoria' })
    expect(within(chips).getAllByRole('button').map((chip) => chip.textContent)).toEqual(['Tutte', 'Sicurezza', 'Primo soccorso'])

    fireEvent.click(within(chips).getByRole('button', { name: 'Sicurezza' }))

    await waitFor(() => expect(lastCatalogRequest().params).toEqual({ usage: 'SALE', category_ids: [CATEGORY_A] }))
    await waitFor(() => expect(screen.queryByText('Corso Primo Soccorso')).not.toBeInTheDocument())
  })

  it('AC-003: shows name, code, typology, price per unit, category path and description', async () => {
    renderEditor()

    const item = within(await screen.findByRole('option', { name: /Corso Sicurezza Base/ }))
    expect(item.getByText('SIC-900')).toBeInTheDocument()
    expect(item.getByText('Corso')).toBeInTheDocument()
    expect(item.getByText(/120,00/)).toBeInTheDocument()
    expect(item.getByText('/ pz', { exact: false })).toBeInTheDocument()
    expect(item.getByText('Formazione > Sicurezza')).toBeInTheDocument()
    expect(item.getByText('Corso base per lavoratori')).toBeInTheDocument()
    expect(within(option('Corso Primo Soccorso')).getByText('Prezzo non definito')).toBeInTheDocument()
  })

  it('AC-004: searches server-side, then shows the empty and the error state', async () => {
    renderEditor()
    await screen.findByText('Corso Sicurezza Base')

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca per nome o descrizione…' }), { target: { value: 'Nulla' } })

    await waitFor(() => expect(lastCatalogRequest().search).toBe('Nulla'))
    expect(await screen.findByText('Nessun prodotto trovato.')).toBeInTheDocument()

    fetchForSelectMock.mockRejectedValue(new Error('down'))
    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca per nome o descrizione…' }), { target: { value: 'Altro' } })
    expect(await screen.findByText('Impossibile caricare i prodotti.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Riprova' })).toBeInTheDocument()
  })

  it('AC-011: without any category the catalogue is locked and fetches nothing', () => {
    renderEditor({ categoryIds: [] })

    expect(screen.getByText('Scegli prima una categoria prodotto: limita i prodotti selezionabili.')).toBeInTheDocument()
    expect(screen.getByRole('searchbox')).toBeDisabled()
    expect(fetchForSelectMock).not.toHaveBeenCalledWith('products', expect.objectContaining({ params: expect.anything() }))
  })
})

describe('OfferLinesEditor — scelta e righe', () => {
  it('AC-005: adds every selected product as a precompiled row, filling the pristine one first', async () => {
    renderEditor()
    await screen.findByText('Corso Sicurezza Base')

    fireEvent.click(option('Corso Sicurezza Base'))
    fireEvent.click(option('Corso Primo Soccorso'))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 2 selezionati' }))

    const rows = onChangeSpy.mock.lastCall?.[0] as QuoteLineFormValues[]
    expect(rows).toHaveLength(2)
    expect(rows[0]).toMatchObject({ product_id: 900, quantity: 1, unit_price: 120, vat_rate_id: 7, unit_of_measure: SAFETY.meta.unit_of_measure, commissions: [] })
    expect(rows[1]).toMatchObject({ product_id: 901, quantity: 1, unit_price: null })
    expect(screen.getByRole('button', { name: 'Seleziona i prodotti da aggiungere' })).toBeDisabled()
  })

  it('AC-006: a product already on a row is marked and cannot be picked', async () => {
    renderEditor({ initialRows: [{ ...EMPTY_LINE_ROW, product_id: 900, quantity: 2, unit_price: 120, vat_rate_id: 7 }] })

    await screen.findByRole('option', { name: /Corso Sicurezza Base/ })
    const added = option('Corso Sicurezza Base')
    expect(added).toHaveAttribute('aria-disabled', 'true')
    expect(within(added).getByText('Già aggiunto')).toBeInTheDocument()

    fireEvent.click(added)
    expect(screen.getByRole('button', { name: 'Seleziona i prodotti da aggiungere' })).toBeDisabled()
  })

  it('AC-007: a single-managed card picks with one click and replaces its one row', async () => {
    renderEditor({
      singleCategoryMode: true,
      maxRows: 1,
      initialRows: [{ ...EMPTY_LINE_ROW, id: 70, product_id: 900, quantity: 2, unit_price: 120, vat_rate_id: 7 }],
    })
    await screen.findByText('Corso Primo Soccorso')

    expect(screen.queryByRole('button', { name: /^Aggiungi/ })).not.toBeInTheDocument()
    fireEvent.click(option('Corso Primo Soccorso'))

    const rows = onChangeSpy.mock.lastCall?.[0] as QuoteLineFormValues[]
    expect(rows).toHaveLength(1)
    expect(rows[0]).toMatchObject({ id: 70, product_id: 901, quantity: 2 })
  })

  it('AC-008: a simplified card congeals a priceless pick and shows amounts only', async () => {
    renderEditor({ simplified: true })
    await screen.findByText('Corso Primo Soccorso')

    fireEvent.click(option('Corso Primo Soccorso'))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))

    expect(onChangeSpy.mock.lastCall?.[0][0]).toMatchObject({ product_id: 901, quantity: 1, unit_price: 0 })
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
  })

  it('AC-009: live amounts and offer total follow the row, and the row can be removed', async () => {
    renderEditor({ initialRows: [{ ...EMPTY_LINE_ROW, product_id: 900, quantity: 1, unit_price: 100, vat_rate_id: 7 }] })
    const rows = screen.getByRole('region', { name: "Righe dell'offerta" })

    expect(within(rows).getAllByText('122,00')).toHaveLength(2)
    fireEvent.change(screen.getByLabelText('Quantità riga 1'), { target: { value: '2' } })
    expect(within(rows).getAllByText('244,00')).toHaveLength(2)

    fireEvent.click(screen.getByRole('button', { name: 'Rimuovi riga 1' }))
    expect(onChangeSpy.mock.lastCall?.[0]).toEqual([])
    expect(screen.getByText('Nessuna riga: scegli i prodotti dal catalogo.')).toBeInTheDocument()
  })

  it('AC-010: wires a row error to its field with the accessible triad', () => {
    const errors: (QuoteLineRowErrors | undefined)[] = [{ quantity: { type: 'server', message: 'La quantità è obbligatoria.' } }]
    renderEditor({ initialRows: [{ ...EMPTY_LINE_ROW, product_id: 900, quantity: null, unit_price: 100 }], errors })

    const input = screen.getByLabelText('Quantità riga 1')
    const message = screen.getByRole('alert')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', message.id)
    expect(message).toHaveTextContent('La quantità è obbligatoria.')
  })

  it('AC-016: options toggle from the keyboard', async () => {
    renderEditor()
    await screen.findByText('Corso Sicurezza Base')

    const item = option('Corso Sicurezza Base')
    item.focus()
    fireEvent.keyDown(item, { key: ' ' })
    expect(item).toHaveAttribute('aria-selected', 'true')
    fireEvent.keyDown(item, { key: 'Enter' })
    expect(item).toHaveAttribute('aria-selected', 'false')
  })
})
