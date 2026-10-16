import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as React from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { TrendLineChart, type TrendLineSeries } from '@/components/ui/trend-line-chart'

/**
 * Spec 0270 AC-016, mirrors `comparison-bar-chart.test.tsx`: one line per series
 * with its legend entry, sr-only values per month, lazy recharts boundary,
 * placeholder when empty, theme tokens only.
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

const TITLE = 'Cash flow trend'
const MONTHS = ['Jan', 'Feb', 'Mar']
const SERIES: TrendLineSeries[] = [
  { key: 'in', label: 'Inflows', values: [100, 200, -50] },
  { key: 'out', label: 'Outflows', values: [80, null, 120] },
]

const readSource = (file: string) => readFileSync(path.resolve(__dirname, file), 'utf8')

describe('TrendLineChart', () => {
  // Must run first: once the lazy import resolved, the fallback is never shown again.
  it('shows the skeleton fallback until the lazy chart chunk resolves', async () => {
    const { container } = render(<TrendLineChart title={TITLE} months={MONTHS} series={SERIES} />)

    expect(container.querySelector('[data-slot="skeleton"]')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()

    await screen.findByRole('list', { name: TITLE })

    expect(container.querySelector('svg')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('renders one line per series, each with a legend entry', async () => {
    const { container } = render(<TrendLineChart title={TITLE} months={MONTHS} series={SERIES} />)

    await screen.findByRole('list', { name: TITLE })

    expect(container.querySelectorAll('.recharts-line')).toHaveLength(2)
    const legend = container.querySelector('.recharts-legend-wrapper') as HTMLElement
    expect(within(legend).getByText('Inflows')).toBeInTheDocument()
    expect(within(legend).getByText('Outflows')).toBeInTheDocument()
  })

  it('renders the title as a heading and an image-role chart naming every series', async () => {
    render(<TrendLineChart title={TITLE} months={MONTHS} series={SERIES} />)

    expect(screen.getByRole('heading', { name: TITLE, level: 3 })).toBeInTheDocument()
    expect(await screen.findByRole('img', { name: /Cash flow trend.*Inflows.*Outflows/ })).toBeInTheDocument()
  })

  it('renders the title at the requested heading level', () => {
    render(<TrendLineChart title={TITLE} months={[]} series={[]} headingLevel={4} />)

    expect(screen.getByRole('heading', { name: TITLE, level: 4 })).toBeInTheDocument()
  })

  it('exposes the values of every series for each month, gaps and negatives included', async () => {
    render(
      <TrendLineChart
        title={TITLE}
        months={MONTHS}
        series={SERIES}
        formatValue={(value) => `${value} EUR`}
      />,
    )

    const list = await screen.findByRole('list', { name: TITLE })

    expect(within(list).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Jan: Inflows 100 EUR, Outflows 80 EUR',
      'Feb: Inflows 200 EUR, Outflows —',
      'Mar: Inflows -50 EUR, Outflows 120 EUR',
    ])
  })

  it.each([
    ['no series', MONTHS, [] as TrendLineSeries[]],
    ['no months', [] as string[], SERIES],
  ])('shows a discreet placeholder and loads no chart with %s', (_case, months, series) => {
    const { container } = render(
      <TrendLineChart title={TITLE} months={months} series={series} emptyLabel="No data" />,
    )

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(container.querySelector('svg')).toBeNull()
    expect(container.querySelector('[data-slot="skeleton"]')).toBeNull()
  })

  it('keeps recharts out of the eager module so it lands in a separate chunk', () => {
    const wrapper = readSource('trend-line-chart.tsx')
    const impl = readSource('trend-line-chart-impl.tsx')

    expect(wrapper).not.toMatch(/from ["']recharts["']/)
    expect(wrapper).toMatch(
      /React\.lazy\(\s*\(\)\s*=>\s*import\(["']@\/components\/ui\/trend-line-chart-impl["']\)/,
    )
    expect(impl).toMatch(/from ["']recharts["']/)
    expect(impl).not.toMatch(
      /import\s+\{[^}]*TrendLineChart[^}]*\}\s+from ["']@\/components\/ui\/trend-line-chart["']/,
    )
  })

  it('takes series colors from the theme tokens, never a hex literal', () => {
    const impl = readSource('trend-line-chart-impl.tsx')

    expect(impl).toMatch(/CHART_SERIES_COLORS\[index % CHART_SERIES_COLORS\.length\]/)
    expect(impl).not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
  })

  it('sizes the chart with responsive classes instead of a fixed pixel height', () => {
    const impl = readSource('trend-line-chart-impl.tsx')

    expect(impl).toMatch(/className="h-48 w-full overflow-hidden sm:h-64"/)
    expect(impl).not.toMatch(/h-\[\d+px\]/)
  })
})
