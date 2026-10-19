import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteLineAdditionalDescription } from '@/features/quotes/quote-line-additional-description'
import { toLineInputs } from '@/features/quotes/quote-line-values'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'

/**
 * `additional_description`: the row's optional free text, sent on the wire
 * only when the row carries the key (an absent key keeps the stored value
 * server-side). REQUIREMENT CHANGED (spec 0269): the control is a slot of the
 * Offerte revenue card now (`QuoteOfferTab`), so it is exercised on its own
 * here; "not on a cost row / not in Gestione Richieste" moved to the tab and
 * editor tests, where that rule now lives.
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

function Harness({ initial, onChange }: { initial: string | null; onChange: (value: string | null) => void }) {
  const [value, setValue] = useState(initial)
  return (
    <QuoteLineAdditionalDescription
      lineNumber={1}
      value={value}
      disabled={false}
      onChange={(next) => {
        setValue(next)
        onChange(next)
      }}
    />
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLineAdditionalDescription', () => {
  it('opens the field on demand and bubbles the typed text up', () => {
    const onChange = vi.fn()
    render(<Harness initial={null} onChange={onChange} />)

    expect(screen.queryByRole('textbox', { name: 'Row 1 additional description' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Additional description' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Row 1 additional description' }), { target: { value: 'Montaggio incluso' } })

    expect(onChange).toHaveBeenLastCalledWith('Montaggio incluso')
  })

  it('starts open on a stored description and clears it on remove', () => {
    const onChange = vi.fn()
    render(<Harness initial="Esistente" onChange={onChange} />)

    expect(screen.getByRole('textbox', { name: 'Row 1 additional description' })).toHaveValue('Esistente')
    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1 additional description' }))

    expect(onChange).toHaveBeenLastCalledWith(null)
    expect(screen.queryByRole('textbox', { name: 'Row 1 additional description' })).not.toBeInTheDocument()
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
