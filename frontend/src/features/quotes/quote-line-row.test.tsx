import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteLineRow } from '@/features/quotes/quote-line-row'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0065 AC-074/AC-075: selecting a product precompiles `unit_price` from
 * `meta.price` (Offer) vs `meta.cost` (Cost), and a row error is wired to its
 * field via `aria-describedby`/`aria-invalid`/`role="alert"`.
 */

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const PRODUCT_PAGE = {
  items: [
    {
      id: 42,
      label: 'Widget Pro',
      subtitle: 'Widgets',
      meta: {
        code: 'PRD-0042',
        price: '120.00',
        cost: '80.00',
        vat_rate_id: 7,
        vat_rate_name: 'IVA 22%',
        vat_rate: '22.00',
      },
    },
  ],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}

const VAT_RATE_PAGE = {
  items: [{ id: 9, label: 'IVA 10%', meta: { rate: '10.00' } }],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

const EMPTY_ROW: QuoteLineFormValues = {
  product_id: null,
  quantity: 1,
  unit_price: null,
  vat_rate_id: null,
}

async function pickProduct() {
  fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 product' }))
  await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
  // No whitespace between the label/subtitle `<span>`s in the option's markup,
  // so the accessible-name algorithm concatenates them WITHOUT a space.
  fireEvent.click(await screen.findByRole('option', { name: 'Widget ProWidgets' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string) => {
    if (resource === 'products') {
      return Promise.resolve(PRODUCT_PAGE)
    }
    if (resource === 'vat-rates') {
      return Promise.resolve(VAT_RATE_PAGE)
    }
    return Promise.resolve(EMPTY_PAGE)
  })
})

describe('QuoteLineRow (spec 0065)', () => {
  it('precompiles unit_price from meta.price and the VAT rate on an Offer row (AC-074)', async () => {
    const onChangeProduct = vi.fn()
    render(
      <QuoteLineRow
        index={0}
        row={EMPTY_ROW}
        disabled={false}
        vatRatePercentFor={() => null}
        onChangeProduct={onChangeProduct}
        onChangeField={vi.fn()}
        onRemove={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await pickProduct()

    expect(onChangeProduct).toHaveBeenCalledWith(
      42,
      expect.objectContaining({ meta: expect.objectContaining({ price: '120.00', vat_rate_id: 7 }) }),
    )
  })

  it('shows the product code read-only after picking it', async () => {
    // A small controlled harness: the row is a controlled component, so its
    // `product_id` only reflects the pick once the caller feeds it back in
    // (exactly like `QuoteLinesField` does via `setProduct`).
    function Harness() {
      const [row, setRow] = useState<QuoteLineFormValues>(EMPTY_ROW)
      return (
        <QuoteLineRow
          index={0}
          row={row}
          disabled={false}
          vatRatePercentFor={() => null}
          onChangeProduct={(productId) => setRow((previous) => ({ ...previous, product_id: productId }))}
          onChangeField={vi.fn()}
          onRemove={vi.fn()}
        />
      )
    }

    render(<Harness />, { wrapper: wrapper() })

    await pickProduct()

    expect(await screen.findByText('PRD-0042')).toBeInTheDocument()
  })

  it('wires a quantity error to its input via aria-describedby/aria-invalid and role=alert (AC-075)', () => {
    render(
      <QuoteLineRow
        index={0}
        row={{ ...EMPTY_ROW, quantity: 0 }}
        disabled={false}
        vatRatePercentFor={() => null}
        error={{ quantity: { type: 'custom', message: 'Enter a quantity greater than zero.' } }}
        onChangeProduct={vi.fn()}
        onChangeField={vi.fn()}
        onRemove={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const quantityInput = screen.getByRole('spinbutton', { name: 'Row 1 quantity' })
    expect(quantityInput).toHaveAttribute('aria-invalid', 'true')
    const describedBy = quantityInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()

    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('Enter a quantity greater than zero.')
    expect(alert.id).toBe(describedBy)
  })

  it('computes net/vat/total read-only from quantity, unit price and the resolved VAT percent', () => {
    render(
      <QuoteLineRow
        index={0}
        row={{ product_id: 42, quantity: 3, unit_price: 10, vat_rate_id: 7 }}
        disabled={false}
        vatRatePercentFor={(id) => (id === 7 ? 22 : null)}
        onChangeProduct={vi.fn()}
        onChangeField={vi.fn()}
        onRemove={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText('30.00')).toBeInTheDocument()
    expect(screen.getByText('6.60')).toBeInTheDocument()
    expect(screen.getByText('36.60')).toBeInTheDocument()
  })

  it('shows the unit of measure symbol read-only, next to the quantity (spec 0088 AC-060)', () => {
    render(
      <QuoteLineRow
        index={0}
        row={{
          ...EMPTY_ROW,
          unit_of_measure: { id: 1, name: 'Kilogram', symbol: 'kg' },
        }}
        disabled={false}
        vatRatePercentFor={() => null}
        onChangeProduct={vi.fn()}
        onChangeField={vi.fn()}
        onRemove={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText('kg')).toBeInTheDocument()
  })

  it('falls back to a placeholder when a freshly-added row has no unit of measure yet', () => {
    render(
      <QuoteLineRow
        index={0}
        row={EMPTY_ROW}
        disabled={false}
        vatRatePercentFor={() => null}
        onChangeProduct={vi.fn()}
        onChangeField={vi.fn()}
        onRemove={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // Two placeholders render for an unpicked row: the read-only product
    // `code` cell and the unit of measure cell this test targets.
    expect(screen.getAllByText('—')).toHaveLength(2)
  })

  it('refreshes the live summary the moment the user manually picks a never-before-seen VAT rate (AC-071)', async () => {
    // Mirrors the real `useQuoteForm` percent cache: a row on a product WITHOUT
    // a VAT rate (or on a rate the row never saw via product pick/edit
    // hydration) starts with `vatRatePercentFor` returning `null` for every
    // id, exactly the bug report's repro.
    function Harness() {
      const [row, setRow] = useState<QuoteLineFormValues>({
        product_id: null,
        quantity: 10,
        unit_price: 5,
        vat_rate_id: null,
      })
      const [percentById, setPercentById] = useState<Record<number, number>>({})
      return (
        <QuoteLineRow
          index={0}
          row={row}
          disabled={false}
          vatRatePercentFor={(id) => percentById[id] ?? null}
          rememberVatRatePercent={(id, percent) => setPercentById((previous) => ({ ...previous, [id]: percent }))}
          onChangeProduct={vi.fn()}
          onChangeField={(patch) => setRow((previous) => ({ ...previous, ...patch }))}
          onRemove={vi.fn()}
        />
      )
    }

    render(<Harness />, { wrapper: wrapper() })

    // Before picking: no VAT rate known, so vat is 0 and total equals net
    // (both render as "50.00").
    expect(screen.getAllByText('50.00')).toHaveLength(2)
    expect(screen.getByText('0.00')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 VAT rate' }))
    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith('vat-rates', expect.anything()),
    )
    fireEvent.click(await screen.findByRole('option', { name: 'IVA 10%' }))

    // The summary recomputes instantly from the picked item's own `meta.rate`
    // (client-side, `computeLineAmounts`): every recorded fetch still targets
    // only the picker's own `vat-rates` for-select resource, never a separate
    // calculation round-trip.
    expect(await screen.findByText('5.00')).toBeInTheDocument()
    expect(screen.getByText('55.00')).toBeInTheDocument()
    expect(fetchForSelectMock.mock.calls.every(([resource]) => resource === 'vat-rates')).toBe(true)
  })

  it('hides the revenue commission action when the commissions field is not visible', () => {
    const permissions: ResourcePermissions = {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
      actions: {},
      fields: {
        commissions: {
          visible: false,
          hidden: true,
          editable: false,
          readonly: false,
          required: false,
          disabled: true,
        },
      },
    }
    render(
      <ResourcePermissionsProvider permissions={permissions}>
        <QuoteLineRow
          index={0}
          variant="revenue"
          row={{ ...EMPTY_ROW, product_id: 42, commissions: [] }}
          disabled={false}
          vatRatePercentFor={() => null}
          onChangeProduct={vi.fn()}
          onChangeField={vi.fn()}
          onRemove={vi.fn()}
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )
    expect(screen.queryByRole('button', { name: 'Commissions for line 1' })).not.toBeInTheDocument()
  })
})
