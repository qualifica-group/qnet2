import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { StatDonutChart, type StatDonutItem } from '@/components/ui/stat-donut-chart'

// jsdom reports a 0x0 layout, so recharts' ResponsiveContainer would render nothing.
// Only the sizing wrapper is replaced: the chart components under test stay real.
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof import('recharts')>()

  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      React.cloneElement(children as React.ReactElement<{ width: number; height: number }>, {
        width: 400,
        height: 200,
      }),
  }
})

const ITEMS: StatDonutItem[] = [
  { key: 'open', label: 'Open', value: 30, color: 'var(--chart-1)' },
  { key: 'closed', label: 'Closed', value: 70, color: 'var(--chart-2)' },
]

const readSource = (file: string) => readFileSync(path.resolve(__dirname, file), 'utf8')

describe('StatDonutChart', () => {
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(<StatDonutChart title="By status" items={ITEMS} total={100} />)

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list')

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders the title as a heading', () => {
    render(<StatDonutChart title="By status" items={ITEMS} total={100} />)

    expect(screen.getByRole('heading', { name: 'By status' })).toBeInTheDocument()
  })

  it('shows the total at the center, in proportional (non-tabular) figures', async () => {
    render(<StatDonutChart title="By status" items={ITEMS} total={100} />)

    const total = await screen.findByText('100')

    expect(total).not.toHaveClass('tabular-nums')
  })

  it('renders a visible legend with label, value and percent — never color-only', async () => {
    render(<StatDonutChart title="By status" items={ITEMS} total={100} />)

    const list = await screen.findByRole('list')
    const items = within(list).getAllByRole('listitem')

    expect(items).toHaveLength(2)
    expect(items[0]).toHaveTextContent('Open')
    expect(items[0]).toHaveTextContent('30')
    expect(items[0]).toHaveTextContent('(30%)')
    expect(items[1]).toHaveTextContent('Closed')
    expect(items[1]).toHaveTextContent('(70%)')
    expect(list).not.toHaveClass('sr-only')
  })

  it('shows a discreet placeholder and loads no chart when there are no items', () => {
    const { container } = render(<StatDonutChart title="By status" items={[]} total={0} emptyLabel="No data" />)

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk', () => {
    const wrapper = readSource('stat-donut-chart.tsx')
    const impl = readSource('stat-donut-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(
      /React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/stat-donut-chart-impl["']\)/
    )
    expect(impl).toMatch(/from ["']recharts["']/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('stat-donut-chart-impl.tsx')

    expect(impl).toMatch(/className="relative h-40 w-full sm:h-48"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })
})
