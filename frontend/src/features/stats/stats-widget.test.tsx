import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatStatValue } from '@/features/stats/format-stat-value'
import { StatsWidgetView } from '@/features/stats/stats-widget'
import type { DistributionWidget, StatWidget, StatsWidget } from '@/features/stats/types'

/**
 * Spec 0026 — the widget renderer maps each backend-described widget onto a
 * design-system component. It owns the only formatting rules of the panel
 * (AC-005/AC-012). The spec 0152 `chart`/`tone` dispatch (AC-002/AC-003) is
 * covered in `stats-widget-chart-dispatch.test.tsx`, which stubs the
 * design-system renderings so it exercises only the selection/data-prep
 * logic — this file keeps testing the real, default (`bars`/`area`) markup.
 */

const LEADS_TOTAL: StatWidget = {
  type: 'stat',
  key: 'total',
  label: 'leads.stats.total',
  value: 1280,
  format: 'number',
  subtitle: null,
  icon: 'users',
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('formatStatValue', () => {
  it('renders a missing percent as a placeholder, never as 0%', () => {
    expect(formatStatValue(null, 'percent', 'en')).toBe('—')
  })

  it('formats a percent that the backend already computed on the 0..100 scale', () => {
    expect(formatStatValue(32.5, 'percent', 'en')).toBe('32.5%')
  })

  it('formats a currency from the plain number the backend sends', () => {
    expect(formatStatValue(1500, 'currency', 'en')).toContain('1,500')
  })

  it('groups plain numbers with the active locale separators', () => {
    expect(formatStatValue(1280, 'number', 'en')).toBe('1,280')
  })
})

describe('StatsWidgetView', () => {
  it('renders a stat widget with its translated label and formatted value', () => {
    render(<StatsWidgetView widget={LEADS_TOTAL} />)

    expect(screen.getByText('Leads')).toBeInTheDocument()
    expect(screen.getByText('1,280')).toBeInTheDocument()
  })

  it('renders a subtitle interpolated with its count', () => {
    render(
      <StatsWidgetView
        widget={{
          ...LEADS_TOTAL,
          subtitle: { key: 'leads.stats.assigned', count: 42 },
        }}
      />,
    )

    expect(screen.getByText('Assigned to an operator')).toBeInTheDocument()
  })

  it('pluralizes a subtitle key with i18next _one/_other (delta: subtitle keys)', () => {
    render(
      <StatsWidgetView
        widget={{
          ...LEADS_TOTAL,
          label: 'companies.stats.total',
          subtitle: { key: 'companies.stats.withVatNumberSubtitle', count: 1 },
        }}
      />,
    )

    expect(screen.getByText('1 with VAT number')).toBeInTheDocument()
  })

  it('picks the plural form for a count other than one', () => {
    render(
      <StatsWidgetView
        widget={{
          ...LEADS_TOTAL,
          label: 'registries.stats.suppliers',
          subtitle: { key: 'registries.stats.suppliersSubtitle', count: 5 },
        }}
      />,
    )

    expect(screen.getByText('5 suppliers')).toBeInTheDocument()
  })

  it('renders no icon when the backend sends a name outside the allow-list', () => {
    const { container } = render(<StatsWidgetView widget={{ ...LEADS_TOTAL, icon: 'not-a-icon' }} />)

    expect(container.querySelector('svg')).toBeNull()
  })

  it('renders a distribution as accessible meters, with the domain labels verbatim', () => {
    const widget: DistributionWidget = {
      type: 'distribution',
      key: 'by_source',
      label: 'leads.stats.bySource',
      items: [
        { key: 'web', label: 'Web', value: 51, color: 'teal' },
        { key: 'phone', label: 'Telefono', value: 49, color: null },
      ],
      total: 100,
    }

    render(<StatsWidgetView widget={widget} />)

    expect(screen.getByText('By source')).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: 'Web' })).toHaveAttribute('aria-valuenow', '51')
    // Domain text from the database is shown as-is, never translated.
    expect(screen.getByRole('meter', { name: 'Telefono' })).toBeInTheDocument()
  })

  it('resolves a distribution color TOKEN (not a literal CSS color) to a theme variable', () => {
    // "slate"/"amber" are DB color tokens (e.g. `pipeline_statuses.color`),
    // not valid standalone CSS colors — the fix under test.
    const widget: DistributionWidget = {
      type: 'distribution',
      key: 'by_status',
      label: 'projects.stats.byStatus',
      items: [
        { key: 'active', label: 'Active', value: 10, color: 'teal' },
        { key: 'draft', label: 'Draft', value: 5, color: 'slate' },
        { key: 'onhold', label: 'On hold', value: 3, color: 'amber' },
        { key: 'unknown', label: 'Unknown', value: 2, color: 'not-a-real-token' },
        { key: 'none', label: 'None', value: 1, color: null },
      ],
      total: 21,
    }

    render(<StatsWidgetView widget={widget} />)

    const bar = (name: string) =>
      screen.getByRole('meter', { name }).querySelector('div') as HTMLElement

    expect(bar('Active')).toHaveStyle({ backgroundColor: 'var(--color-teal-500)' })
    expect(bar('Draft')).toHaveStyle({ backgroundColor: 'var(--color-slate-500)' })
    expect(bar('On hold')).toHaveStyle({ backgroundColor: 'var(--color-amber-500)' })
    // Unrecognized token or null: never injected raw, falls back to the
    // default bar color instead (never crashes, never an invalid CSS value).
    expect(bar('Unknown')).toHaveStyle({ backgroundColor: 'var(--chart-1)' })
    expect(bar('None')).toHaveStyle({ backgroundColor: 'var(--chart-1)' })
  })

  it('renders a trend widget with its translated title', () => {
    render(
      <StatsWidgetView
        widget={{
          type: 'trend',
          key: 'created_trend',
          label: 'leads.stats.trend',
          points: [{ label: '2026-02', value: 12 }],
          format: 'number',
        }}
      />,
    )

    expect(screen.getByText('New leads per month')).toBeInTheDocument()
  })

  it('ignores a widget whose type the deployed frontend does not know (AC-012)', () => {
    const unknown = { type: 'heatmap', key: 'x', label: 'leads.stats.total' } as unknown as StatsWidget

    const { container } = render(<StatsWidgetView widget={unknown} />)

    expect(container).toBeEmptyDOMElement()
  })
})
