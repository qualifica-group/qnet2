/**
 * Zod schema for the custom filter rule builder form (spec 0158). Column-aware
 * (operator/value validity depends on the selected field's resolved rule
 * type), so it is built by a factory that closes over the domain's usable
 * columns instead of a static top-level schema.
 */
import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  operatorsForRuleType,
  resolveRuleType,
  type RuleOperator,
  VALUELESS_OPERATORS,
} from '@/features/table/custom-filters/rule-types'
import type { TableColumn } from '@/features/table/types'

/** Server-mirrored limits ("DETTAGLIO CONGELATO"). */
export const MAX_RULES_PER_GROUP = 20
export const MAX_SET_VALUES = 500
export const MAX_TEXT_LENGTH = 255
const MAX_LAST_N_DAYS = 366

const ruleRowShape = z.object({
  field: z.string(),
  operator: z.string(),
  /** Scalar text/number/date value, or the `between` lower bound. */
  value: z.string(),
  /** The `between` upper bound only; unused otherwise. */
  valueTo: z.string(),
  /** `in`/`not_in` value list only; unused otherwise. */
  values: z.array(z.string()),
})

/** One rule row as held by the form, before it is converted to a `FilterRule`. */
export type RuleFormRow = z.infer<typeof ruleRowShape>

/** Blank row seeded for a newly-added condition. */
export function blankRuleRow(field: string): RuleFormRow {
  return { field, operator: '', value: '', valueTo: '', values: [] }
}

function isBlank(value: string): boolean {
  return value.trim() === ''
}

/**
 * Column-aware row refinement: resolves the row's rule type from its `field`,
 * checks the `operator` against that type's allowed set, then validates
 * `value`/`valueTo`/`values` against the operator's expected shape. Messages
 * are localized HERE (not left as i18n keys): `<FormMessage>`
 * (`components/ui/form.tsx`) renders `error.message` verbatim, with no
 * translation step of its own — the same convention as
 * `features/tasks/use-task-quick-create-row.ts`.
 */
function refineRuleRow(row: RuleFormRow, ctx: z.RefinementCtx, columns: TableColumn[], t: TFunction): void {
  const column = columns.find((candidate) => candidate.id === row.field)
  const ruleType = column ? resolveRuleType(column) : null
  if (!column || !ruleType) {
    ctx.addIssue({ code: 'custom', path: ['field'], message: t('table.customFilters.errors.field') })
    return
  }

  const operators = operatorsForRuleType(ruleType, column.hasFilterValues !== false)
  if (!operators.includes(row.operator as RuleOperator)) {
    ctx.addIssue({ code: 'custom', path: ['operator'], message: t('table.customFilters.errors.operator') })
    return
  }

  if (VALUELESS_OPERATORS.has(row.operator as RuleOperator)) {
    return
  }

  if (row.operator === 'last_n_days') {
    const days = Number(row.value)
    if (!Number.isInteger(days) || days < 1 || days > MAX_LAST_N_DAYS) {
      ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.lastNDays') })
    }
    return
  }

  if (ruleType === 'set') {
    if (row.values.length === 0) {
      ctx.addIssue({ code: 'custom', path: ['values'], message: t('table.customFilters.errors.required') })
    } else if (row.values.length > MAX_SET_VALUES) {
      ctx.addIssue({ code: 'custom', path: ['values'], message: t('table.customFilters.errors.tooMany') })
    }
    return
  }

  if (ruleType === 'boolean') {
    if (row.value !== 'true' && row.value !== 'false') {
      ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.required') })
    }
    return
  }

  if (row.operator === 'between') {
    if (isBlank(row.value) || isBlank(row.valueTo)) {
      ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.required') })
      return
    }
    if (ruleType === 'number') {
      const from = Number(row.value)
      const to = Number(row.valueTo)
      if (Number.isNaN(from) || Number.isNaN(to)) {
        ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.number') })
      } else if (from > to) {
        ctx.addIssue({ code: 'custom', path: ['valueTo'], message: t('table.customFilters.errors.range') })
      }
    } else if (row.value > row.valueTo) {
      ctx.addIssue({ code: 'custom', path: ['valueTo'], message: t('table.customFilters.errors.range') })
    }
    return
  }

  // Remaining case: a plain scalar value (text contains/equals/not_equals,
  // number/date equals/lt/lte/gt/gte).
  if (isBlank(row.value)) {
    ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.required') })
    return
  }
  if (ruleType === 'text' && row.value.length > MAX_TEXT_LENGTH) {
    ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.tooLong') })
  }
  if (ruleType === 'number' && Number.isNaN(Number(row.value))) {
    ctx.addIssue({ code: 'custom', path: ['value'], message: t('table.customFilters.errors.number') })
  }
}

/** The rule builder's whole form shape: two condition groups. */
export interface RuleBuilderFormValues {
  and: RuleFormRow[]
  or: RuleFormRow[]
}

/**
 * Builds the rule builder's Zod schema for one domain's usable columns
 * (`usableRuleColumns`): each group capped at `MAX_RULES_PER_GROUP`, every row
 * refined against its field's resolved rule type, and at least one rule
 * required across both groups (mirrors the backend's own validation). `t`
 * localizes every issue message at build time (see `refineRuleRow`).
 */
export function buildRuleBuilderSchema(columns: TableColumn[], t: TFunction) {
  const group = z
    .array(ruleRowShape.superRefine((row, ctx) => refineRuleRow(row, ctx, columns, t)))
    .max(MAX_RULES_PER_GROUP, { message: t('table.customFilters.errors.tooManyRules') })

  return z.object({ and: group, or: group }).superRefine((values, ctx) => {
    if (values.and.length === 0 && values.or.length === 0) {
      ctx.addIssue({ code: 'custom', path: ['and'], message: t('table.customFilters.errors.atLeastOne') })
    }
  })
}
