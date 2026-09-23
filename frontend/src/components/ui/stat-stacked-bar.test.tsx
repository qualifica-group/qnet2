import { describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { StatStackedBar, type StatStackedItem } from '@/components/ui/stat-stacked-bar'

const ITEMS: StatStackedItem[] = [
  { key: 'todo', label: 'To do', value: 30, color: 'var(--chart-1)' },
  { key: 'done', label: 'Done', value: 70, color: 'var(--chart-2)' },
]

describe('StatStackedBar', () => {
  it('renders the title as a heading', () => {
    render(<StatStackedBar title="By status" items={ITEMS} total={100} />)

    expect(screen.getByRole('heading', { name: 'By status' })).toBeInTheDocument()
  })

  it('exposes a visible legend with label, value and percent as real text (never color-only)', () => {
    render(<StatStackedBar title="By status" items={ITEMS} total={100} />)

    const list = screen.getByRole('list')
    const items = within(list).getAllByRole('listitem')

    expect(items).toHaveLength(2)
    expect(items[0]).toHaveTextContent('To do')
    expect(items[0]).toHaveTextContent('30')
    expect(items[0]).toHaveTextContent('(30%)')
    expect(items[1]).toHaveTextContent('Done')
    expect(items[1]).toHaveTextContent('70')
    expect(items[1]).toHaveTextContent('(70%)')
    // The legend list is a real, visible list — not the sr-only accessibility twin.
    expect(list).not.toHaveClass('sr-only')
  })

  it('sizes each segment proportionally to the total, with its resolved color', () => {
    const { container } = render(<StatStackedBar title="By status" items={ITEMS} total={100} />)

    const segments = container.querySelectorAll('[aria-hidden] > div')

    expect(segments).toHaveLength(2)
    expect(segments[0]).toHaveStyle({ width: '30%', backgroundColor: 'var(--chart-1)' })
    expect(segments[1]).toHaveStyle({ width: '70%', backgroundColor: 'var(--chart-2)' })
  })

  it('applies formatValue to the legend values', () => {
    render(
      <StatStackedBar
        title="Budget"
        items={[{ key: 'a', label: 'A', value: 1500, color: 'var(--chart-1)' }]}
        total={1500}
        formatValue={(value) => `${value} EUR`}
      />
    )

    expect(screen.getByText(/1500 EUR/)).toBeInTheDocument()
  })

  it('shows a discreet placeholder and no bar when items is empty', () => {
    render(<StatStackedBar title="By status" items={[]} total={0} emptyLabel="No data" />)

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(screen.queryByRole('list')).not.toBeInTheDocument()
  })
})
