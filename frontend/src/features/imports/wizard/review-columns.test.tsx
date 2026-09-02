import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewMessagesCell,
  ReviewStatusCell,
  buildReviewColumnDefs,
  reviewValueKeyOf,
} from '@/features/imports/wizard/review-columns'
import { ReviewGeoCell } from '@/features/imports/wizard/review-geo-editor'
import { ReviewOperatorCell } from '@/features/imports/wizard/review-operator-editor'
import { ReviewProductsCell } from '@/features/imports/wizard/review-products-editor'
import { ReviewResolutionCell } from '@/features/imports/wizard/review-resolution-cell'
import { ReviewSiteCell } from '@/features/imports/wizard/review-site-editor'
import type { ImportRunDetail, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-023: the review grid's columns are derived client-side from
 * the run's frozen mapping — no `columns` endpoint exists for the review
 * domain (unlike the generic SSRM tables).
 */

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 3,
    valid_rows: 2,
    warning_rows: 1,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Email', name: 'Email', index: 0, duplicate: false },
      { key: 'Phone', name: 'Phone', index: 1, duplicate: false },
      { key: 'Notes column', name: 'Notes column', index: 2, duplicate: false },
    ],
    column_mapping: { Email: 'email', Phone: '__ignore__', 'Notes column': '__extra__' },
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [
      { id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' },
      { id: 'phone', label: 'Phone', required: false, group: 'contact', type: 'string' },
    ],
    global_fields: [],
    dedup_modes: ['create_new'],
    review_fields: [{ id: 'email', label: 'Email' }],
    ...overrides,
  }
}

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'warning',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: { email: 'mario@example.com' },
    messages: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildReviewColumnDefs', () => {
  it('builds row_number/status as read-only, one editable column per mapped field, one per extra column, and a trailing messages column', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    const colIds = colDefs.map((col) => col.colId)

    expect(colIds).toEqual([
      'row_number',
      'status',
      'resolution',
      'operator',
      'site',
      'products',
      'field:email',
      'extra:Notes column',
      'messages',
    ])
    expect(colDefs.find((col) => col.colId === 'row_number')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'status')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'resolution')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'field:email')?.editable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'extra:Notes column')?.editable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'messages')?.editable).toBe(false)
  })

  it('forces every value column non-editable in readOnly mode (spec 0034 AC-013)', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), true)

    expect(colDefs.find((col) => col.colId === 'field:email')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'extra:Notes column')?.editable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'field:email')?.cellEditor).toBeUndefined()
  })

  it('leaves the ignored column (Phone) out of the grid entirely', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    expect(colDefs.some((col) => col.colId === 'field:phone')).toBe(false)
  })

  it('only allows sorting on the backend allow-list (row_number, status, mapped field ids)', () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    expect(colDefs.find((col) => col.colId === 'field:email')?.sortable).toBe(true)
    expect(colDefs.find((col) => col.colId === 'extra:Notes column')?.sortable).toBe(false)
    expect(colDefs.find((col) => col.colId === 'messages')?.sortable).toBe(false)
  })

  it('builds editable columns from review_fields (the final persisted fields), not from the mapped input columns', () => {
    // delta D-2026-07-15-placeholder-review-fields: the input `full_name`
    // column mapping must not surface as an editable column; only the
    // final persisted `first_name`/`last_name` review fields do.
    const run = baseRun({
      column_mapping: { 'Full name': 'full_name' },
      fields: [{ id: 'full_name', label: 'Full name', required: false, group: 'contact', type: 'string' }],
      review_fields: [
        { id: 'first_name', label: 'First name' },
        { id: 'last_name', label: 'Last name' },
      ],
    })
    const colDefs = buildReviewColumnDefs(run, i18n.t.bind(i18n))
    const colIds = colDefs.map((col) => col.colId)

    expect(colIds).toEqual([
      'row_number',
      'status',
      'resolution',
      'operator',
      'site',
      'products',
      'field:first_name',
      'field:last_name',
      'messages',
    ])
    expect(colIds).not.toContain('field:full_name')
  })

  it('falls back to the legacy mapped-fields derivation when review_fields is absent or empty', () => {
    const withoutReviewFields = baseRun({ review_fields: undefined })
    const withEmptyReviewFields = baseRun({ review_fields: [] })

    for (const run of [withoutReviewFields, withEmptyReviewFields]) {
      const colIds = buildReviewColumnDefs(run, i18n.t.bind(i18n)).map((col) => col.colId)
      expect(colIds).toEqual([
        'row_number',
        'status',
        'resolution',
        'operator',
        'site',
        'products',
        'field:email',
        'extra:Notes column',
        'messages',
      ])
    }
  })

  it('wires the resolution column to ReviewResolutionCell, disabling onResolve in readOnly mode (spec 0036 AC-008)', () => {
    const onResolve = vi.fn()

    const resolutionCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), false, onResolve).find(
      (col) => col.colId === 'resolution',
    )
    expect(resolutionCol?.cellRenderer).toBe(ReviewResolutionCell)
    expect(resolutionCol?.cellRendererParams).toEqual({ onResolve, readOnly: false })

    const readOnlyResolutionCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), true, onResolve).find(
      (col) => col.colId === 'resolution',
    )
    expect(readOnlyResolutionCol?.cellRendererParams).toEqual({ onResolve: undefined, readOnly: true })
  })

  it('wires the 4 geo review fields to ReviewGeoCell instead of the text editor (spec 0038 AC-010)', () => {
    const run = baseRun({
      fields: [{ id: 'country', label: 'Country', required: false, group: 'geo', type: 'string' }],
      review_fields: [
        { id: 'country', label: 'Country' },
        { id: 'region', label: 'Region' },
        { id: 'province', label: 'Province' },
        { id: 'city', label: 'City' },
      ],
    })
    const colDefs = buildReviewColumnDefs(run, i18n.t.bind(i18n))

    for (const geoField of ['country', 'region', 'province', 'city']) {
      const col = colDefs.find((c) => c.colId === `field:${geoField}`)
      expect(col?.editable).toBe(false)
      expect(col?.cellEditor).toBeUndefined()
      expect(col?.cellRenderer).toBe(ReviewGeoCell)
      expect(col?.cellRendererParams).toEqual({ readOnly: false })
    }
  })

  it('keeps the geo columns non-editable and disables their popup in readOnly mode (spec 0038 AC-013)', () => {
    const run = baseRun({
      fields: [{ id: 'country', label: 'Country', required: false, group: 'geo', type: 'string' }],
      review_fields: [{ id: 'country', label: 'Country' }],
    })
    const colDefs = buildReviewColumnDefs(run, i18n.t.bind(i18n), true)
    const col = colDefs.find((c) => c.colId === 'field:country')

    expect(col?.editable).toBe(false)
    expect(col?.cellRendererParams).toEqual({ readOnly: true })
  })

  it('wires the operator column to ReviewOperatorCell, non-editable and disabled in readOnly mode', () => {
    const operatorCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n)).find(
      (col) => col.colId === 'operator',
    )
    expect(operatorCol?.editable).toBe(false)
    expect(operatorCol?.cellRenderer).toBe(ReviewOperatorCell)
    expect(operatorCol?.cellRendererParams).toEqual({ readOnly: false })

    const readOnlyOperatorCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), true).find(
      (col) => col.colId === 'operator',
    )
    expect(readOnlyOperatorCol?.cellRendererParams).toEqual({ readOnly: true })
  })

  it('wires the site column to ReviewSiteCell, non-editable and disabled in readOnly mode', () => {
    const siteCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n)).find((col) => col.colId === 'site')
    expect(siteCol?.editable).toBe(false)
    expect(siteCol?.cellRenderer).toBe(ReviewSiteCell)
    expect(siteCol?.cellRendererParams).toEqual({ readOnly: false })

    const readOnlySiteCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), true).find(
      (col) => col.colId === 'site',
    )
    expect(readOnlySiteCol?.cellRendererParams).toEqual({ readOnly: true })
  })

  it('wires the products column to ReviewProductsCell, non-editable and disabled in readOnly mode (spec 0094 AC-055)', () => {
    const productsCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n)).find((col) => col.colId === 'products')
    expect(productsCol?.editable).toBe(false)
    expect(productsCol?.cellRenderer).toBe(ReviewProductsCell)
    expect(productsCol?.cellRendererParams).toEqual({ readOnly: false })

    const readOnlyProductsCol = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n), true).find(
      (col) => col.colId === 'products',
    )
    expect(readOnlyProductsCol?.cellRendererParams).toEqual({ readOnly: true })
  })

  it("the mapped field column's valueGetter/valueSetter read and write `values` by field id", () => {
    const colDefs = buildReviewColumnDefs(baseRun(), i18n.t.bind(i18n))
    const emailColumn = colDefs.find((col) => col.colId === 'field:email')!
    const valueGetter = emailColumn.valueGetter as (params: unknown) => unknown
    const valueSetter = emailColumn.valueSetter as (params: unknown) => boolean
    const data = rowItem({ values: { email: 'old@example.com' } })

    expect(valueGetter({ data })).toBe('old@example.com')

    const setterResult = valueSetter({ data, oldValue: 'old@example.com', newValue: 'new@example.com' })
    expect(setterResult).toBe(true)
    expect(data.values.email).toBe('new@example.com')
  })
})

