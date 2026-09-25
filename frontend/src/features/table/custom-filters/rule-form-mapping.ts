/**
 * Conversion between the rule builder's form rows (`RuleFormRow`, always
 * string-based — Radix/native inputs only speak strings) and the wire
 * `FilterRule`/`FilterRules` shape (typed per rule type/operator). Assumes the
 * form value already passed `buildRuleBuilderSchema` — no re-validation here.
 */
import { resolveRuleType, VALUELESS_OPERATORS, type RuleOperator } from '@/features/table/custom-filters/rule-types'
import type { RuleBuilderFormValues, RuleFormRow } from '@/features/table/custom-filters/rule-schema'
import type { FilterRule, FilterRules, TableColumn } from '@/features/table/types'

function rowToRule(row: RuleFormRow, columns: TableColumn[]): FilterRule {
  if (VALUELESS_OPERATORS.has(row.operator as RuleOperator)) {
    return { field: row.field, operator: row.operator }
  }
  if (row.operator === 'last_n_days') {
    return { field: row.field, operator: row.operator, value: Number(row.value) }
  }

  const column = columns.find((candidate) => candidate.id === row.field)
  const ruleType = column ? resolveRuleType(column) : null

  if (ruleType === 'set') {
    return { field: row.field, operator: row.operator, value: row.values }
  }
  if (ruleType === 'boolean') {
    return { field: row.field, operator: row.operator, value: row.value === 'true' }
  }
  if (row.operator === 'between') {
    const value = ruleType === 'number' ? [Number(row.value), Number(row.valueTo)] : [row.value, row.valueTo]
    return { field: row.field, operator: row.operator, value }
  }
  if (ruleType === 'number') {
    return { field: row.field, operator: row.operator, value: Number(row.value) }
  }
  // text/date: the server-side format ('Y-m-d' for date) round-trips as-is.
  return { field: row.field, operator: row.operator, value: row.value }
}

/** Builds the wire `FilterRules` payload from a validated form value. */
export function formValuesToRules(values: RuleBuilderFormValues, columns: TableColumn[]): FilterRules {
  return {
    and: values.and.map((row) => rowToRule(row, columns)),
    or: values.or.map((row) => rowToRule(row, columns)),
  }
}

function ruleToRow(rule: FilterRule): RuleFormRow {
  if (rule.value === undefined || rule.value === null) {
    return { field: rule.field, operator: rule.operator, value: '', valueTo: '', values: [] }
  }
  if (Array.isArray(rule.value)) {
    if (rule.operator === 'between') {
      const [from, to] = rule.value as [unknown, unknown]
      return { field: rule.field, operator: rule.operator, value: String(from), valueTo: String(to), values: [] }
    }
    return {
      field: rule.field,
      operator: rule.operator,
      value: '',
      valueTo: '',
      values: (rule.value as unknown[]).map(String),
    }
  }
  return { field: rule.field, operator: rule.operator, value: String(rule.value), valueTo: '', values: [] }
}

/** Rebuilds form rows from a saved/applied `FilterRules` (editing an existing view). */
export function rulesToFormValues(rules: FilterRules): RuleBuilderFormValues {
  return {
    and: rules.and.map(ruleToRow),
    or: rules.or.map(ruleToRow),
  }
}
