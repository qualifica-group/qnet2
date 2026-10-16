import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import {
  ComparisonBarChart,
  type ComparisonBarChartPoint,
} from '@/components/ui/comparison-bar-chart'

/**
 * Spec 0268 AC-019, mirrors `report-bar-chart.test.tsx`: two series per point
 * with their labels, lazy recharts boundary, placeholder when empty, theme
 * tokens only.
 */

// jsdom reports a 0x0 layout, so the ResponsiveContainer of recharts would render nothing.
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

const SERIES_LABELS = { budget: 'Expected', actual: 'Actual' }
const POINTS: ComparisonBarChartPoint[] = [
  { label: 'GOL', budget: 1000, actual: 800 },
  { label: 'APL', budget: 500, actual: -200 },
]

const readSource = (file: string) => readFileSync(path.resolve(__dirname, file), 'utf8')

describe('ComparisonBarChart', () => {
  // Must run first: once the lazy import resolved, the fallback is never shown again.
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(
      <ComparisonBarChart title="Expected vs actual" points={POINTS} seriesLabels={SERIES_LABELS} />,
    )

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list', { name: 'Expected vs actual' })

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders the title as a heading and an image-role chart with a descriptive label', async () => {
    render(<ComparisonBarChart title="Expected vs actual" points={POINTS} seriesLabels={SERIES_LABELS} />)

    expect(screen.getByRole('heading', { name: 'Expected vs actual' })).toBeInTheDocument()
    expect(
      await screen.findByRole('img', { name: /Expected vs actual.*Expected.*Actual/ }),
    ).toBeInTheDocument()
  })

  it('exposes both series with their labels for every point, negatives included', async () => {
    render(
      <ComparisonBarChart
        title="Expected vs actual"
        points={POINTS}
        seriesLabels={SERIES_LABELS}
        formatValue={(value) => `${value} EUR`}
      />,
    )

    const list = await screen.findByRole('list', { name: 'Expected vs actual' })
    const items = within(list).getAllByRole('listitem')

    expect(items.map((item) => item.textContent)).toEqual([
      'GOL: Expected 1000 EUR, Actual 800 EUR',
      'APL: Expected 500 EUR, Actual -200 EUR',
    ])
  })

  it('renders the title at the requested heading level', () => {
    render(
      <ComparisonBarChart title="Expected vs actual" points={[]} seriesLabels={SERIES_LABELS} headingLevel={4} />,
    )

    expect(screen.getByRole('heading', { name: 'Expected vs actual', level: 4 })).toBeInTheDocument()
  })

  it('shows a discreet placeholder and loads no chart when there are no points', () => {
    const { container } = render(
      <ComparisonBarChart
        title="Expected vs actual"
        points={[]}
        seriesLabels={SERIES_LABELS}
        emptyLabel="No data"
      />,
    )

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk', () => {
    const wrapper = readSource('comparison-bar-chart.tsx')
    const impl = readSource('comparison-bar-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(
      /React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/comparison-bar-chart-impl["']\)/,
    )
    expect(impl).toMatch(/from ["']recharts["']/)
    expect(impl).not.toMatch(
      /import\s+\{[^}]*ComparisonBarChart[^}]*\}\s+from ["']@\/components\/ui\/comparison-bar-chart["']/,
    )
  })

  it('uses two distinct theme CSS variables, never a hex literal', () => {
    const impl = readSource('comparison-bar-chart-impl.tsx')

    expect(impl).toMatch(/CHART_SERIES_COLORS\[0\]/)
    expect(impl).toMatch(/CHART_SERIES_COLORS\[1\]/)
    expect(impl).not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('comparison-bar-chart-impl.tsx')

    // The chart box keeps its responsive height classes; overflow-hidden (added for the
    // edge axis labels, spec 0268) does not change the sizing contract.
    expect(impl).toMatch(/className="h-48 w-full overflow-hidden sm:h-64"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })
})
