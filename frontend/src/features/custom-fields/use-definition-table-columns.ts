import { useFieldArray, useFormContext, useFormState, useWatch, type Control } from 'react-hook-form'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import {
  TABLE_MAX_COLUMNS,
  TABLE_SUMMARY_COLUMN_TYPES,
  blankTableColumn,
  suggestColumnKey,
  type TableColumnRow,
} from '@/features/custom-fields/field-definition-table'
import type { TableColumnType } from '@/features/custom-fields/types'

/** A column the grid summary can read from: it has a key and a summary-eligible type. */
function isSummaryEligible(column: TableColumnRow): boolean {
  return column.key !== '' && TABLE_SUMMARY_COLUMN_TYPES.includes(column.type)
}

/**
 * State and handlers of `DefinitionTableColumnsEditor`. Keeps the dependent
 * settings coherent while columns change: renaming a column's key follows it in
 * the summary, removing it (or switching it to a non-summary type) clears the
 * summary, and turning row selection off downgrades the `selected` strategy.
 * Must run inside the host form's `<Form>` provider.
 */
export function useDefinitionTableColumns(control: Control<FieldDefinitionFormValues>) {
  const { setValue, getValues } = useFormContext<FieldDefinitionFormValues>()
  const { fields, append, remove, move } = useFieldArray({ control, name: 'table.columns' })
  const table = useWatch({ control, name: 'table' })
  const { errors } = useFormState({ control, name: 'table' })

  const columns = table.columns
  const columnsError = errors.table?.columns?.root?.message ?? errors.table?.columns?.message
  const optionsErrorAt = (index: number) => {
    const optionsError = errors.table?.columns?.[index]?.options
    return optionsError?.root?.message ?? optionsError?.message
  }
  const summaryColumns = columns.filter(isSummaryEligible)

  const clearSummary = () => {
    setValue('table.summary_column', '', { shouldDirty: true })
    setValue('table.summary_strategy', '', { shouldDirty: true })
  }

  const clearSummaryIfDropped = (key: string, stillEligible: boolean) => {
    if (getValues('table.summary_column') === key && !stillEligible) {
      clearSummary()
    }
  }

  const changeKey = (index: number, key: string, touched = true) => {
    const previous = getValues(`table.columns.${index}.key`)
    setValue(`table.columns.${index}.key`, key, { shouldDirty: true })
    if (touched) {
      setValue(`table.columns.${index}.key_touched`, true)
    }
    if (previous !== '' && getValues('table.summary_column') === previous) {
      setValue('table.summary_column', key, { shouldDirty: true })
    }
  }

  const changeLabel = (index: number, label: string) => {
    setValue(`table.columns.${index}.label`, label, { shouldDirty: true })
    if (!getValues(`table.columns.${index}.key_touched`)) {
      changeKey(index, suggestColumnKey(label), false)
    }
  }

  const changeType = (index: number, type: TableColumnType) => {
    const column = getValues(`table.columns.${index}`)
    setValue(`table.columns.${index}.type`, type, { shouldDirty: true })
    clearSummaryIfDropped(column.key, TABLE_SUMMARY_COLUMN_TYPES.includes(type))
  }

  const removeColumn = (index: number) => {
    clearSummaryIfDropped(getValues(`table.columns.${index}.key`), false)
    remove(index)
  }

  const setSelectable = (enabled: boolean) => {
    setValue('table.selectable_enabled', enabled, { shouldDirty: true })
    if (!enabled && getValues('table.summary_strategy') === 'selected') {
      setValue('table.summary_strategy', 'max', { shouldDirty: true })
    }
  }

  const setSummaryColumn = (key: string) => {
    if (key === '') {
      clearSummary()
      return
    }
    setValue('table.summary_column', key, { shouldDirty: true })
    if (getValues('table.summary_strategy') === '') {
      setValue('table.summary_strategy', 'max', { shouldDirty: true })
    }
  }

  return {
    fields,
    table,
    columnsError,
    optionsErrorAt,
    summaryColumns,
    canAddColumn: fields.length < TABLE_MAX_COLUMNS,
    addColumn: () => append(blankTableColumn()),
    moveColumn: (from: number, to: number) => move(from, to),
    removeColumn,
    changeLabel,
    changeKey,
    changeType,
    setSelectable,
    setSummaryColumn,
  }
}
