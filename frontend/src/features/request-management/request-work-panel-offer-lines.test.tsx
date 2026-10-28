import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import {
  OFFER_LINE_FIBRA,
  workPanel as panel,
} from '@/features/request-management/request-work-panel-fixtures'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ForSelectParams } from '@/features/for-select/types'

/**
 * "Linee dell'offerta" nel pannello di lavorazione (direttiva utente
 * 2026-08-07): le stesse righe delle Offerte, senza il blocco provvigioni —
 * che questo canale non possiede — composte dal catalogo (spec 0268).
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const categoryTreeMock = vi.fn<() => ProductCategoryTreeNode[]>()
vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: categoryTreeMock(), isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log</div>,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** A catalogue product of the panel's own category (500), in the for-select shape (spec 0268). */
const CATALOG_PRODUCT = {
  id: 901,
  label: 'Fibra 2000',
  subtitle: null,
  meta: {
    category_id: 500,
    code: 'FIB-2000',
    description: null,
    price: '49.90',
    cost: null,
    vat_rate_id: 7,
    vat_rate_name: 'IVA 22%',
    vat_rate: '22.00',
    unit_of_measure: null,
    product_typology: null,
  },
}

let catalogProduct: typeof CATALOG_PRODUCT & { meta: Record<string, unknown> } = CATALOG_PRODUCT

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  categoryTreeMock.mockReset()
  categoryTreeMock.mockReturnValue([])
  catalogProduct = CATALOG_PRODUCT
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: ForSelectParams = {}) => {
    const items = resource === 'products' && !params.ids?.length ? [catalogProduct] : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
})

describe('work panel — Linee dell\'offerta', () => {
  it('hydrates one editable row per persisted offer line', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(await screen.findByLabelText('Quantità riga 1')).toHaveValue(1)
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toHaveValue(100)
  })

  it('never offers the provvigioni control: this channel does not own that block', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await screen.findByLabelText('Quantità riga 1')
    expect(screen.queryByRole('button', { name: /Provvigioni/i })).not.toBeInTheDocument()
  })

  it('sends the whole collection on save, only when a row actually changed', async () => {
    const loaded = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '3' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).toEqual({
      // Spec 0320 D-5 (REQUIREMENT CHANGED): every row now travels with its supplier, null without suppliers.
      offer_lines: [{
        id: OFFER_LINE_FIBRA.id,
        product_id: OFFER_LINE_FIBRA.product_id,
        supplier_id: null,
        quantity: 3,
        unit_price: 100,
        vat_rate_id: null,
        sort_order: 0,
      }],
    })
  })

  // Spec 0268 AC-014: a catalogue pick reaches the panel's own submit, in the shape it always sent.
  it('sends a product added from the catalogue next to the persisted row', async () => {
    const loaded = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.click(await screen.findByRole('option', { name: /Fibra 2000/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).toEqual({
      offer_lines: [
        { id: OFFER_LINE_FIBRA.id, product_id: OFFER_LINE_FIBRA.product_id, supplier_id: null, quantity: 1, unit_price: 100, vat_rate_id: null, sort_order: 0 },
        // A new row carries the empty block every new row always carried
        // (`createEmptyLineRow`, the autofill seed): `prohibited` accepts it.
        { product_id: 901, supplier_id: null, quantity: 1, unit_price: 49.9, vat_rate_id: 7, sort_order: 1, commissions: [] },
      ],
    })
  })

  // Spec 0320 AC-014: the default supplier is preselected and the chosen one travels on the panel's submit.
  it('shows the supplier select for a product with suppliers and sends the chosen supplier', async () => {
    const loaded = panel({ offer_lines: [] })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)
    catalogProduct = {
      ...CATALOG_PRODUCT,
      id: 902,
      label: 'Corso Multi',
      meta: {
        ...CATALOG_PRODUCT.meta,
        suppliers: [
          { supplier_id: 5, name: 'Acme', price: '170.00', cost: '70.00', is_default: true },
          { supplier_id: 6, name: 'Beta', price: '190.00', cost: '90.00', is_default: false },
        ],
      },
    }

    renderPanel()

    fireEvent.click(await screen.findByRole('option', { name: /Corso Multi/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))
    const trigger = await screen.findByRole('combobox', { name: 'Fornitore riga 1' })
    expect(trigger).toHaveTextContent('Acme')
    fireEvent.click(trigger)
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).toMatchObject({
      offer_lines: [expect.objectContaining({ product_id: 902, supplier_id: 6 })],
    })
  })

  it('leaves the collection out of the payload when nothing in it was touched', async () => {
    const loaded = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.change(await screen.findByLabelText(/Data del richiamo/i), {
      target: { value: '2026-09-01' },
    })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).not.toHaveProperty('offer_lines')
  })

  it('opens on one empty row when the request carries no line yet, and still saves without it', async () => {
    const loaded = panel({ offer_lines: [] })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    // Direttiva utente 2026-09-09: la riga vuota c'e' gia' nel form. Restando
    // intatta non viaggia (`toLineInputs` la scarta). REQUIREMENT CHANGED
    // (spec 0268 D-7): l'editor non la disegna, mostra lo stato vuoto e il
    // catalogo da cui riempirla.
    expect(await screen.findByText('Nessuna riga: scegli i prodotti dal catalogo.')).toBeInTheDocument()
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/Data del richiamo/i), {
      target: { value: '2026-09-01' },
    })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).not.toHaveProperty('offer_lines')
  })

  it('blocks the submit on an incomplete row, with the message on that row', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    expect(await screen.findByText('La quantità è obbligatoria.')).toBeInTheDocument()
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })
})

