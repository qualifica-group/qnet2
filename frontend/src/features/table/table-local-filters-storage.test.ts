// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest'
import {
  EMPTY_TABLE_LOCAL_FILTERS,
  readTableLocalFilters,
  tableLocalFiltersKey,
  writeTableLocalFilters,
} from '@/features/table/table-local-filters-storage'
import type { FilterRules } from '@/features/table/types'

const RULES: FilterRules = { and: [{ field: 'status', operator: 'equals', value: 'open' }], or: [] }
const KEY = tableLocalFiltersKey({ userId: 1, domain: 'leads' })

beforeEach(() => {
  window.localStorage.clear()
})

describe('tableLocalFiltersKey', () => {
  it('separates users, domains and row-set scopes', () => {
    const keys = new Set([
      KEY,
      tableLocalFiltersKey({ userId: 2, domain: 'leads' }),
      tableLocalFiltersKey({ userId: 1, domain: 'quotes' }),
      tableLocalFiltersKey({ userId: 1, domain: 'leads', productCategoryId: 3 }),
      tableLocalFiltersKey({ userId: 1, domain: 'leads', opportunityId: 3 }),
      tableLocalFiltersKey({ userId: 1, domain: 'leads', quoteId: 3 }),
      tableLocalFiltersKey({ userId: 1, domain: 'leads', registryId: 3 }),
    ])
    expect(keys.size).toBe(7)
  })

  // Spec 0199: the Anagrafica segment is appended only when set, so every key
  // written before it existed (and every unscoped table) still reads back.
  it('keeps the pre-existing key shape when no anagrafica scope is set', () => {
    expect(tableLocalFiltersKey({ userId: 1, domain: 'quotes', quoteId: 4 })).toBe('table-filters:1:quotes:::4')
    expect(tableLocalFiltersKey({ userId: 1, domain: 'quotes', registryId: 4 })).toBe('table-filters:1:quotes::::4')
  })
})

describe('readTableLocalFilters / writeTableLocalFilters', () => {
  it('round-trips the search and the custom filter', () => {
    const filters = { search: 'rossi', customFilter: { rules: RULES, viewId: 4, name: 'Aperte' } }
    writeTableLocalFilters(KEY, filters)
    expect(readTableLocalFilters(KEY)).toEqual(filters)
  })

  it('returns no filters when nothing is stored or there is no key', () => {
    expect(readTableLocalFilters(KEY)).toEqual(EMPTY_TABLE_LOCAL_FILTERS)
    expect(readTableLocalFilters(null)).toEqual(EMPTY_TABLE_LOCAL_FILTERS)
  })

  it('removes the entry once no filter is active (blank search, no custom filter)', () => {
    writeTableLocalFilters(KEY, { search: 'rossi', customFilter: null })
    writeTableLocalFilters(KEY, { search: '   ', customFilter: null })
    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('never writes without a key', () => {
    writeTableLocalFilters(null, { search: 'rossi', customFilter: null })
    expect(window.localStorage.length).toBe(0)
  })

  it('ignores an entry that is not valid JSON', () => {
    window.localStorage.setItem(KEY, '{broken')
    expect(readTableLocalFilters(KEY)).toEqual(EMPTY_TABLE_LOCAL_FILTERS)
  })

  it('drops a malformed custom filter but keeps a valid search', () => {
    window.localStorage.setItem(KEY, JSON.stringify({ search: 'rossi', customFilter: { rules: 'all' } }))
    expect(readTableLocalFilters(KEY)).toEqual({ search: 'rossi', customFilter: null })
  })
})
