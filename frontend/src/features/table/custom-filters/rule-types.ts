/**
 * Column → custom filter rule-type/operator mapping (spec 0158, "DETTAGLIO
 * CONGELATO"). Pure, framework-free functions: the backend derives a rule's
 * type from the SAME `{type, filterType}` pair on the SAME column catalog, so
 * this module is the frontend half of one shared contract — never invent a
 * mapping, mirror the frozen table verbatim.
 */
import type { TableColumn } from '@/features/table/types'

/** The five rule value shapes the builder and the backend agree on. */
export type RuleType = 'text' | 'number' | 'date' | 'set' | 'boolean'

/** Every operator token accepted by at least one rule type. */
export type RuleOperator =
  | 'contains'
  | 'equals'
  | 'not_equals'
  | 'blank'
  | 'not_blank'
  | 'lt'
  | 'lte'
  | 'gt'
  | 'gte'
  | 'between'
  | 'today'
  | 'this_week'
  | 'this_month'
  | 'last_n_days'
  | 'in'
  | 'not_in'
  | 'is'

/**
 * Derives a rule's value type from a filterable column, per the frozen
 * mapping: `type === 'boolean'` or `filterType === 'boolean'` → boolean;
 * `filterType` 'set' → set, 'date' → date, 'number' → number, 'text' → text;
 * 'multi' (the combined Set Filter + typed condition) → derived from `type`
 * (number → number, datetime → date, anything else → text); any other
 * `filterType` (absent/null) → `null`, the column is not usable in a rule.
 */
export function resolveRuleType(
  column: Pick<TableColumn, 'type' | 'filterType'>,
): RuleType | null {
  if (column.type === 'boolean' || column.filterType === 'boolean') {
    return 'boolean'
  }
  switch (column.filterType) {
    case 'set':
      return 'set'
    case 'date':
      return 'date'
    case 'number':
      return 'number'
    case 'text':
      return 'text'
    case 'multi':
      if (column.type === 'number') {
        return 'number'
      }
      if (column.type === 'datetime') {
        return 'date'
      }
      return 'text'
    default:
      return null
  }
}

/** The base operator set of each rule type, before the blank/not_blank addition. */
const BASE_OPERATORS: Record<RuleType, RuleOperator[]> = {
  text: ['contains', 'equals', 'not_equals'],
  number: ['equals', 'lt', 'lte', 'gt', 'gte', 'between'],
  date: ['equals', 'lt', 'lte', 'gt', 'gte', 'between', 'today', 'this_week', 'this_month', 'last_n_days'],
  set: ['in', 'not_in'],
  boolean: ['is'],
}

/** Rule types that ever offer `blank`/`not_blank` (text/number/date). */
const BLANK_CAPABLE_TYPES: ReadonlySet<RuleType> = new Set(['text', 'number', 'date'])

/**
 * The operator list for a rule type, adding `blank`/`not_blank` only when the
 * column does not declare `hasFilterValues: false` — the same condition that
 * hides the grid's own "(Vuoti)" set-filter entry.
 */
export function operatorsForRuleType(ruleType: RuleType, hasFilterValues: boolean): RuleOperator[] {
  const base = BASE_OPERATORS[ruleType]
  if (BLANK_CAPABLE_TYPES.has(ruleType) && hasFilterValues) {
    return [...base, 'blank', 'not_blank']
  }
  return base
}

/** Whether a column can be used as a rule field: filterable AND its type resolves. */
export function isUsableRuleColumn(column: TableColumn): boolean {
  return column.filterable && resolveRuleType(column) !== null
}

/** The subset of `columns` usable as rule fields, in their given order. */
export function usableRuleColumns(columns: TableColumn[]): TableColumn[] {
  return columns.filter(isUsableRuleColumn)
}

/** Operators whose rule carries no `value` at all. */
export const VALUELESS_OPERATORS: ReadonlySet<RuleOperator> = new Set([
  'blank',
  'not_blank',
  'today',
  'this_week',
  'this_month',
])

/** Operators whose value is a `[min, max]` pair. */
export const RANGE_OPERATORS: ReadonlySet<RuleOperator> = new Set(['between'])

/** Operators whose value is a non-empty string list (the `set` rule type). */
export const LIST_OPERATORS: ReadonlySet<RuleOperator> = new Set(['in', 'not_in'])
