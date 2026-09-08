import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { ReportBarChart, type ReportBarChartPoint } from '@/components/ui/report-bar-chart'

/**
 * Spec 0107 AC-046/AC-047, mirrors `stat-chart.test.tsx` (spec 0026 AC-013):
 * the recharts-backed bar chart must stay behind a lazy boundary, and every
 * bar must have a `sr-only` textual equivalent since the chart area itself
 * is `aria-hidden`.
 */

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

const POINTS: ReportBarChartPoint[] = [
  { label: 'GOL', value: 12 },
  { label: 'Consulenza', value: 0 },
  { label: 'APL', value: 7 },
]

const readSource = (file: string) => readFileSync(path.resolve(__dirname, file), 'utf8')

describe('ReportBarChart', () => {
  // Must run first: once any test has resolved the lazy import, React renders the
  // cached chunk synchronously and the Suspense fallback is never shown again.
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(<ReportBarChart title="Requests per category" points={POINTS} />)

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list')

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders the title as a heading', () => {
    render(<ReportBarChart title="Requests per category" points={POINTS} />)

    expect(screen.getByRole('heading', { name: 'Requests per category' })).toBeInTheDocument()
  })

  it('hides the chart area from assistive tech and exposes every point as sr-only text (AC-047)', async () => {
    const { container } = render(<ReportBarChart title="Requests per category" points={POINTS} />)

    const list = await screen.findByRole('list')
    expect(list).toHaveClass('sr-only')

    const items = within(list).getAllByRole('listitem')
    expect(items.map((item) => item.textContent)).toEqual([
      'GOL: 12',
      'Consulenza: 0',
      'APL: 7',
    ])

    const chartArea = container.querySelector('svg')?.closest('[aria-hidden="true"]')
    expect(chartArea).toBeInTheDocument()
  })

  it('applies formatValue to the accessible values', async () => {
    render(
      <ReportBarChart
        title="Budget per operator"
        points={[{ label: 'Mario Rossi', value: 1500 }]}
        formatValue={(value) => `${value} EUR`}
      />,
    )

    const list = await screen.findByRole('list')

    expect(within(list).getByRole('listitem')).toHaveTextContent('Mario Rossi: 1500 EUR')
  })

  it('shows a discreet placeholder and loads no chart when there are no points', () => {
    const { container } = render(
      <ReportBarChart title="Requests per category" points={[]} emptyLabel="No data" />,
    )

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk (AC-046)', () => {
    const wrapper = readSource('report-bar-chart.tsx')
    const impl = readSource('report-bar-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(
      /React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/report-bar-chart-impl["']\)/,
    )
    expect(impl).toMatch(/from ["']recharts["']/)
    // The impl must only be reachable through the lazy boundary above.
    expect(impl).not.toMatch(
      /import\s+\{[^}]*ReportBarChart[^}]*\}\s+from ["']@\/components\/ui\/report-bar-chart["']/,
    )
  })

  it('uses theme CSS variables for color, never a hex literal', () => {
    const impl = readSource('report-bar-chart-impl.tsx')

    expect(impl).toMatch(/var\(--chart-1\)/)
    expect(impl).not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('report-bar-chart-impl.tsx')

    expect(impl).toMatch(/className="h-48 w-full sm:h-64"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })

  it('lays the chart out horizontally (bars, not columns)', () => {
    const impl = readSource('report-bar-chart-impl.tsx')

    expect(impl).toMatch(/layout="vertical"/)
    expect(impl).toMatch(/<Bar\b/)
  })
})
