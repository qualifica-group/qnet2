import type { TableDefinitionBag } from '@/features/custom-fields/field-definition-table'

export interface TableNotifyTriggerOption {
  key: string
  label: string
}

/**
 * The yes/no switches a table row event can fire on (spec 0390): every
 * `boolean` column with a key, plus the row selection when enabled. Same set
 * the server's `TableRowNotifyConfigValidator` accepts.
 */
export function tableNotifyTriggerOptions(bag: TableDefinitionBag): TableNotifyTriggerOption[] {
  const options = bag.columns
    .filter((column) => column.type === 'boolean' && column.key !== '')
    .map((column) => ({ key: column.key, label: column.label || column.key }))
  if (bag.selectable_enabled && bag.selectable_key !== '') {
    options.push({ key: bag.selectable_key, label: bag.selectable_label || bag.selectable_key })
  }
  return options
}

export function tableNotifyTriggerKeys(bag: TableDefinitionBag): string[] {
  return tableNotifyTriggerOptions(bag).map((option) => option.key)
}