// Spec 0114 AC-017/AC-018: a simplified classification drops the three
// controls from the row, keeping the product picker and the read-only amounts.
describe('work panel — Linee dell\'offerta (categoria semplificata)', () => {
  it('hides quantity/unit price/VAT controls but keeps the product picker and the amounts', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    categoryTreeMock.mockReturnValue([
      {
        id: 500,
        name: 'Consulting',
        parent_id: null,
        children: [],
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
        simplified_offer_line: true,
        simplified_offer_line_override: null,
      },
    ])

    renderPanel()

    await screen.findByText(
      'Categoria semplificata: scegli il prodotto, quantità, prezzo unitario e aliquota IVA sono compilati automaticamente dal sistema.',
    )
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
    // REQUIREMENT CHANGED (spec 0268): the row reads its product, the
    // catalogue beside it replaces the per-row picker.
    expect(screen.getByText('Fibra 1000')).toBeInTheDocument()
    expect(screen.getByRole('listbox', { name: 'Prodotti del catalogo' })).toBeInTheDocument()
  })

  it('a non-simplified classification keeps the three controls (no regression)', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    categoryTreeMock.mockReturnValue([
      {
        id: 500,
        name: 'Consulting',
        parent_id: null,
        children: [],
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
      },
    ])

    renderPanel()

    expect(await screen.findByLabelText('Quantità riga 1')).toBeInTheDocument()
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeInTheDocument()
  })

  // Spec 0188 AC-015: the classified node overrides a simplified root to false;
  // the denormalized effective flag is all the panel reads, no tree walk.
  it('a node overriding a simplified root to false shows quantity, unit price and VAT controls', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    const baseNode = {
      attributes_count: 0,
      products_count: 0,
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      is_active: true,
      is_reportable: false,
      management_mode: 'multiple' as const,
      single_quote_per_opportunity: false,
      generates_contract: true,
      multi_quote_work_order: false,
    }
    categoryTreeMock.mockReturnValue([
      {
        ...baseNode,
        id: 400,
        name: 'Formazione',
        parent_id: null,
        simplified_offer_line: true,
        simplified_offer_line_override: null,
        children: [
          {
            ...baseNode,
            id: 500,
            name: 'Corsi E-Campus',
            parent_id: 400,
            simplified_offer_line: false,
            simplified_offer_line_override: false,
            children: [],
          },
        ],
      },
    ])

    renderPanel()

    expect(await screen.findByLabelText('Quantità riga 1')).toBeInTheDocument()
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeInTheDocument()
    expect(screen.getByLabelText('Aliquota IVA riga 1')).toBeInTheDocument()
  })
})
