import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ICellRendererParams } from 'ag-grid-community'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'
import { OfferLinesDialogProvider } from '@/features/request-management/offer-lines-dialog'
import {
  OFFER_LINE_FIBRA,
  workPanel as panel,
} from '@/features/request-management/request-work-panel-fixtures'

/**
 * Modifica rapida delle "Linee di prodotto" dalla griglia (direttiva utente
 * 2026-09-07): la cella apre il dialog che edita le RIGHE dell'offerta
 * (prodotto, quantita', prezzo, IVA) e salva sullo stesso
 * `PATCH /request-management/{quote}` del pannello Lavora.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

const canMock = vi.fn(() => true)
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** The grid cell as the renderer registry mounts it, inside the provider that owns the dialog. */
function renderCell(onSaved?: () => void) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const params = {
    value: [{ id: 900, name: 'Fibra 1000' }],
    data: { id: 4001 },
  } as unknown as ICellRendererParams

  return render(
    <QueryClientProvider client={client}>
      <OfferLinesDialogProvider onSaved={onSaved}>
        {requestManagementColumnRenderers.offer_lines(params)}
      </OfferLinesDialogProvider>
    </QueryClientProvider>,
  )
}

const EDIT_LABEL = "Modifica le righe dell'offerta"

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('Gestione Richieste — modifica rapida delle linee di prodotto', () => {
  it('renders the offer products and the quick-edit affordance', () => {
    renderCell()

    expect(screen.getByText('Fibra 1000')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: EDIT_LABEL })).toBeInTheDocument()
  })

  it('hides the affordance without the module update permission', () => {
    canMock.mockReturnValue(false)

    renderCell()

    expect(screen.queryByRole('button', { name: EDIT_LABEL })).not.toBeInTheDocument()
  })

  it('opens the dialog on the row and hydrates one editable row per persisted offer line', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderCell()
    fireEvent.click(screen.getByRole('button', { name: EDIT_LABEL }))

    expect(await screen.findByLabelText('Quantità riga 1')).toHaveValue(1)
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toHaveValue(100)
    expect(fetchRequestWorkPanelMock).toHaveBeenCalledWith(4001)
  })

  it('never offers the provvigioni control: this channel does not own that block', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderCell()
    fireEvent.click(screen.getByRole('button', { name: EDIT_LABEL }))

    await screen.findByLabelText('Quantità riga 1')
    expect(screen.queryByRole('button', { name: /Provvigioni/i })).not.toBeInTheDocument()
  })

  it('saves the whole collection and refreshes the grid', async () => {
    const loaded = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)
    const onSaved = vi.fn()

    renderCell(onSaved)
    fireEvent.click(screen.getByRole('button', { name: EDIT_LABEL }))

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][0]).toBe(4001)
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      offer_lines: [{
        id: OFFER_LINE_FIBRA.id,
        product_id: OFFER_LINE_FIBRA.product_id,
        quantity: 3,
        unit_price: 100,
        vat_rate_id: null,
        sort_order: 0,
      }],
    })
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('closes without a request when nothing was touched', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderCell()
    fireEvent.click(screen.getByRole('button', { name: EDIT_LABEL }))

    fireEvent.click(await screen.findByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument())
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  it('blocks the submit on an incomplete row, with the message on that row', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderCell()
    fireEvent.click(screen.getByRole('button', { name: EDIT_LABEL }))

    fireEvent.change(await screen.findByLabelText('Quantità riga 1'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Salva' }))

    expect(await screen.findByText('La quantità è obbligatoria.')).toBeInTheDocument()
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })
})
