import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteLineRow } from '@/features/quotes/quote-line-row'
import { toLineInputs } from '@/features/quotes/quote-line-values'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'

/**
 * `additional_description`: editable only on the Offerte revenue row, and sent
 * on the wire only when the row carries the key (an absent key keeps the
 * stored value server-side).
 */

const ROW: QuoteLineFormValues = {
  id: 5,
  product_id: 42,
  quantity: 1,
  unit_price: 10,
  vat_rate_id: null,
  additional_description: null,
  commissions: [],
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function Harness({ initial, variant = 'revenue', withCommissions = true, onChange }: {
  initial: QuoteLineFormValues
  variant?: 'revenue' | 'cost'
  withCommissions?: boolean
  onChange?: (row: QuoteLineFormValues) => void
}) {
  const [row, setRow] = useState(initial)
  return (
    <QuoteLineRow
      index={0}
      variant={variant}
      withCommissions={withCommissions}
      row={row}
      disabled={false}
      vatRatePercentFor={() => null}
      onChangeProduct={vi.fn()}
      onChangeField={(patch) => {
        const next = { ...row, ...patch }
        setRow(next)
        onChange?.(next)
      }}
      onRemove={vi.fn()}
    />
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLineRow additional description', () => {
  it('opens the field on demand and bubbles the typed text up', () => {
    const onChange = vi.fn()
    render(<Harness initial={ROW} onChange={onChange} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox', { name: 'Row 1 additional description' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Additional description' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Row 1 additional description' }), { target: { value: 'Montaggio incluso' } })

    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ additional_description: 'Montaggio incluso' }))
  })

  it('starts open on a stored description and clears it on remove', () => {
    const onChange = vi.fn()
    render(<Harness initial={{ ...ROW, additional_description: 'Esistente' }} onChange={onChange} />, { wrapper: wrapper() })

    expect(screen.getByRole('textbox', { name: 'Row 1 additional description' })).toHaveValue('Esistente')
    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1 additional description' }))

    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ additional_description: null }))
    expect(screen.queryByRole('textbox', { name: 'Row 1 additional description' })).not.toBeInTheDocument()
  })

  it('is not editable on a cost row nor on a channel without commissions', () => {
    const { unmount } = render(<Harness initial={ROW} variant="cost" />, { wrapper: wrapper() })
    expect(screen.queryByRole('button', { name: 'Additional description' })).not.toBeInTheDocument()
    unmount()

    render(<Harness initial={ROW} withCommissions={false} />, { wrapper: wrapper() })
    expect(screen.queryByRole('button', { name: 'Additional description' })).not.toBeInTheDocument()
  })
})

describe('toLineInputs additional_description', () => {
  it('sends trimmed text, blank as null, and omits the key when the row has none', () => {
    const withoutKey: QuoteLineFormValues = { ...ROW }
    delete withoutKey.additional_description
    const [trimmed, blank, absent] = toLineInputs([
      { ...ROW, additional_description: '  Nota  ' },
      { ...ROW, additional_description: '   ' },
      withoutKey,
    ])

    expect(trimmed.additional_description).toBe('Nota')
    expect(blank.additional_description).toBeNull()
    expect(absent).not.toHaveProperty('additional_description')
  })
})
