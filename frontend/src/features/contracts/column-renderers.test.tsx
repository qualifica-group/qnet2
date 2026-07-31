import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { contractColumnRenderers } from '@/features/contracts/column-renderers'

/**
 * Spec 0072 `data_contract` (AC-029, AC-049): the contracts grid renders the
 * shared rich cells plus the D-4 alert indicator, icon+text (never color
 * alone).
 */

function renderCell(columnId: string, value: unknown, data: Record<string, unknown> = {}) {
  const renderer = contractColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `en-contracts.ts`/`it-contracts.ts` are not yet merged into `en.ts`/`it.ts`
  // (MT-09's job): registered here at runtime, mirrors the wave-1
  // `document-layouts` pre-merge test pattern.
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

describe('contractColumnRenderers.code', () => {
  it('renders the code as a badge', () => {
    renderCell('code', 'QUO-0003')
    expect(screen.getByText('QUO-0003')).toBeInTheDocument()
  })
})

describe('contractColumnRenderers relation columns', () => {
  it.each(['registry', 'opportunity', 'commercial', 'reporter'])('renders the %s relation name with a kind icon', (columnId) => {
    const { container } = renderCell(columnId, { id: 1, name: 'Acme Spa' })
    expect(screen.getByText('Acme Spa')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it.each(['registry', 'opportunity', 'commercial', 'reporter'])('renders an em dash for %s when unset', (columnId) => {
    renderCell(columnId, null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('contractColumnRenderers.contract_status', () => {
  it('renders the status as a colored badge (shared StatusBadgeCell)', () => {
    renderCell('contract_status', { name: 'Da validare', color: 'green' })
    expect(screen.getByText('Da validare')).toBeInTheDocument()
  })
})

describe('contractColumnRenderers.alert (AC-049)', () => {
  it('renders an icon AND text for "expiring" — never color alone', () => {
    const { container } = renderCell('alert', 'expiring')
    expect(screen.getByText('Expiring soon')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it('renders an icon AND text for "renewal_due"', () => {
    const { container } = renderCell('alert', 'renewal_due')
    expect(screen.getByText('Renewal due')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it('renders nothing when there is no alert', () => {
    const { container } = renderCell('alert', null)
    expect(container.textContent).toBe('')
  })
})
