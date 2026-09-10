import { render } from '@testing-library/react'
import type { EditableCallbackParams, ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import appI18n from '@/i18n'
import {
  defaultValueFormatter,
  resolveCellRenderer,
  resolveEditableColumnProps,
  type CellRenderer,
} from '@/components/data-table/column-defaults'
import { RelationCellEditor } from '@/components/data-table/relation-cell-editor'
import type { ColumnType, EnumBadge, TableColumn, TableRow } from '@/features/table/types'

/** Minimal TableColumn stub; only the fields under test matter. */
function stubColumn(partial: Partial<TableColumn> & Pick<TableColumn, 'id' | 'type'>): TableColumn {
  return {
    label: 'label',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...partial,
  }
}

const translate = (key: string) => key

const ENUM_BADGES: EnumBadge[] = [
  { value: 'red', label: 'Red', color: 'red', icon: null },
  { value: 'blue', label: 'Blue', color: 'blue', icon: null },
]

function renderCell(renderer: CellRenderer | undefined, value: unknown) {
  if (!renderer) {
    throw new Error('expected a renderer')
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('resolveCellRenderer', () => {
  it('prefers an explicit per-id override, even for a custom enum column', () => {
    const column = stubColumn({ id: 'custom.color', type: 'enum', source: 'custom', badges: ENUM_BADGES })
    const override: CellRenderer = () => <span>overridden</span>
    const renderer = resolveCellRenderer(column, { 'custom.color': override })
    const { getByText } = renderCell(renderer, 'red')
    expect(getByText('overridden')).toBeInTheDocument()
  })

  it('falls back to the agnostic BadgeCell for a native `badge` column', () => {
    const column = stubColumn({ id: 'status', type: 'badge', badges: ENUM_BADGES })
    const renderer = resolveCellRenderer(column, undefined)
    const { getByText } = renderCell(renderer, 'blue')
    expect(getByText('Blue')).toBeInTheDocument()
  })

  it('falls back to the agnostic BadgeCell for a custom `enum` column, driven by column.badges/options', () => {
    const column = stubColumn({
      id: 'custom.color',
      type: 'enum',
      source: 'custom',
      badges: ENUM_BADGES,
      options: ['red', 'blue'],
    })
    const renderer = resolveCellRenderer(column, undefined)
    const { getByText } = renderCell(renderer, 'red')
    expect(getByText('Red')).toBeInTheDocument()
  })

  it('does NOT apply the badge fallback to a native `enum` column (no source: custom)', () => {
    // Native columns never carry type "enum" with badge metadata the way custom
    // fields do — the badge fallback is scoped to source:'custom' to avoid
    // changing any native column's behavior.
    const column = stubColumn({ id: 'kind', type: 'enum', badges: ENUM_BADGES })
    const renderer = resolveCellRenderer(column, undefined)
    expect(renderer).toBeUndefined()
  })

  it('has no renderer for a custom text/relation column (plain label string, default AG Grid cell)', () => {
    const column = stubColumn({ id: 'custom.notes', type: 'text', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })

  it('has no renderer for a custom number column (formatted via valueFormatter instead)', () => {
    const column = stubColumn({ id: 'custom.score', type: 'number', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })

  it('has no renderer for a custom boolean column (formatted via valueFormatter instead)', () => {
    const column = stubColumn({ id: 'custom.active', type: 'boolean', source: 'custom' })
    expect(resolveCellRenderer(column, undefined)).toBeUndefined()
  })
})

describe('defaultValueFormatter', () => {
  it('joins a native `tags` array for display', () => {
    const column = stubColumn({ id: 'roles', type: 'tags' })
    expect(defaultValueFormatter(column, translate)?.(['admin', 'editor'])).toBe('admin, editor')
  })

  // A multiselect enum custom field stores option CODES, not labels — joining
  // them raw would show gibberish like "red, blue" instead of "Red, Blue".
  it('maps a multiselect enum custom field `tags` array through column.badges to readable labels', () => {
    const column = stubColumn({
      id: 'custom.colori_disponibili',
      type: 'tags',
      source: 'custom',
      badges: ENUM_BADGES,
    })
    expect(defaultValueFormatter(column, translate)?.(['red', 'blue'])).toBe('Red, Blue')
  })

  it('falls back to the raw code for a tags entry with no matching badge (defensive)', () => {
    const column = stubColumn({ id: 'custom.colori_disponibili', type: 'tags', source: 'custom', badges: ENUM_BADGES })
    expect(defaultValueFormatter(column, translate)?.(['red', 'unknown'])).toBe('Red, unknown')
  })

  // The backend never resolves a relation-many column to a label catalog
  // (no `badges`): the fallback must keep showing the raw ids, unbroken.
  it('leaves a relation-many `tags` column (no badges) showing the raw ids', () => {
    const column = stubColumn({
      id: 'custom.prodotti_collegati',
      type: 'tags',
      source: 'custom',
      editor: 'multiselect',
    })
    expect(defaultValueFormatter(column, translate)?.([3, 7])).toBe('3, 7')
  })

  it('formats a custom boolean value to a localized yes/no', () => {
    const column = stubColumn({ id: 'custom.active', type: 'boolean', source: 'custom' })
    const format = defaultValueFormatter(column, translate)
    expect(format?.(true)).toBe('common.yes')
    expect(format?.(false)).toBe('common.no')
  })

  it('formats a custom number value with locale separators, blank when invalid', () => {
    const column = stubColumn({ id: 'custom.score', type: 'number', source: 'custom' })
    const format = defaultValueFormatter(column, translate)
    expect(format?.(1234)).toBe(new Intl.NumberFormat(appI18n.language).format(1234))
    // Backend decimal casts can serialize numbers as strings.
    expect(format?.('42.5')).toBe(new Intl.NumberFormat(appI18n.language).format(42.5))
    expect(format?.(null)).toBe('')
  })

  it('leaves a native number column untouched (no formatter — unaffected behavior)', () => {
    const column = stubColumn({ id: 'count', type: 'number' })
    expect(defaultValueFormatter(column, translate)).toBeUndefined()
  })

  it('leaves a native boolean column untouched (no formatter — unaffected behavior)', () => {
    const column = stubColumn({ id: 'is_active', type: 'boolean' })
    expect(defaultValueFormatter(column, translate)).toBeUndefined()
  })

  // `attr.<code>` date attributes reach the grid as a bare `Y-m-d` string: with
  // no formatter they printed the raw wire value instead of the user's pattern
  // (Settings -> System), unlike the native "Prossimo richiamo" column.
  it('formats a date attribute column with the active date pattern, no time', () => {
    const column = stubColumn({
      id: 'attr.data_corso',
      type: 'datetime',
      source: 'attribute',
      filterType: 'date',
      editor: 'date',
    })
    expect(defaultValueFormatter(column, translate)?.('2026-03-10')).toBe('10/03/2026')
  })

  it('keeps the hour of a datetime attribute column, dropping a midnight one', () => {
    const column = stubColumn({
      id: 'attr.appuntamento',
      type: 'datetime',
      source: 'attribute',
      filterType: 'date',
      editor: 'datetime',
    })
    const format = defaultValueFormatter(column, translate)
    expect(format?.('2026-03-10T14:30')).toBe('10/03/2026 14:30')
    expect(format?.('2026-03-10T00:00')).toBe('10/03/2026')
    expect(format?.(null)).toBe('')
  })

  it('has no formatter for a custom text/relation or enum column', () => {
    expect(
      defaultValueFormatter(stubColumn({ id: 'custom.notes', type: 'text', source: 'custom' }), translate),
    ).toBeUndefined()
    expect(
      defaultValueFormatter(stubColumn({ id: 'custom.color', type: 'enum', source: 'custom' }), translate),
    ).toBeUndefined()
  })
})

/** Minimal AG Grid `EditableCallbackParams` stub around a row's `editable` flag. */
function editableParams(row: TableRow | undefined): EditableCallbackParams<TableRow> {
  return { data: row } as EditableCallbackParams<TableRow>
}

describe('resolveEditableColumnProps (spec 0053)', () => {
  it('is read-only when the column itself is not declared editable', () => {
    const column = stubColumn({ id: 'name', type: 'text', editable: false })
    expect(resolveEditableColumnProps(column)).toEqual({ editable: false })
  })

  it('is read-only when the column type has no registered cell editor (AC-023)', () => {
    const column = stubColumn({ id: 'name', type: 'future_type' as ColumnType, editable: true })
    expect(resolveEditableColumnProps(column)).toEqual({ editable: false })
  })

  it('wires the registered editor and an editable callback for an editable column', () => {
    const column = stubColumn({ id: 'name', type: 'text', editable: true })
    const props = resolveEditableColumnProps(column)
    expect(props.cellEditor).toBe('agTextCellEditor')
    expect(typeof props.editable).toBe('function')
  })

  it('the editable callback is true only when the ROW is also editable (AC-017)', () => {
    const column = stubColumn({ id: 'name', type: 'text', editable: true })
    const editableFn = resolveEditableColumnProps(column).editable as (
      params: EditableCallbackParams<TableRow>,
    ) => boolean

    expect(editableFn(editableParams({ id: 1, actions: [], editable: true }))).toBe(true)
    expect(editableFn(editableParams({ id: 1, actions: [], editable: false }))).toBe(false)
    expect(editableFn(editableParams({ id: 1, actions: [] }))).toBe(false)
    expect(editableFn(editableParams(undefined))).toBe(false)
  })
})

describe('resolveEditableColumnProps — relation columns (spec 0054 D-7)', () => {
  it('dispatches to the relation editor when `editor: "relation"` is declared, overriding `type`', () => {
    const column = stubColumn({
      id: 'operator',
      type: 'text',
      editable: true,
      editor: 'relation',
      relation: { resource: 'users' },
    })
    const props = resolveEditableColumnProps(column)
    expect(props.cellEditor).toBe(RelationCellEditor)
    expect(props.cellEditorPopup).toBe(true)
    expect(props.cellEditorParams).toEqual({ resource: 'users', showAvatar: true })
  })

  it('stays read-only when `editor: "relation"` is declared without its target resource (malformed metadata)', () => {
    const column = stubColumn({ id: 'operator', type: 'text', editable: true, editor: 'relation' })
    expect(resolveEditableColumnProps(column)).toEqual({ editable: false })
  })

  it('a plain scalar column (no `editor`) is unaffected and still resolves from `type`', () => {
    const column = stubColumn({ id: 'name', type: 'text', editable: true })
    expect(resolveEditableColumnProps(column).cellEditor).toBe('agTextCellEditor')
  })
})
