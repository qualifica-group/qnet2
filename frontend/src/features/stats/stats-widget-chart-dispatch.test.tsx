import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { StatsWidgetView } from '@/features/stats/stats-widget'
import type { DistributionWidget, TrendWidget } from '@/features/stats/types'

/**
 * Spec 0152 AC-002/AC-003 — `StatsWidgetView` picks the rendering the
 * backend's `chart`/`tone` fields ask for, and prepares its data correctly
 * (color resolution + "Others" folding for the categorical shapes). Every
 * design-system rendering is stubbed (same pattern as the existing
 * `stat-chart` tests stubbing `recharts`): this file verifies ONLY the
 * selection and the props each rendering receives, never chart markup —
 * that is covered by each component's own test file
 * (`stat-column-chart.test.tsx`, `stat-donut-chart.test.tsx`, etc.).
 */

interface StubItem {
  key: string
  label: string
  color: string
}

vi.mock('@/components/ui/stat-bar-list', () => ({
  StatBarList: ({ title }: { title: string }) => (
    <div role="note" aria-label="bars">
      {title}
    </div>
  ),
}))

vi.mock('@/components/ui/stat-column-chart', () => ({
  StatColumnChart: ({ title, items }: { title: string; items: StubItem[] }) => (
    <div role="note" aria-label="columns">
      {title}
      <ul>
        {items.map((item) => (
          <li key={item.key}>{`${item.label}:${item.color}`}</li>
        ))}
      </ul>
    </div>
  ),
}))

vi.mock('@/components/ui/stat-donut-chart', () => ({
  StatDonutChart: ({ title, items }: { title: string; items: StubItem[] }) => (
    <div role="note" aria-label="donut">
      {title}
      <ul>
        {items.map((item) => (
          <li key={item.key}>{`${item.label}:${item.color}`}</li>
        ))}
      </ul>
    </div>
  ),
}))

vi.mock('@/components/ui/stat-stacked-bar', () => ({
  StatStackedBar: ({ title, items }: { title: string; items: StubItem[] }) => (
    <div role="note" aria-label="stacked">
      {title}
      <ul>
        {items.map((item) => (
          <li key={item.key}>{`${item.label}:${item.color}`}</li>
        ))}
      </ul>
    </div>
  ),
}))

vi.mock('@/components/ui/stat-chart', () => ({
  StatChart: ({ title, variant, tone }: { title: string; variant: string; tone: number }) => (
    <div role="note" aria-label="trend">{`${title}:${variant}:${tone}`}</div>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function distributionWidget(overrides: Partial<DistributionWidget>): DistributionWidget {
  return {
    type: 'distribution',
    key: 'by_status',
    label: 'leads.stats.bySource',
    items: [
      { key: 'a', label: 'A', value: 10, color: null },
      { key: 'b', label: 'B', value: 20, color: null },
    ],
    total: 30,
    ...overrides,
  }
}

function trendWidget(overrides: Partial<TrendWidget>): TrendWidget {
  return {
    type: 'trend',
    key: 'created_trend',
    label: 'leads.stats.trend',
    points: [{ label: '2026-02', value: 12 }],
    format: 'number',
    ...overrides,
  }
}

describe('StatsWidgetView — distribution chart dispatch (AC-002)', () => {
  it('renders StatBarList for chart="bars"', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: 'bars' })} />)
    expect(screen.getByRole('note', { name: 'bars' })).toBeInTheDocument()
  })

  it('renders StatBarList when chart is absent (retro-compatible default)', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: undefined })} />)
    expect(screen.getByRole('note', { name: 'bars' })).toBeInTheDocument()
  })

  it('renders StatBarList when chart is unrecognized', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: 'pie-3d' })} />)
    expect(screen.getByRole('note', { name: 'bars' })).toBeInTheDocument()
  })

  it('renders StatColumnChart for chart="columns"', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: 'columns' })} />)
    expect(screen.getByRole('note', { name: 'columns' })).toBeInTheDocument()
  })

  it('renders StatDonutChart for chart="donut"', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: 'donut' })} />)
    expect(screen.getByRole('note', { name: 'donut' })).toBeInTheDocument()
  })

  it('renders StatStackedBar for chart="stacked"', () => {
    render(<StatsWidgetView widget={distributionWidget({ chart: 'stacked' })} />)
    expect(screen.getByRole('note', { name: 'stacked' })).toBeInTheDocument()
  })
})

describe('StatsWidgetView — distribution color/grouping (AC-003)', () => {
  it('assigns the fixed --chart-1..5 order to items without a DB color token', () => {
    render(
      <StatsWidgetView
        widget={distributionWidget({
          chart: 'donut',
          items: [
            { key: 'a', label: 'A', value: 1, color: null },
            { key: 'b', label: 'B', value: 2, color: null },
          ],
        })}
      />
    )

    const list = within(screen.getByRole('note', { name: 'donut' })).getAllByRole('listitem')
    expect(list.map((item) => item.textContent)).toEqual(['A:var(--chart-1)', 'B:var(--chart-2)'])
  })

  it('keeps a DB color token and folds 7+ items into a single "Others" entry', () => {
    const items = Array.from({ length: 7 }, (_, index) => ({
      key: `s${index}`,
      label: `S${index}`,
      value: index + 1,
      color: index === 0 ? 'teal' : null,
    }))

    render(<StatsWidgetView widget={distributionWidget({ chart: 'stacked', items, total: 28 })} />)

    const rows = within(screen.getByRole('note', { name: 'stacked' })).getAllByRole('listitem')
    // s0 (DB token) + s1..s5 (--chart-1..5, fixed order) + one "Other" bucket for s6.
    expect(rows).toHaveLength(7)
    expect(rows[0].textContent).toBe('S0:var(--color-teal-500)')
    expect(rows.slice(1, 6).map((row) => row.textContent)).toEqual([
      'S1:var(--chart-1)',
      'S2:var(--chart-2)',
      'S3:var(--chart-3)',
      'S4:var(--chart-4)',
      'S5:var(--chart-5)',
    ])
    expect(rows[6].textContent).toContain('Other:')
  })
})

describe('StatsWidgetView — trend chart/tone dispatch (AC-002)', () => {
  it('passes the normalized variant and tone through to StatChart', () => {
    render(<StatsWidgetView widget={trendWidget({ chart: 'line', tone: 4 })} />)
    expect(screen.getByRole('note', { name: 'trend' })).toHaveTextContent('New leads per month:line:4')
  })

  it('defaults to area/tone 1 when chart/tone are absent', () => {
    render(<StatsWidgetView widget={trendWidget({ chart: undefined, tone: undefined })} />)
    expect(screen.getByRole('note', { name: 'trend' })).toHaveTextContent('New leads per month:area:1')
  })

  it('defaults to area/tone 1 when chart/tone are unrecognized', () => {
    render(<StatsWidgetView widget={trendWidget({ chart: 'waterfall', tone: 9 })} />)
    expect(screen.getByRole('note', { name: 'trend' })).toHaveTextContent('New leads per month:area:1')
  })
})
