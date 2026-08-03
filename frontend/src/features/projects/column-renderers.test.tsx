import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { projectColumnRenderers } from '@/features/projects/column-renderers'

function renderCell(columnId: string, value: unknown) {
  const renderer = projectColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data: {} } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('projectColumnRenderers.code', () => {
  it('renders the code inside a badge', () => {
    renderCell('code', 'PRJ-001')
    expect(screen.getByText('PRJ-001')).toBeInTheDocument()
  })
})

describe('projectColumnRenderers.pipeline_status', () => {
  it('renders the status name as a badge', () => {
    renderCell('pipeline_status', { id: 1, name: 'Active', color: 'green' })
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('renders an em dash when unset', () => {
    renderCell('pipeline_status', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('projectColumnRenderers.total_budget', () => {
  it('formats a decimal string amount', () => {
    renderCell('total_budget', '1234.50')
    expect(screen.getByText('1,234.50')).toBeInTheDocument()
  })

  it('renders an em dash when null', () => {
    renderCell('total_budget', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('projectColumnRenderers date columns', () => {
  it('formats start_date without a time part', () => {
    renderCell('start_date', '2026-03-15')
    expect(screen.getByText('15/03/2026')).toBeInTheDocument()
  })

  it('renders an em dash for an empty end_date', () => {
    renderCell('end_date', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('projectColumnRenderers geo columns (spec 0027)', () => {
  it.each(['country', 'province', 'city'])('renders the %s relation name', (columnId) => {
    renderCell(columnId, { id: 1, name: 'Lombardy' })
    expect(screen.getByText('Lombardy')).toBeInTheDocument()
  })

  it.each(['country', 'province', 'city'])('renders an em dash when %s is unset', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('renders the geo_scope column as its scope badge, without a place', () => {
    renderCell('geo_scope', 'province')
    expect(screen.getByText('Provincial')).toBeInTheDocument()
  })

  it('renders an em dash when geo_scope is null (no geo at all)', () => {
    renderCell('geo_scope', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
