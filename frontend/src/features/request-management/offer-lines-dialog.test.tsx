import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OfferLinesCellEditor } from '@/features/request-management/offer-lines-cell-editor'
import { OfferLinesDialogProvider } from '@/features/request-management/offer-lines-dialog'
import {
  ENROLLEE_MODULE,
  REQUEST_MODULE,
  RequestModuleProvider,
  type RequestModuleConfig,
} from '@/features/request-management/request-module'
import type { ForSelectParams } from '@/features/for-select/types'
import {
  FULL_PERMISSIONS,
  OFFER_LINE_FIBRA,
  workPanel as panel,
} from '@/features/request-management/request-work-panel-fixtures'
import type { TableRow } from '@/features/table/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * "Linee di prodotto" in griglia (direttiva utente 2026-09-07: "l'edit della
 * cella deve essere lo stesso flusso delle altre colonne"): la cella e' una
 * cella inline-editabile normale — l'editor delega al dialog e si chiude, e il
 * commit passa dallo stesso `PATCH /tables/{domain}/rows/{row}` di ogni altra
 * cella, sostituendo la riga con quella ri-mappata dal server.
 */

const fetchRequestWorkPanelMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
}))

const updateTableCellMock = vi.fn()
vi.mock('@/features/table/api', () => ({
  updateTableCell: (...args: unknown[]) => updateTableCellMock(...args),
}))

const categoryTreeMock = vi.fn<() => ProductCategoryTreeNode[]>()
vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: categoryTreeMock(), isPending: false, isError: false, refetch: vi.fn() }),
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

/** A catalogue product with two suppliers (spec 0320): `meta.suppliers` default first. */
const MULTI_SUPPLIER_PRODUCT = {
  ...CATALOG_PRODUCT,
  id: 902,
  label: 'Corso Multi',
  meta: {
    ...CATALOG_PRODUCT.meta,
    price: '80.00',
    suppliers: [
      { supplier_id: 5, name: 'Acme', price: '170.00', cost: '70.00', is_default: true },
      { supplier_id: 6, name: 'Beta', price: '190.00', cost: '90.00', is_default: false },
    ],
  },
}

let catalogItems: unknown[] = [CATALOG_PRODUCT]

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const setData = vi.fn()
const stopEditing = vi.fn()

/** The cell editor as AG Grid mounts it, inside the provider that owns the dialog. */
function renderEditor(module: RequestModuleConfig = REQUEST_MODULE) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const props = {
    data: { id: 4001 } as unknown as TableRow,
    node: { setData },
    stopEditing,
  } as unknown as CustomCellEditorProps<TableRow>

  return render(
    <QueryClientProvider client={client}>
      <RequestModuleProvider module={module}>
        <OfferLinesDialogProvider>
          <OfferLinesCellEditor {...props} />
        </OfferLinesDialogProvider>
      </RequestModuleProvider>
    </QueryClientProvider>,
  )
}

/** The row shape the cell endpoint answers with — what replaces the grid row. */
const REMAPPED_ROW = { id: 4001, offer_lines: [{ id: 900, name: 'Fibra 1000' }] } as unknown as TableRow

const EXPECTED_ROWS = [{
  id: OFFER_LINE_FIBRA.id,
  product_id: OFFER_LINE_FIBRA.product_id,
  // Spec 0320 D-5 (REQUIREMENT CHANGED): every row now travels with its supplier, null without suppliers.
  supplier_id: null,
  quantity: 3,
  unit_price: 100,
  vat_rate_id: null,
  sort_order: 0,
}]

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateTableCellMock.mockReset()
  setData.mockReset()
  stopEditing.mockReset()
  categoryTreeMock.mockReset()
  categoryTreeMock.mockReturnValue([])
  catalogItems = [CATALOG_PRODUCT]
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: ForSelectParams = {}) => {
    const items = resource === 'products' && !params.ids?.length ? catalogItems : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
})

