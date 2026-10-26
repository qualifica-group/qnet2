import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrderCostCard } from '@/features/work-order-costs/work-order-cost-card'
import { EMPTY_ROW_DISPLAY, type WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Spec 0270 D-3: one real-cost card on its own — every field of the former
 * grid row reaches the row through `onChangeField`, the live amounts, the
 * removal and the accessible error triad.
 */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const ROW: WorkOrderCostRowValues = {
  id: 5,
  product_id: 7,
  quantity: 2,
  unit_price: 50,
  vat_rate_id: 3,
  quote_line_id: null,
  cost_type_id: null,
  incurred_on: '2026-09-15',
  supplier_id: null,
  document_reference: null,
  additional_description: null,
  display: { ...EMPTY_ROW_DISPLAY, product_name: 'Noleggio auto', product_code: 'CST-0009', unit_symbol: 'gg', vat_name: 'IVA 22%', vat_percent: 22 },
}

const onChangeField = vi.fn()
const onRemove = vi.fn()

function renderCard({ disabled = false, error }: { disabled?: boolean; error?: Parameters<typeof WorkOrderCostCard>[0]['error'] } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ul>
        <WorkOrderCostCard
          index={0}
          row={ROW}
          disabled={disabled}
          revenueOptions={[{ quoteLineId: 11, label: 'PRD-1 — Consulenza' }]}
          error={error}
          onChangeField={onChangeField}
          onRemove={onRemove}
        />
      </ul>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  onChangeField.mockReset()
  onRemove.mockReset()
  // The server answers the page plus any `ids[]` asked for hydration: the first page here carries both.
  fetchForSelectMock.mockReset().mockImplementation((resource: string) => {
    const items = resource === 'vat-rates' ? [{ id: 4, label: 'IVA 10%', meta: { rate: '10.00' } }] : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
})

describe('WorkOrderCostCard', () => {
  it('reads the product first and shows the live amounts of the row', () => {
    renderCard()

    const card = within(screen.getByRole('listitem'))
    expect(card.getByText('Noleggio auto')).toBeInTheDocument()
    expect(card.getByText('CST-0009')).toBeInTheDocument()
    expect(card.getByText('gg ×')).toBeInTheDocument()
    expect(card.getByText('100.00')).toBeInTheDocument()
    expect(card.getByText('22.00')).toBeInTheDocument()
    expect(card.getByText('122.00')).toBeInTheDocument()
  })

  it('sends every edited field to the row', async () => {
    renderCard()

    fireEvent.change(screen.getByRole('spinbutton', { name: 'Unit price, row 1' }), { target: { value: '60' } })
    expect(onChangeField).toHaveBeenLastCalledWith({ unit_price: 60 })

    fireEvent.change(screen.getByLabelText('Cost date, row 1'), { target: { value: '2026-10-01' } })
    expect(onChangeField).toHaveBeenLastCalledWith({ incurred_on: '2026-10-01' })

    fireEvent.change(screen.getByLabelText('Document reference, row 1'), { target: { value: 'FT-1' } })
    expect(onChangeField).toHaveBeenLastCalledWith({ document_reference: 'FT-1' })

    fireEvent.click(screen.getByRole('combobox', { name: 'Reference offer line, row 1' }))
    fireEvent.click(await screen.findByRole('option', { name: 'PRD-1 — Consulenza' }))
    expect(onChangeField).toHaveBeenLastCalledWith({ quote_line_id: 11 })
    // Radix hands focus back to the previous trigger on the next tick; opening the next popover before that dismisses it.
    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
    await new Promise((resolve) => setTimeout(resolve, 0))

    fireEvent.click(screen.getByRole('combobox', { name: 'VAT rate, row 1' }))
    fireEvent.click(await screen.findByRole('option', { name: /IVA 10%/ }))
    await waitFor(() =>
      expect(onChangeField).toHaveBeenLastCalledWith(
        expect.objectContaining({ vat_rate_id: 4, display: expect.objectContaining({ vat_name: 'IVA 10%', vat_percent: 10 }) }),
      ),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Additional description' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Row 1 additional description' }), { target: { value: 'Andata e ritorno' } })
    expect(onChangeField).toHaveBeenLastCalledWith({ additional_description: 'Andata e ritorno' })
  })

  it('removes the row, and offers no removal on a read-only card', () => {
    const { unmount } = renderCard()
    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1' }))
    expect(onRemove).toHaveBeenCalledTimes(1)
    unmount()

    renderCard({ disabled: true })
    expect(screen.queryByRole('button', { name: 'Remove row 1' })).not.toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Unit price, row 1' })).toBeDisabled()
  })

  it('wires a unit price error with the accessible triad', () => {
    renderCard({ error: { unit_price: { type: 'server', message: 'Price not allowed.' } } })

    const input = screen.getByRole('spinbutton', { name: 'Unit price, row 1' })
    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('Price not allowed.')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', alert.id)
  })
})
