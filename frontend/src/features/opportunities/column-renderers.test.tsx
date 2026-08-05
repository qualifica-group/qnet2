import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'

/**
 * Spec 0040/0043: the opportunities grid renders the shared rich cells
 * (relation + icon, colored status pill, money, progress bar, avatars). The
 * user columns (`supervisor`/`managers`) are covered by `user-cell.test.tsx`;
 * here we assert the domain wiring and the domain-local cells (probability bar,
 * aggregated names).
 */

function renderCell(columnId: string, value: unknown, data: Record<string, unknown> = {}) {
  const renderer = opportunityColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('opportunityColumnRenderers relation columns', () => {
  it.each(['registry', 'referent', 'commercial', 'source'])('renders the %s relation name with a kind icon', (columnId) => {
    const { container } = renderCell(columnId, { id: 1, name: 'Acme Spa' })
    expect(screen.getByText('Acme Spa')).toBeInTheDocument()
    // The leading lucide icon names the relation kind.
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it.each(['registry', 'referent', 'commercial', 'source'])('renders an em dash for %s when unset', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('opportunityColumnRenderers.status (spec 0082)', () => {
  it('renders a single computed status as a colored badge', () => {
    const { container } = renderCell('status', {
      source: 'quotes',
      distinct_count: 1,
      entries: [{ id: 1, name: 'Bozza', color: 'green', group: 'open', count: 3 }],
    })
    expect(screen.getByText('Bozza')).toBeInTheDocument()
    expect(container.querySelector('.bg-green-100')).not.toBeNull()
  })

  it('renders "N stati" with the per-status breakdown as its accessible name', () => {
    renderCell('status', {
      source: 'quotes',
      distinct_count: 2,
      entries: [
        { id: 1, name: 'In lavorazione', color: 'blue', group: 'open', count: 1 },
        { id: 2, name: 'In corso', color: 'amber', group: 'pending', count: 1 },
      ],
    })
    expect(screen.getByText('2 statuses')).toBeInTheDocument()
    expect(screen.getByLabelText('1 In lavorazione, 1 In corso')).toBeInTheDocument()
  })

  it('renders an em dash when no status resolves', () => {
    renderCell('status', { source: 'workflow', distinct_count: 0, entries: [] })
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('opportunityColumnRenderers aggregated name columns', () => {
  it.each(['product_category', 'business_function'])('renders the %s comma-joined names', (columnId) => {
    renderCell(columnId, 'Cloud, On-Prem')
    expect(screen.getByText('Cloud, On-Prem')).toBeInTheDocument()
  })

  it.each(['product_category', 'business_function'])('renders an em dash for %s when empty', (columnId) => {
    renderCell(columnId, '')
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('opportunityColumnRenderers.estimated_value', () => {
  it('renders a formatted decimal value', () => {
    renderCell('estimated_value', '1250.00')
    expect(screen.queryByText('—')).toBeNull()
  })

  it('renders an em dash when null', () => {
    renderCell('estimated_value', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('opportunityColumnRenderers.success_probability', () => {
  it('renders the percentage text and the (decorative, aria-hidden) bar', () => {
    const { container } = renderCell('success_probability', 45)
    expect(screen.getByText('45%')).toBeInTheDocument()
    // The bar is aria-hidden — the percentage text is the accessible value.
    expect(container.querySelector('[data-slot="progress"]')).not.toBeNull()
  })

  it('rounds and clamps out-of-range values into 0..100', () => {
    renderCell('success_probability', 150)
    expect(screen.getByText('100%')).toBeInTheDocument()
  })

  it.each([
    [10, 'bg-red-500'],
    [45, 'bg-amber-500'],
    [80, 'bg-green-600'],
  ])('tones the bar by band (%i%% -> %s)', (value, toneClass) => {
    const { container } = renderCell('success_probability', value)
    expect(container.querySelector(`.${toneClass}`)).not.toBeNull()
  })

  it('renders an em dash when null', () => {
    renderCell('success_probability', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('opportunityColumnRenderers wiring', () => {
  it('maps the person columns to the shared user cells', () => {
    // Rendering is covered by user-cell.test.tsx (they need the detail-sheet
    // context); here we assert the domain registers a renderer for each.
    expect(opportunityColumnRenderers.supervisor).toBeTypeOf('function')
    expect(opportunityColumnRenderers.managers).toBeTypeOf('function')
    expect(opportunityColumnRenderers.created_at).toBeTypeOf('function')
  })
})
