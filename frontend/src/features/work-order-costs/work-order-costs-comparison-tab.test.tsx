import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { WorkOrderCostsComparisonTab } from '@/features/work-order-costs/work-order-costs-comparison-tab'
import { OVERVIEW } from '@/features/work-order-costs/work-order-costs-fixtures'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function statValue(label: string): HTMLElement {
  const term = screen.getByText(label)
  const value = term.nextElementSibling
  if (!(value instanceof HTMLElement)) throw new Error(`No value for ${label}`)
  return value
}

describe('WorkOrderCostsComparisonTab (spec 0252, AC-008)', () => {
  it('shows the totals by source, the overall actual, the margin and the variance', () => {
    render(<WorkOrderCostsComparisonTab overview={OVERVIEW} />)

    expect(statValue('Manually entered costs')).toHaveTextContent('100.00')
    expect(statValue('Committed ODA (not yet invoiced)')).toHaveTextContent('40.00')
    expect(statValue('Passive invoiced')).toHaveTextContent('60.00')
    expect(statValue('Total actual cost')).toHaveTextContent('200.00')
    expect(statValue('Actual margin (on the total)')).toHaveTextContent('600.00')
    expect(statValue('Total variance from budget')).toHaveTextContent('120.00')
  })

  it('lists the cost types with badge, translated nature, the three sources and the total', () => {
    render(<WorkOrderCostsComparisonTab overview={OVERVIEW} />)

    const table = screen.getByRole('table', { name: 'Actual costs by cost type and source' })
    const travel = within(table).getByRole('row', { name: /Travel/ })
    expect(within(travel).getByText('Direct')).toBeInTheDocument()
    expect(travel).toHaveTextContent('100.00')
    expect(travel).toHaveTextContent('40.00')
    expect(travel).toHaveTextContent('140.00')

    const untyped = within(table).getByRole('row', { name: /No cost type/ })
    expect(untyped).toHaveTextContent('60.00')
  })

  it('shows an empty state when there are no costs by cost type', () => {
    const overview: WorkOrderCostOverview = {
      ...OVERVIEW,
      comparison: { ...OVERVIEW.comparison, by_cost_type: [] },
    }
    render(<WorkOrderCostsComparisonTab overview={overview} />)

    expect(screen.getByText('No actual costs by cost type.')).toBeInTheDocument()
    expect(screen.queryByRole('table', { name: 'Actual costs by cost type and source' })).not.toBeInTheDocument()
  })
})
