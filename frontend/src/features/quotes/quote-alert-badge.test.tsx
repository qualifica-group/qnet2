import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteAlertBadge } from '@/features/quotes/quote-alert-badge'

/** Spec 0102 (AC-036): icon AND text always together, never color alone. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteAlertBadge', () => {
  it('renders an icon AND text for "missing_offer_lines"', () => {
    const { container } = render(<QuoteAlertBadge alert="missing_offer_lines" />)
    expect(screen.getByText('Missing offer rows')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it('renders nothing when there is no alert', () => {
    const { container } = render(<QuoteAlertBadge alert={null} />)
    expect(container.textContent).toBe('')
  })
})
