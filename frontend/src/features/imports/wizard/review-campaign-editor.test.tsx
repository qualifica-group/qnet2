import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewCampaignCell,
  type ReviewCampaignCellParams,
  type ReviewCampaignGridContext,
} from '@/features/imports/wizard/review-campaign-editor'
import { buildReviewColumnDefs } from '@/features/imports/wizard/review-columns'
import type { ImportRunDetail, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0108 AC-035/AC-036: the review grid gains a Campaign column ONLY on a
 * run reading campaigns from a file column; the cell shows the resolved
 * campaign (or flags the unmatched code) and its popup PATCHes the picked
 * campaign through `context.onApplyCampaign`.
 */

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(77)}>
      {value ?? 'none'}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'error',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    campaign_id: null,
    campaign: null,
    values: { campaign_code: 'CMP-9999' },
    messages: [],
    ...overrides,
  }
}

function runDetail(columnMapping: Record<string, string> | null): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 2,
    valid_rows: 1,
    warning_rows: 0,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-09-08T00:00:00Z',
    error_count: 0,
    detected_columns: [],
    column_mapping: columnMapping,
    global_config: null,
    dedup_strategy: 'create_new',
    suggested_mapping: {},
    fields: [],
    global_fields: [],
    dedup_modes: ['create_new'],
    review_fields: [],
  }
}

function renderCell(overrides: Partial<ReviewCampaignCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplyCampaign = vi.fn().mockResolvedValue(undefined)
  const context: ReviewCampaignGridContext = { onApplyCampaign }
  render(
    <ReviewCampaignCell
      {...({
        data: rowItem(),
        node,
        context,
        ...overrides,
      } as unknown as ReviewCampaignCellParams)}
    />,
  )
  return { onApplyCampaign, node }
}

describe('review campaign column', () => {
  it('AC-035: exists only when a file column feeds the campaign', () => {
    const perRow = buildReviewColumnDefs(runDetail({ 'Codice campagna': 'campaign_code' }), i18n.t.bind(i18n))
    const global = buildReviewColumnDefs(runDetail({ Email: 'email' }), i18n.t.bind(i18n))

    expect(perRow.map((column) => column.colId)).toContain('campaign')
    expect(global.map((column) => column.colId)).not.toContain('campaign')
  })

  it('AC-035: shows the resolved campaign as code + name', () => {
    renderCell({ data: rowItem({ campaign_id: 5, campaign: { id: 5, code: 'CMP-0005', name: 'Autumn' } }) })

    expect(screen.getByRole('button', { name: /edit campaign/i })).toHaveTextContent('CMP-0005 — Autumn')
  })

  it('AC-035: shows the unmatched file code on a row whose campaign did not resolve', () => {
    renderCell()

    expect(screen.getByRole('button', { name: /edit campaign/i })).toHaveTextContent('CMP-9999')
  })

  it('AC-036: picking a campaign in the popup applies it through the grid context', async () => {
    const { onApplyCampaign } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: /edit campaign/i }))
    fireEvent.click(await screen.findByRole('button', { name: 'Campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyCampaign).toHaveBeenCalledTimes(1))
    expect(onApplyCampaign.mock.calls[0][1]).toBe(77)
  })

  it('AC-036: "use the file code" applies a null campaign, unpinning the row', async () => {
    const { onApplyCampaign } = renderCell({
      data: rowItem({ campaign_id: 5, campaign: { id: 5, code: 'CMP-0005', name: 'Autumn' } }),
    })

    fireEvent.click(screen.getByRole('button', { name: /edit campaign/i }))
    fireEvent.click(await screen.findByRole('button', { name: 'Use the file code' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyCampaign).toHaveBeenCalledTimes(1))
    expect(onApplyCampaign.mock.calls[0][1]).toBeNull()
  })

  it('AC-036/AC-037: a failing apply keeps the popup open and reports the error in an alert', async () => {
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    const onApplyCampaign = vi.fn().mockRejectedValue(new Error('nope'))
    render(
      <ReviewCampaignCell
        {...({
          data: rowItem(),
          node,
          context: { onApplyCampaign } satisfies ReviewCampaignGridContext,
        } as unknown as ICellRendererParams<ImportRunRowItem>)}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: /edit campaign/i }))
    fireEvent.click(await screen.findByRole('button', { name: 'Campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Apply' })).toBeInTheDocument()
  })

  it('AC-035: the read-only detail view renders plain text with no popup affordance', () => {
    renderCell({
      readOnly: true,
      data: rowItem({ campaign_id: 5, campaign: { id: 5, code: 'CMP-0005', name: 'Autumn' } }),
    })

    expect(screen.queryByRole('button', { name: /edit campaign/i })).not.toBeInTheDocument()
    expect(screen.getByText('CMP-0005 — Autumn')).toBeInTheDocument()
  })
})
