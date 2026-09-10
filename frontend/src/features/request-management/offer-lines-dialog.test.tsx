import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OfferLinesCellEditor } from '@/features/request-management/offer-lines-cell-editor'
import { OfferLinesDialogProvider } from '@/features/request-management/offer-lines-dialog'
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

const setData = vi.fn()
const stopEditing = vi.fn()

/** The cell editor as AG Grid mounts it, inside the provider that owns the dialog. */
function renderEditor() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const props = {
    data: { id: 4001 } as unknown as TableRow,
    node: { setData },
    stopEditing,
  } as unknown as CustomCellEditorProps<TableRow>

  return render(
    <QueryClientProvider client={client}>
      <OfferLinesDialogProvider>
        <OfferLinesCellEditor {...props} />
      </OfferLinesDialogProvider>
    </QueryClientProvider>,
  )
}

/** The row shape the cell endpoint answers with — what replaces the grid row. */
const REMAPPED_ROW = { id: 4001, offer_lines: [{ id: 900, name: 'Fibra 1000' }] } as unknown as TableRow

const EXPECTED_ROWS = [{
  id: OFFER_LINE_FIBRA.id,
  product_id: OFFER_LINE_FIBRA.product_id,
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
})

describe('Gestione Richieste — linee di prodotto in griglia', () => {
  it('hands the row to the dialog and closes the cell editor immediately', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderEditor()

    // Suppressed post-edit navigation: the focus goes into the dialog, not
    // onto the next cell.
    expect(stopEditing).toHaveBeenCalledWith(true)
    expect(await screen.findByLabelText('Quantità riga 1')).toHaveValue(1)
    expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith(4001)
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
        management_mode: 'multiple',
        single_quote_per_opportunity: false,
        generates_contract: true,
        simplified_offer_line: true,
      },
    ])

    renderEditor()

    await screen.findByText(
      'Categoria semplificata: scegli il prodotto, quantità, prezzo unitario e aliquota IVA sono compilati automaticamente dal sistema.',
    )
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Prodotto riga 1' })).toBeInTheDocument()
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
    // hiding them: a locked field still has to be readable.
    expect(screen.getByRole('button', { name: /Aggiungi riga/i })).toBeDisabled()
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