describe('Gestione Richieste — linee di prodotto in griglia', () => {
  it('hands the row to the dialog and closes the cell editor immediately', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderEditor()

    // Suppressed post-edit navigation: the focus goes into the dialog, not
    // onto the next cell.
    expect(stopEditing).toHaveBeenCalledWith(true)
    expect(await screen.findByLabelText('Quantità riga 1')).toHaveValue(1)
    expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith('/request-management', 4001)
  })

  it('never offers the provvigioni control: this channel does not own that block', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderEditor()

    await screen.findByLabelText('Quantità riga 1')
    expect(screen.queryByRole('button', { name: /Provvigioni/i })).not.toBeInTheDocument()
  })

  it('commits through the generic cell endpoint and replaces the grid row with the server copy', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateTableCellMock.mockResolvedValue(REMAPPED_ROW)

    renderEditor()

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(updateTableCellMock).toHaveBeenCalled())
    expect(updateTableCellMock.mock.calls[0][0]).toBe('request-management')
    expect(updateTableCellMock.mock.calls[0][1]).toBe(4001)
    expect(updateTableCellMock.mock.calls[0][2]).toEqual({
      column: 'offer_lines',
      value: EXPECTED_ROWS,
    })
    await waitFor(() => expect(setData).toHaveBeenCalledWith(REMAPPED_ROW))
  })

  // Spec 0268 AC-013: a catalogue pick travels on the very same cell PATCH,
  // in both modules sharing the component.
  it.each([
    ['Gestione Richieste', REQUEST_MODULE],
    ['Gestione Iscritti', ENROLLEE_MODULE],
  ])('%s: adds a product from the catalogue and commits it with the existing row', async (_label, module) => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateTableCellMock.mockResolvedValue(REMAPPED_ROW)

    renderEditor(module)

    fireEvent.click(await screen.findByRole('option', { name: /Fibra 2000/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(updateTableCellMock).toHaveBeenCalledTimes(1))
    expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith(module.apiBasePath, 4001)
    expect(updateTableCellMock.mock.calls[0][0]).toBe(module.key)
    expect(updateTableCellMock.mock.calls[0][2]).toEqual({
      column: 'offer_lines',
      value: [
        { ...EXPECTED_ROWS[0], quantity: 1 },
        { product_id: 901, supplier_id: null, quantity: 1, unit_price: 49.9, vat_rate_id: 7, sort_order: 1 },
      ],
    })
    await waitFor(() => expect(setData).toHaveBeenCalledWith(REMAPPED_ROW))
  })

  // Spec 0320 AC-014: a product with suppliers brings the select, default preselected, and the pick travels.
  it.each([
    ['Gestione Richieste', REQUEST_MODULE],
    ['Gestione Iscritti', ENROLLEE_MODULE],
  ])('%s: a product with suppliers shows the supplier select and commits the chosen supplier', async (_label, module) => {
    catalogItems = [MULTI_SUPPLIER_PRODUCT]
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ offer_lines: [] }))
    updateTableCellMock.mockResolvedValue(REMAPPED_ROW)

    renderEditor(module)

    fireEvent.click(await screen.findByRole('option', { name: /Corso Multi/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))

    const trigger = await screen.findByRole('combobox', { name: 'Fornitore riga 1' })
    expect(trigger).toHaveTextContent('Acme')
    fireEvent.click(trigger)
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(updateTableCellMock).toHaveBeenCalledTimes(1))
    expect(updateTableCellMock.mock.calls[0][2]).toEqual({
      column: 'offer_lines',
      value: [{ product_id: 902, supplier_id: 6, quantity: 1, unit_price: 190, vat_rate_id: 7, sort_order: 0 }],
    })
  })

  it('shows no supplier select for a product without suppliers', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ offer_lines: [] }))

    renderEditor()

    fireEvent.click(await screen.findByRole('option', { name: /Fibra 2000/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))

    await screen.findByLabelText('Quantità riga 1')
    expect(screen.queryByRole('combobox', { name: /Fornitore riga/ })).not.toBeInTheDocument()
  })

  it('maps a cell 422 on value.N.supplier_id onto the row with the accessible triad', async () => {
    catalogItems = [MULTI_SUPPLIER_PRODUCT]
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ offer_lines: [] }))
    updateTableCellMock.mockRejectedValue(
      Object.assign(new Error('422'), {
        isAxiosError: true,
        response: { status: 422, data: { message: 'Invalid', errors: { 'value.0.supplier_id': ['Il fornitore non è del prodotto.'] } } },
      }),
    )

    renderEditor()

    fireEvent.click(await screen.findByRole('option', { name: /Corso Multi/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Aggiungi 1 selezionato' }))
    await screen.findByRole('combobox', { name: 'Fornitore riga 1' })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    const alert = await screen.findByText('Il fornitore non è del prodotto.')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(screen.getByRole('combobox', { name: 'Fornitore riga 1' })).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('combobox', { name: 'Fornitore riga 1' })).toHaveAttribute('aria-describedby', alert.id)
    expect(setData).not.toHaveBeenCalled()
  })

  it('closes without a request when nothing was touched', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderEditor()

    fireEvent.click(await screen.findByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument())
    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  it('blocks the submit on an incomplete row, with the message on that row', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderEditor()

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    expect(await screen.findByText('La quantità è obbligatoria.')).toBeInTheDocument()
    expect(updateTableCellMock).not.toHaveBeenCalled()
  })

  it("maps the engine's per-row 422 (reported on `value`) back onto the edited row", async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateTableCellMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { errors: { 'value.0.quantity': ['Quantità non valida.'] } } },
    })

    renderEditor()

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    expect(await screen.findByText('Quantità non valida.')).toBeInTheDocument()
    expect(setData).not.toHaveBeenCalled()
  })

  // Spec 0114 AC-020: same component as the work panel (`RequestOfferLinesField`),
  // so a simplified classification hides the same three controls here too.
  it('hides quantity/unit price/VAT controls on a simplified classification', async () => {
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

    renderEditor()

    await screen.findByText(
      'Categoria semplificata: scegli il prodotto, quantità, prezzo unitario e aliquota IVA sono compilati automaticamente dal sistema.',
    )
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
    // REQUIREMENT CHANGED (spec 0268): no per-row picker any more — the row
    // reads its product, and new products come from the catalogue beside it.
    expect(screen.getByText('Fibra 1000')).toBeInTheDocument()
    expect(screen.getByRole('listbox', { name: 'Prodotti del catalogo' })).toBeInTheDocument()
  })
})

