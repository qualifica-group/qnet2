import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { blankRuleRow, buildRuleBuilderSchema, type RuleBuilderFormValues } from '@/features/table/custom-filters/rule-schema'
import { formValuesToRules, rulesToFormValues } from '@/features/table/custom-filters/rule-form-mapping'
import type { TableColumn } from '@/features/table/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const COLUMNS: TableColumn[] = [
  { id: 'name', label: 'l', type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true, filterType: 'text' },
  { id: 'amount', label: 'l', type: 'number', visible: true, width: null, order: 1, sortable: true, filterable: true, filterType: 'number' },
  { id: 'due_at', label: 'l', type: 'datetime', visible: true, width: null, order: 2, sortable: true, filterable: true, filterType: 'date' },
  { id: 'status', label: 'l', type: 'enum', visible: true, width: null, order: 3, sortable: true, filterable: true, filterType: 'set' },
  { id: 'active', label: 'l', type: 'boolean', visible: true, width: null, order: 4, sortable: true, filterable: true, filterType: 'boolean' },
  {
    id: 'computed',
    label: 'l',
    type: 'text',
    visible: true,
    width: null,
    order: 5,
    sortable: true,
    filterable: true,
    filterType: 'text',
    hasFilterValues: false,
  },
]

function values(and: RuleBuilderFormValues['and'], or: RuleBuilderFormValues['or'] = []): RuleBuilderFormValues {
  return { and, or }
}

describe('buildRuleBuilderSchema', () => {
  // These tests only assert success/failure, never message text, so the
  // real `i18n.t` (any locale) is enough — no stub needed.
  const schema = buildRuleBuilderSchema(COLUMNS, i18n.t)

  it('rejects an empty rule set (at least one rule required)', () => {
    const result = schema.safeParse(values([]))
    expect(result.success).toBe(false)
  })

  it('accepts a valid text contains rule', () => {
    const row = { ...blankRuleRow('name'), operator: 'contains', value: 'acme' }
    expect(schema.safeParse(values([row])).success).toBe(true)
  })

  it('rejects an unknown field', () => {
    const row = { ...blankRuleRow('ghost'), operator: 'equals', value: 'x' }
    expect(schema.safeParse(values([row])).success).toBe(false)
  })

  it('rejects an operator not valid for the field type', () => {
    const row = { ...blankRuleRow('name'), operator: 'gte', value: 'x' }
    expect(schema.safeParse(values([row])).success).toBe(false)
  })

  it('rejects blank/not_blank on a column with hasFilterValues:false', () => {
    const row = { ...blankRuleRow('computed'), operator: 'blank' }
    expect(schema.safeParse(values([row])).success).toBe(false)
  })

  it('accepts blank/not_blank on a column with hasFilterValues true (default)', () => {
    const row = { ...blankRuleRow('name'), operator: 'blank' }
    expect(schema.safeParse(values([row])).success).toBe(true)
  })

  it('validates a number between with min <= max', () => {
    const ok = { ...blankRuleRow('amount'), operator: 'between', value: '1', valueTo: '10' }
    expect(schema.safeParse(values([ok])).success).toBe(true)

    const bad = { ...blankRuleRow('amount'), operator: 'between', value: '10', valueTo: '1' }
    expect(schema.safeParse(values([bad])).success).toBe(false)
  })

  it('validates last_n_days as an integer 1..366', () => {
    const ok = { ...blankRuleRow('due_at'), operator: 'last_n_days', value: '30' }
    expect(schema.safeParse(values([ok])).success).toBe(true)

    const tooLarge = { ...blankRuleRow('due_at'), operator: 'last_n_days', value: '400' }
    expect(schema.safeParse(values([tooLarge])).success).toBe(false)
  })

  it('accepts today/this_week/this_month with no value', () => {
    const row = { ...blankRuleRow('due_at'), operator: 'today' }
    expect(schema.safeParse(values([row])).success).toBe(true)
  })

  it('requires a non-empty value list for in/not_in, capped at 500', () => {
    const empty = { ...blankRuleRow('status'), operator: 'in', values: [] }
    expect(schema.safeParse(values([empty])).success).toBe(false)

    const withValues = { ...blankRuleRow('status'), operator: 'in', values: ['a', 'b'] }
    expect(schema.safeParse(values([withValues])).success).toBe(true)

    const tooMany = { ...blankRuleRow('status'), operator: 'in', values: Array.from({ length: 501 }, (_, i) => String(i)) }
    expect(schema.safeParse(values([tooMany])).success).toBe(false)
  })

  it('requires a boolean value for is', () => {
    const missing = { ...blankRuleRow('active'), operator: 'is', value: '' }
    expect(schema.safeParse(values([missing])).success).toBe(false)

    const set = { ...blankRuleRow('active'), operator: 'is', value: 'true' }
    expect(schema.safeParse(values([set])).success).toBe(true)
  })

  it('caps a group at 20 rules', () => {
    const rows = Array.from({ length: 21 }, () => ({ ...blankRuleRow('name'), operator: 'contains', value: 'x' }))
    expect(schema.safeParse(values(rows)).success).toBe(false)
  })

  it('rejects text values over 255 characters', () => {
    const row = { ...blankRuleRow('name'), operator: 'equals', value: 'a'.repeat(256) }
    expect(schema.safeParse(values([row])).success).toBe(false)
  })
})

describe('formValuesToRules / rulesToFormValues', () => {
  it('round-trips a set of rules through the form representation', () => {
    const rules = {
      and: [
        { field: 'name', operator: 'contains', value: 'acme' },
        { field: 'amount', operator: 'between', value: [1, 10] },
        { field: 'due_at', operator: 'today' },
      ],
      or: [
        { field: 'status', operator: 'in', value: ['a', 'b'] },
        { field: 'active', operator: 'is', value: true },
      ],
    }

    const formValues = rulesToFormValues(rules)
    expect(formValuesToRules(formValues, COLUMNS)).toEqual(rules)
  })

  it('converts a number rule value to a real number, not a numeric string', () => {
    const row = { ...blankRuleRow('amount'), operator: 'gte', value: '42' }
    const result = formValuesToRules({ and: [row], or: [] }, COLUMNS)
    expect(result.and[0].value).toBe(42)
    expect(typeof result.and[0].value).toBe('number')
  })
})
