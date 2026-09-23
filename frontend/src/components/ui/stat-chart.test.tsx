import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { StatChart, type StatChartPoint } from '@/components/ui/stat-chart'

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

const POINTS: StatChartPoint[] = [
  { label: '2026-01', value: 12 },
  { label: '2026-02', value: 0 },
  { label: '2026-03', value: 7 },
]

const readSource = (file: string) =>
  readFileSync(path.resolve(__dirname, file), 'utf8')

describe('StatChart', () => {
  // Must run first: once any test has resolved the lazy import, React renders the
  // cached chunk synchronously and the Suspense fallback is never shown again.
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(<StatChart title="New per month" points={POINTS} />)

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list')

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders the title as a heading', () => {
    render(<StatChart title="New per month" points={POINTS} />)

    expect(screen.getByRole('heading', { name: 'New per month' })).toBeInTheDocument()
  })

  it('exposes every point as text once the lazy chart has loaded', async () => {
    render(<StatChart title="New per month" points={POINTS} />)

    const list = await screen.findByRole('list')
    const items = within(list).getAllByRole('listitem')

    expect(items.map((item) => item.textContent)).toEqual([
      '2026-01: 12',
      '2026-02: 0',
      '2026-03: 7',
    ])
  })

  it('applies formatValue to the accessible values', async () => {
    render(
      <StatChart
        title="Budget per month"
        points={[{ label: '2026-01', value: 1500 }]}
        formatValue={(value) => `${value} EUR`}
      />
    )

    const list = await screen.findByRole('list')

    expect(within(list).getByRole('listitem')).toHaveTextContent('2026-01: 1500 EUR')
  })

  it('shows a discreet placeholder and loads no chart when there are no points', () => {
    const { container } = render(
      <StatChart title="New per month" points={[]} emptyLabel="No data" />
    )

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk (AC-013)', () => {
    const wrapper = readSource('stat-chart.tsx')
    const impl = readSource('stat-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(/React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/stat-chart-impl["']\)/)
    expect(impl).toMatch(/from ["']recharts["']/)
    // The impl must only be reachable through the lazy boundary above.
    expect(impl).not.toMatch(/import\s+\{[^}]*StatChart[^}]*\}\s+from ["']@\/components\/ui\/stat-chart["']/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('stat-chart-impl.tsx')

    expect(impl).toMatch(/className="h-40 w-full sm:h-48"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })

  it('renders a columns variant without crashing and keeps the accessible text list', async () => {
    render(<StatChart title="New per month" points={POINTS} variant="columns" />)

    const list = await screen.findByRole('list')

    expect(within(list).getAllByRole('listitem')).toHaveLength(3)
  })

  it('renders a line variant without crashing and keeps the accessible text list', async () => {
    render(<StatChart title="New per month" points={POINTS} variant="line" />)

    const list = await screen.findByRole('list')

    expect(within(list).getAllByRole('listitem')).toHaveLength(3)
  })

  it('defaults to tone 1 / area when no variant or tone is given (retro-compatible, spec 0152)', async () => {
    const { container } = render(<StatChart title="New per month" points={POINTS} />)

    await screen.findByRole('list')

    expect(container.querySelector('path[fill^="url(#"]')).toBeInTheDocument()
  })
})
