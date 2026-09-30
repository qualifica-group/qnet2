import type {
  CustomFieldConfig,
  CustomFieldOption,
  TableColumnType,
  TableFieldColumn,
  TableFieldConfig,
  TableSummaryStrategy,
} from '@/features/custom-fields/types'

/**
 * Admin-form model of a `table` field definition (spec 0180 `TableFieldConfig`):
 * the flat RHF bag the columns editor edits, plus the hydration from a persisted
 * `config` and the projection back onto the wire shape.
 */

/** Backend `custom-fields.table.max_columns`. */
export const TABLE_MAX_COLUMNS = 20
/** Backend `custom-fields.table.max_rows`. */
export const TABLE_MAX_ROWS = 200
/** Backend column/selectable `key` shape. */
export const TABLE_KEY_PATTERN = /^[a-z][a-z0-9_]{0,63}$/
/** Column key the server reserves for the row identifier. */
export const TABLE_RESERVED_KEY = 'id'

/** Column types a `table` accepts (D-2: simple types only). */
export const TABLE_COLUMN_TYPES: readonly TableColumnType[] = [
  'text',
  'textarea',
  'integer',
  'decimal',
  'boolean',
  'enum',
  'date',
  'datetime',
  'time',
  'email',
  'url',
  'color',
]

/** Column types the grid summary can be computed from. */
export const TABLE_SUMMARY_COLUMN_TYPES: readonly TableColumnType[] = [
  'date',
  'datetime',
  'time',
  'integer',
  'decimal',
  'text',
  'enum',
]

export interface TableColumnOptionRow {
  value: string
  label: string
  /** Not editable here; carried through so an edit never drops a persisted colour. */
  color: string
}

export interface TableColumnRow {
  key: string
  label: string
  type: TableColumnType
  required: boolean
  /** True once the admin edited `key` by hand (or it was persisted): stops deriving it from the label. */
  key_touched: boolean
  /** Per-type column config, carried through untouched. */
  config: Record<string, unknown> | null
  options: TableColumnOptionRow[]
}

export interface TableDefinitionBag {
  columns: TableColumnRow[]
  selectable_enabled: boolean
  selectable_key: string
  selectable_label: string
  /** `''` = no summary. */
  summary_column: string
  summary_strategy: '' | TableSummaryStrategy
  min_rows: number | null
  max_rows: number | null
}

export function blankTableColumn(): TableColumnRow {
  return { key: '', label: '', type: 'text', required: false, key_touched: false, config: null, options: [] }
}

export function blankTableColumnOption(): TableColumnOptionRow {
  return { value: '', label: '', color: '' }
}

export function emptyTableDefinition(): TableDefinitionBag {
  return {
    columns: [],
    selectable_enabled: false,
    selectable_key: '',
    selectable_label: '',
    summary_column: '',
    summary_strategy: '',
    min_rows: null,
    max_rows: null,
  }
}

/** Snake_case key suggested from a column label (`Data verifica` -> `data_verifica`). */
export function suggestColumnKey(label: string): string {
  return label
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/^[0-9_]+/, '')
    .slice(0, 64)
}

/** Hydrates the bag from a persisted definition `config` (edit/duplicate mode). */
export function hydrateTableDefinition(config: CustomFieldConfig | null): TableDefinitionBag {
  if (!config?.columns) {
    return emptyTableDefinition()
  }
  return {
    columns: config.columns.map((column) => ({
      key: column.key,
      label: column.label,
      type: column.type,
      required: Boolean(column.required),
      key_touched: true,
      config: (column.config as Record<string, unknown> | null | undefined) ?? null,
      options: (column.options ?? []).map((option) => ({
        value: option.value,
        label: option.label,
        color: option.color ?? '',
      })),
    })),
    selectable_enabled: Boolean(config.selectable),
    selectable_key: config.selectable?.key ?? '',
    selectable_label: config.selectable?.label ?? '',
    summary_column: config.summary?.column ?? '',
    summary_strategy: config.summary?.strategy ?? '',
    min_rows: config.min_rows ?? null,
    max_rows: config.max_rows ?? null,
  }
}

function toWireColumn(column: TableColumnRow): TableFieldColumn {
  const wire: TableFieldColumn = {
    key: column.key,
    label: column.label,
    type: column.type,
    required: column.required,
  }
  if (column.config && Object.keys(column.config).length > 0) {
    wire.config = column.config as CustomFieldConfig
  }
  if (column.type === 'enum') {
    wire.options = column.options.map<CustomFieldOption>((option) => ({
      value: option.value,
      label: option.label,
      ...(option.color ? { color: option.color } : {}),
    }))
  }
  return wire
}

/** Projects the bag onto `TableFieldConfig`; `strategy: selected` without a selectable is dropped (the schema blocks it before submit). */
export function buildTableConfig(bag: TableDefinitionBag): TableFieldConfig {
  const config: TableFieldConfig = { columns: bag.columns.map(toWireColumn) }
  if (bag.selectable_enabled) {
    config.selectable = { key: bag.selectable_key, label: bag.selectable_label }
  }
  if (bag.summary_column && bag.summary_strategy) {
    config.summary = { column: bag.summary_column, strategy: bag.summary_strategy }
  }
  if (bag.min_rows !== null) {
    config.min_rows = bag.min_rows
  }
  if (bag.max_rows !== null) {
    config.max_rows = bag.max_rows
  }
  return config
}
