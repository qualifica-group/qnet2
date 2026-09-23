import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { StatColumnChart, type StatColumnItem } from '@/components/ui/stat-column-chart'

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

const ITEMS: StatColumnItem[] = [
  { key: 'web', label: 'Web', value: 51, color: 'var(--chart-1)' },
  { key: 'phone', label: 'Phone', value: 25, color: 'var(--chart-2)' },
]

const readSource = (file: string) => readFileSync(path.resolve(__dirname, file), 'utf8')

describe('StatColumnChart', () => {
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(<StatColumnChart title="By source" items={ITEMS} />)

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list')

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders the title as a heading', () => {
    render(<StatColumnChart title="By source" items={ITEMS} />)

    expect(screen.getByRole('heading', { name: 'By source' })).toBeInTheDocument()
  })

  it('exposes every item as text once the lazy chart has loaded', async () => {
    render(<StatColumnChart title="By source" items={ITEMS} />)

    const list = await screen.findByRole('list')
    const items = within(list).getAllByRole('listitem')

    expect(items.map((item) => item.textContent)).toEqual(['Web: 51', 'Phone: 25'])
  })

  it('shows a discreet placeholder and loads no chart when there are no items', () => {
    const { container } = render(<StatColumnChart title="By source" items={[]} emptyLabel="No data" />)

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk', () => {
    const wrapper = readSource('stat-column-chart.tsx')
    const impl = readSource('stat-column-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(
      /React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/stat-column-chart-impl["']\)/
    )
    expect(impl).toMatch(/from ["']recharts["']/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('stat-column-chart-impl.tsx')

    expect(impl).toMatch(/className="h-40 w-full sm:h-48"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })
})