describe('reviewValueKeyOf', () => {
  it('resolves a mapped-field colId to its field id', () => {
    expect(reviewValueKeyOf('field:email')).toBe('email')
  })

  it('resolves an extra-column colId to its original column name', () => {
    expect(reviewValueKeyOf('extra:Notes column')).toBe('Notes column')
  })

  it('returns null for a service column', () => {
    expect(reviewValueKeyOf('status')).toBeNull()
    expect(reviewValueKeyOf('row_number')).toBeNull()
    expect(reviewValueKeyOf('messages')).toBeNull()
  })
})

describe('ReviewStatusCell', () => {
  it('renders the translated status badge', () => {
    render(<ReviewStatusCell {...({ data: rowItem({ status: 'error' }) } as ICellRendererParams)} />)
    expect(screen.getByText('Error')).toBeInTheDocument()
  })

  it('marks an edited row with a titled indicator', () => {
    const { container } = render(
      <ReviewStatusCell {...({ data: rowItem({ status: 'valid', is_edited: true }) } as ICellRendererParams)} />,
    )
    expect(container.querySelector('[title="Edited"]')).not.toBeNull()
  })
})

describe('ReviewMessagesCell', () => {
  it('renders an em dash when there are no messages', () => {
    render(<ReviewMessagesCell {...({ data: rowItem({ messages: [] }) } as ICellRendererParams)} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('joins the row messages', () => {
    render(
      <ReviewMessagesCell
        {...({ data: rowItem({ messages: ['Email format guessed', 'Low-confidence name split'] }) } as ICellRendererParams)}
      />,
    )
    expect(screen.getByText('Email format guessed · Low-confidence name split')).toBeInTheDocument()
  })
})
