import { describe, expect, it } from 'vitest'
import {
  sameReportColumns,
  sortReportColumnKeys,
  toggleReportColumn,
} from '@/features/product-categories/report-columns-inheritance'

const CATALOG_ORDER = ['phone_calls', 'callbacks', 'new_contacts', 'leads']

describe('sortReportColumnKeys', () => {
  it('dedupes and orders like the catalogue, regardless of input order', () => {
    expect(sortReportColumnKeys(['leads', 'phone_calls', 'leads'], CATALOG_ORDER)).toEqual([
      'phone_calls',
      'leads',
    ])
  })
})

describe('toggleReportColumn', () => {
  it('adds a key not yet checked, catalog-ordered', () => {
    expect(toggleReportColumn(['phone_calls'], 'new_contacts', CATALOG_ORDER)).toEqual([
      'phone_calls',
      'new_contacts',
    ])
  })

  it('removes a key already checked', () => {
    expect(toggleReportColumn(['phone_calls', 'new_contacts'], 'phone_calls', CATALOG_ORDER)).toEqual([
      'new_contacts',
    ])
  })

  it('unchecking the last own column returns null (spec 0141 AC-009 "deselezionare tutte = null")', () => {
    expect(toggleReportColumn(['phone_calls'], 'phone_calls', CATALOG_ORDER)).toBeNull()
  })
})

describe('sameReportColumns', () => {
  it('treats null and null as equal, and null against any array as different', () => {
    expect(sameReportColumns(null, null)).toBe(true)
    expect(sameReportColumns(null, ['phone_calls'])).toBe(false)
    expect(sameReportColumns(['phone_calls'], null)).toBe(false)
  })

  it('is order-independent for two non-null arrays of the same set', () => {
    expect(sameReportColumns(['phone_calls', 'callbacks'], ['callbacks', 'phone_calls'])).toBe(true)
  })

  it('is false when the sets differ', () => {
    expect(sameReportColumns(['phone_calls'], ['phone_calls', 'callbacks'])).toBe(false)
  })
})
