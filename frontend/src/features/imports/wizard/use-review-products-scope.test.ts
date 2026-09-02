import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useReviewProductsScope } from '@/features/imports/wizard/use-review-products-scope'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0094 AC-055: the review grid's products column needs (a) whether the
 * run carries a global `product_ids` default, purely to gate the cell's
 * "uses default" hint, and (b) the run's campaign EFFECTIVE product-category
 * ids, to scope the popup's picker exactly like `ProductsOfInterestField`.
 */

const useForSelectLabelsMock = vi.fn()

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelectLabels: (args: unknown) => useForSelectLabelsMock(args),
}))

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 1,
    valid_rows: 1,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [],
    column_mapping: {},
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [],
    global_fields: [],
    dedup_modes: [],
    ...overrides,
  }
}

beforeEach(() => {
  useForSelectLabelsMock.mockReset()
  useForSelectLabelsMock.mockReturnValue(new Map())
})

describe('useReviewProductsScope', () => {
  it('returns empty defaults and skips the campaign lookup when the run has no campaign/products config', () => {
    const { result } = renderHook(() => useReviewProductsScope(baseRun()))

    expect(result.current.globalDefaultProductIds).toEqual([])
    expect(result.current.campaignCategoryIds).toEqual([])
    expect(useForSelectLabelsMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'campaigns', ids: [], enabled: false }),
    )
  })

  it("resolves the global default products from global_config.product_ids", () => {
    const { result } = renderHook(() =>
      useReviewProductsScope(baseRun({ global_config: { product_ids: [11, 12] } })),
    )

    expect(result.current.globalDefaultProductIds).toEqual([11, 12])
  })

  it("resolves campaignCategoryIds from the chosen campaign's for-select meta", () => {
    useForSelectLabelsMock.mockReturnValue(
      new Map([[9, { id: 9, label: 'Spring campaign', meta: { product_category_ids: [3, 4] } }]]),
    )

    const { result } = renderHook(() =>
      useReviewProductsScope(baseRun({ global_config: { campaign_id: 9 } })),
    )

    expect(useForSelectLabelsMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'campaigns', ids: [9], enabled: true }),
    )
    expect(result.current.campaignCategoryIds).toEqual([3, 4])
  })
})
