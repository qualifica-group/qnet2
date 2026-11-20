import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ExistingOpportunityAlert } from '@/features/opportunities/existing-opportunity-alert'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderAlert(props: Partial<React.ComponentProps<typeof ExistingOpportunityAlert>> = {}) {
  return render(
    <MemoryRouter>
      <ExistingOpportunityAlert opportunityId={5} message="Already open." {...props} />
    </MemoryRouter>,
  )
}

describe('ExistingOpportunityAlert', () => {
  it('offers only the way to the opportunity when no products travel with the refusal', () => {
    renderAlert()

    expect(screen.getByRole('alert')).toHaveTextContent('Already open.')
    expect(screen.getByRole('link', { name: 'Go to the opportunity' })).toHaveAttribute('href', '/opportunities/5')
    expect(screen.getAllByRole('link')).toHaveLength(1)
  })

  // Spec 0396 AC-014: the procedure carries no products, yet the way out is always offered.
  it('AC-014: offerLinkLabel always offers the offer on the opportunity, opened on it', () => {
    renderAlert({ offerLinkLabel: "Create the quote on this opportunity" })

    expect(screen.getByRole('link', { name: 'Create the quote on this opportunity' })).toHaveAttribute(
      'href',
      '/quotes/new?opportunity_id=5',
    )
  })
})
