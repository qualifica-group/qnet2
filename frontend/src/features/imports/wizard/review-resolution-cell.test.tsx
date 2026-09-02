import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewResolutionCell, type ReviewResolutionCellParams } from '@/features/imports/wizard/review-resolution-cell'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0036 AC-008: a `duplicate` row shows the matched anagrafica's name
 * (with a lead-in-campaign indicator when `duplicate_meta.lead_id` is set)
 * and a skip/create/update select; every other row renders an em dash
 * instead. Shape updated by spec 0041 BR-3/AC-060 (`registry_*`, not `referent_*`).
 */

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'duplicate',
    is_edited: false,
    duplicate_of_id: 5,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    duplicate_meta: {
      registry_id: 5,
      registry_name: 'Mario Rossi',
      lead_id: null,
      matched_on: ['email'],
    },
    resolution: null,
    values: { email: 'mario@example.com' },
    messages: [],
    ...overrides,
  }
}

function renderCell(props: Partial<ReviewResolutionCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onResolve = vi.fn()
  render(
    <ReviewResolutionCell
      {...({ data: rowItem(), node, onResolve, ...props } as ReviewResolutionCellParams & ICellRendererParams)}
    />,
  )
  return { node, onResolve }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ReviewResolutionCell', () => {
  it('renders an em dash for a non-duplicate row', () => {
    renderCell({ data: rowItem({ status: 'valid', duplicate_meta: null }) })
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('renders an em dash for a duplicate row whose match was cleared (duplicate_meta null)', () => {
    renderCell({ data: rowItem({ duplicate_meta: null }) })
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('shows the matched anagrafica name and the resolution select for a duplicate row', () => {
    renderCell()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Resolution' })).toBeInTheDocument()
  })

  it('shows the lead-in-campaign indicator only when duplicate_meta.lead_id is set', () => {
    const { container: withoutLead } = render(
      <ReviewResolutionCell
        {...({ data: rowItem(), node: { setData: vi.fn() } } as unknown as ReviewResolutionCellParams &
          ICellRendererParams)}
      />,
    )
    expect(withoutLead.querySelector('[title="This registry already has a lead in the selected campaign."]')).toBeNull()

    const dataWithLead = rowItem({
      duplicate_meta: { registry_id: 5, registry_name: 'Mario Rossi', lead_id: 42, matched_on: ['email'] },
    })
    const { container: withLead } = render(
      <ReviewResolutionCell
        {...({ data: dataWithLead, node: { setData: vi.fn() } } as unknown as ReviewResolutionCellParams &
          ICellRendererParams)}
      />,
    )
    expect(withLead.querySelector('[title="This registry already has a lead in the selected campaign."]')).not.toBeNull()
  })

  it('reflects the row current resolution as the select value', () => {
    renderCell({ data: rowItem({ resolution: 'update' }) })
    expect(screen.getByRole('combobox')).toHaveTextContent('Update existing')
  })

  it('disables the select and omits onResolve wiring in readOnly mode', () => {
    renderCell({ readOnly: true })
    expect(screen.getByRole('combobox')).toBeDisabled()
  })
})
