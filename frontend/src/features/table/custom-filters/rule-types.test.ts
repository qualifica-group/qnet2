import { describe, expect, it } from 'vitest'
import {
  isUsableRuleColumn,
  operatorsForRuleType,
  resolveRuleType,
  usableRuleColumns,
} from '@/features/table/custom-filters/rule-types'
import type { TableColumn } from '@/features/table/types'

function column(overrides: Partial<TableColumn>): TableColumn {
  return {
    id: 'field',
    label: 'label',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...overrides,
  }
}

describe('resolveRuleType', () => {
  it('maps a boolean-typed column to boolean regardless of filterType', () => {
    expect(resolveRuleType(column({ type: 'boolean', filterType: undefined }))).toBe('boolean')
  })

  it('maps filterType boolean to boolean regardless of type', () => {
    expect(resolveRuleType(column({ type: 'text', filterType: 'boolean' }))).toBe('boolean')
  })

  it('maps filterType set/date/number/text directly', () => {
    expect(resolveRuleType(column({ filterType: 'set' }))).toBe('set')
    expect(resolveRuleType(column({ filterType: 'date' }))).toBe('date')
    expect(resolveRuleType(column({ filterType: 'number' }))).toBe('number')
    expect(resolveRuleType(column({ filterType: 'text' }))).toBe('text')
  })

  it('maps filterType multi via the column type (number/datetime/other)', () => {
    expect(resolveRuleType(column({ filterType: 'multi', type: 'number' }))).toBe('number')
    expect(resolveRuleType(column({ filterType: 'multi', type: 'datetime' }))).toBe('date')
    expect(resolveRuleType(column({ filterType: 'multi', type: 'enum' }))).toBe('text')
  })

  it('returns null for any other/absent filterType', () => {
    expect(resolveRuleType(column({ filterType: undefined, type: 'tags' }))).toBeNull()
  })
})

describe('operatorsForRuleType', () => {
  it('adds blank/not_blank to text/number/date when hasFilterValues is true', () => {
    expect(operatorsForRuleType('text', true)).toEqual(['contains', 'equals', 'not_equals', 'blank', 'not_blank'])
    expect(operatorsForRuleType('number', true)).toContain('blank')
    expect(operatorsForRuleType('date', true)).toContain('not_blank')
  })

  it('omits blank/not_blank when hasFilterValues is false', () => {
    expect(operatorsForRuleType('text', false)).toEqual(['contains', 'equals', 'not_equals'])
  })

  it('never adds blank/not_blank to set/boolean', () => {
    expect(operatorsForRuleType('set', true)).toEqual(['in', 'not_in'])
    expect(operatorsForRuleType('boolean', true)).toEqual(['is'])
  })
})

describe('isUsableRuleColumn / usableRuleColumns', () => {
  it('excludes non-filterable columns', () => {
    const col = column({ filterable: false, filterType: 'text' })
    expect(isUsableRuleColumn(col)).toBe(false)
  })

  it('excludes columns whose type cannot resolve', () => {
    const col = column({ filterable: true, filterType: undefined, type: 'tags' })
    expect(isUsableRuleColumn(col)).toBe(false)
  })

  it('keeps only usable columns, preserving order', () => {
    const usable = column({ id: 'a', filterable: true, filterType: 'text' })
    const notFilterable = column({ id: 'b', filterable: false, filterType: 'text' })
    const notResolvable = column({ id: 'c', filterable: true, filterType: undefined, type: 'tags' })
    const alsoUsable = column({ id: 'd', filterable: true, filterType: 'number' })

    expect(usableRuleColumns([usable, notFilterable, notResolvable, alsoUsable])).toEqual([
      usable,
      alsoUsable,
    ])
  })
})
