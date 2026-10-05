import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteLinesField } from './quote-lines-field'

vi.mock('@/components/confirm-dialog-context', () => ({ useOptionalConfirm: () => null }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteLinesField layout', () => {
  it.each(['revenue', 'cost'] as const)(
    'keeps the %s table scrolling inside its box on a narrow screen',
    (variant) => {
      render(
        <QuoteLinesField
          value={[]}
          onChange={vi.fn()}
          variant={variant}
          disabled={false}
          knownProducts={[]}
          knownVatRates={[]}
          vatRatePercentFor={() => null}
          rememberVatRatePercent={vi.fn()}
        />,
      )

      // empty-state text -> min-width table -> scroller
      const scroller = screen.getByText(i18n.t('quotes.form.linesEmpty')).parentElement?.parentElement
      expect(scroller).toHaveClass('overflow-x-auto', 'contain-inline-size')
    },
  )
})
