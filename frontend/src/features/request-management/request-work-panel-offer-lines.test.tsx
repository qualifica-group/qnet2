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

/**
 * "Linee dell'offerta" nel pannello di lavorazione (direttiva utente
 * 2026-08-07): e' il row editor delle Offerte (`QuoteLinesField`) montato
 * qui, senza il blocco provvigioni — che questo canale non possiede.
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
    expect(updateRequestWorkMock.mock.calls[0][1]).not.toHaveProperty('offer_lines')
  })

  it('opens on one empty row when the request carries no line yet, and still saves without it', async () => {
    const loaded = panel({ offer_lines: [] })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    // Direttiva utente 2026-09-09: la riga vuota c'e' gia', non si passa da
    // "Aggiungi riga". Restando intatta non viaggia (`toLineInputs` la scarta).
    expect(await screen.findByLabelText('Quantità riga 1')).toHaveValue(null)

    fireEvent.change(screen.getByLabelText(/Data del richiamo/i), {
      target: { value: '2026-09-01' },
    })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).not.toHaveProperty('offer_lines')
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
        management_mode: 'multiple',
        single_quote_per_opportunity: false,
        generates_contract: true,
        simplified_offer_line: true,
      },
    ])

    renderPanel()

    await screen.findByText(
      'Categoria semplificata: scegli il prodotto, quantità, prezzo unitario e aliquota IVA sono compilati automaticamente dal sistema.',
    )
    expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Prezzo unitario riga 1')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Aliquota IVA riga 1')).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Prodotto riga 1' })).toBeInTheDocument()
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
        management_mode: 'multiple',
        single_quote_per_opportunity: false,
        generates_contract: true,
        simplified_offer_line: false,
      },
    ])

    renderPanel()

    expect(await screen.findByLabelText('Quantità riga 1')).toBeInTheDocument()
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toBeInTheDocument()
  })
})