/**
 * Permessi, come per ogni altra colonna: il motore generico li impone su TRE
 * livelli — colonna (`GET /columns`), riga (`row.editable`) ed endpoint (403).
 * Qui si verifica il livello che questa superficie possiede davvero: la
 * matrice per-campo che il pannello restituisce insieme al record, cioe' cio'
 * che disabilita i controlli invece di far scoprire il divieto a salvataggio
 * fatto. Gli altri due sono coperti lato server
 * (RequestManagementOfferLinesWriteTest).
 */
/** One field-permission entry, in the full shape the envelope carries. */
function fieldPermission(visible: boolean, editable: boolean) {
  return { visible, hidden: !visible, editable, readonly: !editable, required: false, disabled: !editable }
}

describe('Gestione Richieste — linee di prodotto, permessi', () => {
  it('locks the editor and the save when the field permission is read-only', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({
      permissions: {
        ...FULL_PERMISSIONS,
        fields: { offer_lines: fieldPermission(true, false) },
      },
    }))

    renderEditor()

    expect(await screen.findByLabelText('Quantità riga 1')).toBeDisabled()
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeDisabled()
    // The row editor keeps its controls in place and inert, rather than
    // hiding them: a locked field still has to be readable. REQUIREMENT
    // CHANGED (spec 0268): nothing can be added, so the catalogue is not
    // mounted at all, and the row cannot be removed.
    expect(screen.getByRole('button', { name: 'Rimuovi riga 1' })).toBeDisabled()
    expect(screen.queryByRole('region', { name: 'Catalogo prodotti' })).not.toBeInTheDocument()
  })

  it('disables the save when the actor may not update the resource at all', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({
      permissions: {
        ...FULL_PERMISSIONS,
        resource: { ...FULL_PERMISSIONS.resource, update: false },
      },
    }))

    renderEditor()

    await screen.findByLabelText('Quantità riga 1')
    expect(screen.getByRole('button', { name: 'Salva' })).toBeDisabled()
  })

  it('hides the whole field when the permission makes it invisible', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({
      permissions: {
        ...FULL_PERMISSIONS,
        fields: { offer_lines: fieldPermission(false, false) },
      },
    }))

    renderEditor()

    await screen.findByRole('button', { name: 'Salva' })
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
  })
})
