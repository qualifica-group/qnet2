import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityStatusSummary } from '@/features/opportunities/types'

/**
 * Spec 0082, AC-013: one distinct status renders as the status itself; two or
 * more collapse into the neutral "N stati" badge whose tooltip carries the
 * per-status breakdown.
 */
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const SINGLE: OpportunityStatusSummary = {
  source: 'quotes',
  distinct_count: 1,
  entries: [{ id: 1, name: 'Bozza', color: 'green', group: 'open', count: 4 }],
}

const MULTIPLE: OpportunityStatusSummary = {
  source: 'quotes',
  distinct_count: 2,
  entries: [
    { id: 1, name: 'In lavorazione', color: 'blue', group: 'open', count: 1 },
    { id: 2, name: 'In corso', color: 'amber', group: 'pending', count: 1 },
  ],
}

describe('OpportunityStatusBadge', () => {
  it('renders the status name and its color when a single status is shared by every quote', () => {
    const { container } = render(<OpportunityStatusBadge summary={SINGLE} />)

    expect(screen.getByText('Bozza')).toBeInTheDocument()
    expect(container.querySelector('.bg-green-100')).not.toBeNull()
    // The count never surfaces when there is only one status.
    expect(screen.queryByText(/4/)).not.toBeInTheDocument()
  })

  it('renders "N statuses" with the breakdown as its accessible name when the quotes disagree', () => {
    render(<OpportunityStatusBadge summary={MULTIPLE} />)

    expect(screen.getByText('2 statuses')).toBeInTheDocument()
    expect(screen.getByLabelText('1 In lavorazione, 1 In corso')).toBeInTheDocument()
  })

  it('renders the working-state fallback like any other single status', () => {
    render(
      <OpportunityStatusBadge
        summary={{
          source: 'workflow',
          distinct_count: 1,
          entries: [{ id: 9, name: 'Da lavorare', color: 'blue', group: 'open', count: 1 }],
        }}
      />,
    )

    expect(screen.getByText('Da lavorare')).toBeInTheDocument()
  })

  it('renders nothing when no status resolves', () => {
    const { container } = render(
      <OpportunityStatusBadge summary={{ source: 'workflow', distinct_count: 0, entries: [] }} />,
    )

    expect(container).toBeEmptyDOMElement()
  })
})
