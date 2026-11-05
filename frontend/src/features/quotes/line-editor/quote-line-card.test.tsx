import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { ForSelectParams } from '@/features/for-select/types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import { QuoteLineCard } from './quote-line-card'
import type { QuoteLineCardSlots, QuoteLineProductView } from './types'

/**
 * Spec 0268/0269: one line card on its own — what the former
 * `quote-line-row.test.tsx` covered and the editor tests do not (product
 * code, unit of measure, manual VAT pick feeding the live preview), plus the
 * channel slots.
 */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const PRODUCT: QuoteLineProductView = {
  id: 900,
  name: 'Corso Sicurezza Base',
  code: 'SIC-900',
  categoryId: 500,
  typology: { name: 'Corso', color: 'blue' },
}

const ROW: QuoteLineFormValues = {
  ...EMPTY_LINE_ROW,
  product_id: 900,
  quantity: 2,
  unit_price: 100,
  unit_of_measure: { id: 1, name: 'Ora', symbol: 'h' },
}

const rememberVatRatePercent = vi.fn()

function renderCard(slots?: QuoteLineCardSlots, row: QuoteLineFormValues = ROW, lockedWorkOrderCodes: string[] | null = null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ul>
        <QuoteLineCard
          position={1}
          row={row}
          product={PRODUCT}
          categoryPath="Formazione > Sicurezza"
          disabled={false}
          simplified={false}
          vatRatePercentFor={() => null}
          rememberVatRatePercent={rememberVatRatePercent}
          onChangeField={vi.fn()}
          onRemove={vi.fn()}
          slots={slots}
          lockedWorkOrderCodes={lockedWorkOrderCodes}
        />
      </ul>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  rememberVatRatePercent.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: ForSelectParams = {}) => {
    const items = resource === 'vat-rates' && !params.ids?.length ? [{ id: 7, label: 'IVA 22%', meta: { rate: '22.00' } }] : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
})

describe('QuoteLineCard', () => {
  it('reads the product first: name, code, typology and category path', () => {
    renderCard()

    expect(screen.getByText('Corso Sicurezza Base')).toBeInTheDocument()
    expect(screen.getByText('SIC-900')).toBeInTheDocument()
    expect(screen.getByText('Corso')).toBeInTheDocument()
    expect(screen.getByText('Formazione > Sicurezza')).toBeInTheDocument()
  })

  it('shows the unit of measure symbol next to the quantity (spec 0088)', () => {
    renderCard()

    expect(screen.getByText('h ×')).toBeInTheDocument()
  })

  it('feeds the live preview with the percentage of a VAT rate picked by hand (AC-071)', async () => {
    renderCard()

    fireEvent.click(screen.getByRole('combobox', { name: 'Aliquota IVA riga 1' }))
    fireEvent.click(await screen.findByRole('option', { name: /IVA 22%/ }))

    await waitFor(() => expect(rememberVatRatePercent).toHaveBeenCalledWith(7, 22))
  })

  it('renders the channel slots: actions, level-2 field and footer', () => {
    renderCard({
      actions: <button type="button">azione</button>,
      extraField: <span>campo extra</span>,
      footer: <span>piede</span>,
    })

    const card = within(screen.getByRole('listitem'))
    expect(card.getByRole('button', { name: 'azione' })).toBeInTheDocument()
    expect(card.getByText('campo extra')).toBeInTheDocument()
    expect(card.getByText('piede')).toBeInTheDocument()
  })

  it('carries no channel extras without slots (Gestione Richieste: no commissions, no additional description)', () => {
    renderCard()

    expect(screen.queryByRole('button', { name: 'Commissioni della riga 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Descrizione aggiuntiva' })).not.toBeInTheDocument()
  })

  it('locks a row assigned to work orders: every control disabled, codes shown (spec 0360 AC-014)', () => {
    renderCard(undefined, { ...ROW, id: 12 }, ['COM-001', 'COM-002'])

    expect(screen.getByText('Assegnata a COM-001, COM-002')).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Quantità riga 1' })).toBeDisabled()
    expect(screen.getByRole('spinbutton', { name: 'Prezzo unitario riga 1' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Aliquota IVA riga 1' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Rimuovi riga 1' })).toBeDisabled()
  })

  it('leaves a free row editable', () => {
    renderCard()

    expect(screen.queryByText(/Assegnata a/)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Rimuovi riga 1' })).toBeEnabled()
  })
})
