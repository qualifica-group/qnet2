import { useState } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { ForSelectItem, PaginatedResponse } from '@/features/for-select/types'
import type { QuoteCatalogProduct } from '@/features/quotes/line-editor/types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import { RequestSimplifiedOfferPicker } from './request-simplified-offer-picker'

/**
 * Spec 0414 D-4 (AC-007): the simplified offer of Gestione Richieste is ONE
 * product select — no quantity, price or VAT input, a single indicative line.
 */

const CATEGORY_ID = 500

function product(id: number, label: string, price: string | null): QuoteCatalogProduct {
  return {
    id,
    label,
    subtitle: null,
    meta: {
      category_id: CATEGORY_ID,
      code: `SIC-${id}`,
      description: null,
      price,
      cost: null,
      vat_rate_id: 7,
      vat_rate_name: 'IVA 22%',
      vat_rate: '22.00',
      unit_of_measure: { id: 1, name: 'Pezzo', symbol: 'pz' },
      product_typology: null,
    },
  }
}

const SAFETY = product(900, 'Corso Sicurezza Base', '100.00')
const FIRST_AID = product(901, 'Corso Primo Soccorso', null)
const NO_LINES: QuoteLine[] = []

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null }
}

const fetchForSelectMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({ useAbilities: () => ({ can: () => false }) }))
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const onChangeSpy = vi.fn()

interface HarnessProps {
  initialRows?: QuoteLineFormValues[]
  singleCategoryMode?: boolean
  disabled?: boolean
  categoryIds?: number[]
}

function Harness({ initialRows = [EMPTY_LINE_ROW], singleCategoryMode = false, disabled = false, categoryIds = [CATEGORY_ID] }: HarnessProps) {
  const [rows, setRows] = useState(initialRows)
  return (
    <RequestSimplifiedOfferPicker
      value={rows}
      onChange={(next) => {
        onChangeSpy(next)
        setRows(next)
      }}
      disabled={disabled}
      categoryIds={categoryIds}
      lockedHint="Scegli prima una categoria prodotto."
      maxRows={singleCategoryMode ? 1 : 200}
      singleCategoryMode={singleCategoryMode}
      knownLines={NO_LINES}
      vatRatePercentFor={(id) => (id === 7 ? 22 : null)}
      rememberVatRatePercent={() => {}}
    />
  )
}

function renderPicker(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

function lastRows(): QuoteLineFormValues[] {
  return onChangeSpy.mock.lastCall?.[0] as QuoteLineFormValues[]
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  onChangeSpy.mockReset()
  fetchForSelectMock.mockReset()
  // Like the backend: the requested ids come back alongside the page.
  fetchForSelectMock.mockImplementation(() => Promise.resolve(page([SAFETY, FIRST_AID])))
})

const PICKED_SAFETY: QuoteLineFormValues = { ...EMPTY_LINE_ROW, product_id: 900, quantity: 1, unit_price: 100, vat_rate_id: 7 }

describe('RequestSimplifiedOfferPicker (spec 0414 D-4)', () => {
  it('AC-007: picking from the select adds the product congealed, no quantity/price/VAT input', async () => {
    renderPicker()

    fireEvent.click(screen.getByRole('button', { name: 'Prodotto' }))
    fireEvent.click(await screen.findByRole('option', { name: /Corso Primo Soccorso/ }))

    await waitFor(() => expect(lastRows()).toHaveLength(1))
    expect(lastRows()[0]).toMatchObject({ product_id: 901, quantity: 1, unit_price: 0 })
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
    expect(screen.queryByRole('listbox', { name: 'Prodotti del catalogo' })).not.toBeInTheDocument()
  })

  it('AC-007: one line gives the indicative value of the picked products', async () => {
    renderPicker({ initialRows: [PICKED_SAFETY] })

    expect(await screen.findByText('Valore indicativo (IVA inclusa)')).toBeInTheDocument()
    expect(screen.getByText('122,00 €')).toBeInTheDocument()
  })

  it('removes a picked product from its badge', async () => {
    renderPicker({ initialRows: [PICKED_SAFETY] })

    fireEvent.click(await screen.findByRole('button', { name: 'Togli Corso Sicurezza Base' }))
    expect(lastRows()).toEqual([])
  })

  it('single mode: a plain select whose pick replaces the one product', async () => {
    renderPicker({ singleCategoryMode: true, initialRows: [PICKED_SAFETY] })

    fireEvent.click(screen.getByRole('combobox', { name: 'Prodotto' }))
    fireEvent.click(await screen.findByRole('option', { name: /Corso Primo Soccorso/ }))

    await waitFor(() => expect(lastRows()?.[0]).toMatchObject({ product_id: 901, quantity: 1 }))
    expect(lastRows()).toHaveLength(1)
  })

  it('stays disabled with the hint while no category is picked', () => {
    renderPicker({ categoryIds: [] })

    expect(screen.getByRole('button', { name: 'Prodotto' })).toBeDisabled()
    expect(screen.getByText('Scegli prima una categoria prodotto.')).toBeInTheDocument()
  })
})
