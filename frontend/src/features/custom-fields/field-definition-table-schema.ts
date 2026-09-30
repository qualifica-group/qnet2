import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  TABLE_COLUMN_TYPES,
  TABLE_KEY_PATTERN,
  TABLE_RESERVED_KEY,
  type TableDefinitionBag,
} from '@/features/custom-fields/field-definition-table'

/**
 * Zod pieces for the `table` definition bag, shared by the custom field and
 * attribute schemas. `validateTableDefinition` mirrors the server's
 * `TableFieldConfigValidator` (spec 0180) so the admin sees the error on the
 * control before submitting; the server stays authoritative.
 */

export function fieldDefinitionTableFields() {
  return z.object({
    columns: z.array(
      z.object({
        key: z.string(),
        label: z.string(),
        type: z.enum(TABLE_COLUMN_TYPES),
        required: z.boolean(),
        key_touched: z.boolean(),
        config: z.record(z.string(), z.unknown()).nullable(),
        options: z.array(z.object({ value: z.string(), label: z.string(), color: z.string() })),
      }),
    ),
    selectable_enabled: z.boolean(),
    selectable_key: z.string(),
    selectable_label: z.string(),
    summary_column: z.string(),
    summary_strategy: z.enum(['', 'selected', 'max', 'min']),
    min_rows: z.number().int().nonnegative().nullable(),
    max_rows: z.number().int().positive().nullable(),
  })
}

function checkColumnKeys(bag: TableDefinitionBag, t: TFunction, ctx: z.RefinementCtx): void {
  const seen = new Set<string>()
  const selectableKey = bag.selectable_enabled ? bag.selectable_key : null
  bag.columns.forEach((column, index) => {
    const path = ['table', 'columns', index, 'key']
    if (!TABLE_KEY_PATTERN.test(column.key)) {
      ctx.addIssue({ code: 'custom', path, message: t('customFields.form.keyInvalid') })
    } else if (column.key === TABLE_RESERVED_KEY || column.key === selectableKey) {
      ctx.addIssue({ code: 'custom', path, message: t('customFields.tableEditor.errors.reservedKey') })
    } else if (seen.has(column.key)) {
      ctx.addIssue({ code: 'custom', path, message: t('customFields.tableEditor.errors.duplicateKey') })
    }
    seen.add(column.key)
    if (column.label.trim() === '') {
      ctx.addIssue({ code: 'custom', path: ['table', 'columns', index, 'label'], message: t('customFields.form.labelRequired') })
    }
  })
}

function checkColumnOptions(bag: TableDefinitionBag, t: TFunction, ctx: z.RefinementCtx): void {
  bag.columns.forEach((column, index) => {
    if (column.type !== 'enum') {
      return
    }
    const base = ['table', 'columns', index, 'options']
    if (column.options.length === 0) {
      ctx.addIssue({ code: 'custom', path: base, message: t('customFields.tableEditor.errors.enumWithoutOptions') })
      return
    }
    const seen = new Set<string>()
    column.options.forEach((option, optionIndex) => {
      if (option.value.trim() === '') {
        ctx.addIssue({ code: 'custom', path: [...base, optionIndex, 'value'], message: t('customFields.form.optionValueRequired') })
      } else if (seen.has(option.value)) {
        ctx.addIssue({ code: 'custom', path: [...base, optionIndex, 'value'], message: t('customFields.tableEditor.errors.duplicateOptionValue') })
      }
      seen.add(option.value)
      if (option.label.trim() === '') {
        ctx.addIssue({ code: 'custom', path: [...base, optionIndex, 'label'], message: t('customFields.form.optionLabelRequired') })
      }
    })
  })
}

function checkSelectable(bag: TableDefinitionBag, t: TFunction, ctx: z.RefinementCtx): void {
  if (!bag.selectable_enabled) {
    return
  }
  const keyPath = ['table', 'selectable_key']
  if (!TABLE_KEY_PATTERN.test(bag.selectable_key)) {
    ctx.addIssue({ code: 'custom', path: keyPath, message: t('customFields.form.keyInvalid') })
  } else if (bag.selectable_key === TABLE_RESERVED_KEY) {
    ctx.addIssue({ code: 'custom', path: keyPath, message: t('customFields.tableEditor.errors.reservedKey') })
  } else if (bag.columns.some((column) => column.key === bag.selectable_key)) {
    ctx.addIssue({ code: 'custom', path: keyPath, message: t('customFields.tableEditor.errors.duplicateKey') })
  }
  if (bag.selectable_label.trim() === '') {
    ctx.addIssue({ code: 'custom', path: ['table', 'selectable_label'], message: t('customFields.form.labelRequired') })
  }
}

/** Cross-field rules for `type === 'table'`. */
export function validateTableDefinition(bag: TableDefinitionBag, t: TFunction, ctx: z.RefinementCtx): void {
  if (bag.columns.length === 0) {
    ctx.addIssue({ code: 'custom', path: ['table', 'columns'], message: t('customFields.tableEditor.errors.noColumns') })
    return
  }
  checkColumnKeys(bag, t, ctx)
  checkColumnOptions(bag, t, ctx)
  checkSelectable(bag, t, ctx)
  if (bag.summary_column && bag.summary_strategy === 'selected' && !bag.selectable_enabled) {
    ctx.addIssue({ code: 'custom', path: ['table', 'summary_strategy'], message: t('customFields.tableEditor.errors.summaryRequiresSelectable') })
  }
  if (bag.min_rows !== null && bag.max_rows !== null && bag.min_rows > bag.max_rows) {
    ctx.addIssue({ code: 'custom', path: ['table', 'max_rows'], message: t('customFields.tableEditor.errors.minGreaterThanMax') })
  }
}
