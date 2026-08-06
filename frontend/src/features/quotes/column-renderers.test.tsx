import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'

/**
 * Spec 0065 `data_contract` (tables/quotes/columns): the quotes grid renders
 * the shared rich cells (relation + icon, colored status pill, money,
 * avatar). Here we assert the domain wiring for every custom column.
 */

function renderCell(columnId: string, value: unknown, data: Record<string, unknown> = {}) {
  const renderer = quoteColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('quoteColumnRenderers.code', () => {
  it('renders the code as a badge', () => {
    renderCell('code', 'QUO-0001')
    expect(screen.getByText('QUO-0001')).toBeInTheDocument()
  })

  it('renders an em dash when unset', () => {
    renderCell('code', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('quoteColumnRenderers relation columns', () => {
  it.each(['opportunity', 'commercial', 'reporter'])('renders the %s relation name with a kind icon', (columnId) => {
    const { container } = renderCell(columnId, { id: 1, name: 'Acme Spa' })
    expect(screen.getByText('Acme Spa')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it.each(['opportunity', 'commercial', 'reporter'])('renders an em dash for %s when unset', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('quoteColumnRenderers.quote_workflow_status', () => {
  it('renders the status as a colored badge', () => {
    const { container } = renderCell('quote_workflow_status', { id: 1, name: 'Bozza', color: 'slate' })
    expect(screen.getByText('Bozza')).toBeInTheDocument()
    expect(container.querySelector('.bg-slate-100')).not.toBeNull()
  })

  it('renders an em dash when unset', () => {
    renderCell('quote_workflow_status', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('quoteColumnRenderers money columns', () => {
  it.each(['revenue_net', 'cost_net', 'margin_net'])('renders a formatted decimal value for %s', (columnId) => {
    renderCell(columnId, '1250.00')
    expect(screen.queryByText('—')).toBeNull()
  })

  it.each(['revenue_net', 'cost_net', 'margin_net'])('renders an em dash for %s when null', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('renders a negative margin_net without clamping (AC-043)', () => {
    renderCell('margin_net', '-20.00')
    expect(screen.getByText('-20.00')).toBeInTheDocument()
  })
})

describe('quoteColumnRenderers wiring', () => {
  it('maps supervisor and created_at to the shared cells', () => {
    expect(quoteColumnRenderers.supervisor).toBeTypeOf('function')
    expect(quoteColumnRenderers.created_at).toBeTypeOf('function')
  })
})

/**
 * Bug repro (user report: "la tabella offerte esce ERR su ogni colonna"):
 * `supervisor` is rendered by the SAME shared `UserCell` as Opportunities'
 * supervisor/managers columns. Mounts it with the REAL backend-shaped
 * payloads (both `avatar_url` present and, per the real dev dataset, absent
 * — a quote with no uploaded avatar) to guard against a shape drift going
 * unnoticed because `UserCell` defensively falls back instead of throwing.
 */
describe('quoteColumnRenderers.supervisor', () => {
  it('renders the person name and avatar for a real {id, name, avatar_url} payload', () => {
    renderCell('supervisor', { id: 33, name: 'Mackenzie Stanton', avatar_url: null })
    expect(screen.getByText('Mackenzie Stanton')).toBeInTheDocument()
  })

  it('does not throw for a {id, name} payload missing avatar_url entirely', () => {
    expect(() => renderCell('supervisor', { id: 14, name: 's.r.l. Mazza-Mariani Group' })).not.toThrow()
    expect(screen.getByText('s.r.l. Mazza-Mariani Group')).toBeInTheDocument()
  })

  it('renders an em dash when unset (real dataset: most quotes have no supervisor)', () => {
    renderCell('supervisor', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
