import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { campaignColumnRenderers } from '@/features/campaigns/column-renderers'

/**
 * Spec 0027 BR-5/D-2/AC-013: the campaigns table's MERGED geo columns
 * (`country`/`state`/`province`/`city`/`geo_scope`), added once
 * `CampaignColumnCatalog` registered them (backend lane landed green).
 */

function renderCell(columnId: string, value: unknown, data: Record<string, unknown> = {}) {
  const renderer = campaignColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('campaignColumnRenderers.code', () => {
  it('renders the code inside a badge', () => {
    renderCell('code', 'CMP-001')
    expect(screen.getByText('CMP-001')).toBeInTheDocument()
  })
})

describe('campaignColumnRenderers.pipeline_status', () => {
  it('renders the effective status as a colored badge', () => {
    const { container } = renderCell('pipeline_status', { id: 1, name: 'On Track', color: 'green' })
    expect(screen.getByText('On Track')).toBeInTheDocument()
    expect(container.querySelector('.bg-green-100')).not.toBeNull()
  })

  it('renders an em dash when unset', () => {
    renderCell('pipeline_status', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('campaignColumnRenderers geo relation columns (country/state/province/city)', () => {
  it.each(['country', 'state', 'province', 'city'])('renders the %s relation name', (columnId) => {
    renderCell(columnId, { id: 1, name: 'Lombardy' })
    expect(screen.getByText('Lombardy')).toBeInTheDocument()
  })

  it.each(['country', 'state', 'province', 'city'])('renders an em dash for %s when unset', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('campaignColumnRenderers.source', () => {
  it('renders the Fonte relation name', () => {
    renderCell('source', { id: 4, name: 'Referral' })
    expect(screen.getByText('Referral')).toBeInTheDocument()
  })

  it('renders an em dash when unset', () => {
    renderCell('source', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('campaignColumnRenderers.geo_scope', () => {
  it('renders the scope badge with the matching place name picked from sibling columns', () => {
    renderCell('geo_scope', 'city', {
      country: { id: 1, name: 'Italy' },
      state: { id: 2, name: 'Lombardy' },
      province: { id: 3, name: 'Milan' },
      city: { id: 4, name: 'Milan' },
    })
    expect(screen.getByText('City')).toBeInTheDocument()
    expect(screen.getByText('Milan')).toBeInTheDocument()
  })

  it('picks the country name when the scope is country-level, ignoring unset siblings', () => {
    renderCell('geo_scope', 'country', {
      country: { id: 1, name: 'Italy' },
      state: null,
      province: null,
      city: null,
    })
    expect(screen.getByText('National')).toBeInTheDocument()
    expect(screen.getByText('Italy')).toBeInTheDocument()
  })

  it('renders an em dash when there is no geo at all', () => {
    renderCell('geo_scope', null, { country: null, state: null, province: null, city: null })
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
