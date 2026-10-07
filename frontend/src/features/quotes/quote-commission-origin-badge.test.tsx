import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteCommissionOriginBadge } from './quote-commission-origin-badge'

beforeAll(async () => i18n.changeLanguage('en'))

describe('QuoteCommissionOriginBadge (user directive 2026-10-07)', () => {
  it('names a product rule as a rule and explains it on focus', async () => {
    render(<QuoteCommissionOriginBadge origin="PRODUCT" />)

    expect(screen.getByText('Rule:')).toBeInTheDocument()
    expect(screen.getByText('Product')).toBeInTheDocument()

    fireEvent.focus(screen.getByText('Product').parentElement!)

    expect(await screen.findByRole('tooltip')).toHaveTextContent(
      'Calculated by the Commission Configurator rule set on this product.',
    )
  })

  it('adds no prefix to labels that already read as a rule or an override', () => {
    const { rerender } = render(<QuoteCommissionOriginBadge origin="RECIPIENT" />)
    expect(screen.getByText('Personal rule')).toBeInTheDocument()
    expect(screen.queryByText('Rule:')).not.toBeInTheDocument()

    rerender(<QuoteCommissionOriginBadge origin="MANUAL_OVERRIDE" />)
    expect(screen.getByText('Manual override')).toBeInTheDocument()
    expect(screen.queryByText('Rule:')).not.toBeInTheDocument()
  })
})
