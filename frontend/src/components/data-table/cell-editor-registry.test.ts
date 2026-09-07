import { describe, expect, it } from 'vitest'
import {
  CELL_EDITOR_REGISTRY,
  resolveCellEditorSpec,
  type CellEditorKind,
} from '@/components/data-table/cell-editor-registry'
import { DateTimeCellEditor } from '@/components/data-table/datetime-cell-editor'
import { OfferLinesCellEditor } from '@/features/request-management/offer-lines-cell-editor'
import { ProductLinesCellEditor } from '@/features/product-lines/product-lines-cell-editor'
import { MultiSelectCellEditor } from '@/components/data-table/multi-select-cell-editor'
import { RelationCellEditor } from '@/components/data-table/relation-cell-editor'
import { SelectCellEditor } from '@/components/data-table/select-cell-editor'
import type { ColumnType, EnumBadge, SelectOption, TableColumn, TableRow } from '@/features/table/types'

/** A `RichCellEditorValuesCallback` param stub: only `data` (the editing row) is read. */
function valuesParams(data: TableRow | undefined) {
  return { data } as { data?: TableRow }
}

/** Minimal TableColumn stub; only the fields the registry reads matter. */
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

const ENUM_BADGES: EnumBadge[] = [
  { value: 'open', label: 'Open', color: 'blue', icon: null },
  { value: 'closed', label: 'Closed', color: 'slate', icon: null },
]

describe('resolveCellEditorSpec', () => {
  it('has an entry for every declared ColumnType (AC-023)', () => {
    const types: ColumnType[] = ['text', 'number', 'datetime', 'enum', 'tags', 'badge', 'boolean']
    for (const type of types) {
      expect(resolveCellEditorSpec(type)).toBeDefined()
    }
  })

  it('resolves to `undefined` for an unregistered type instead of throwing (AC-023)', () => {
    expect(() => resolveCellEditorSpec('future_type' as ColumnType)).not.toThrow()
    expect(resolveCellEditorSpec('future_type' as ColumnType)).toBeUndefined()
  })

  it('maps text/number/boolean to their plain built-in editors', () => {
    expect(resolveCellEditorSpec('text')?.cellEditor).toBe('agTextCellEditor')
    expect(resolveCellEditorSpec('number')?.cellEditor).toBe('agNumberCellEditor')
    expect(resolveCellEditorSpec('boolean')?.cellEditor).toBe('agCheckboxCellEditor')
  })

  // Requirement CHANGED by spec 0055 D-4: `datetime` used to resolve to
  // `agTextCellEditor` (retyping the raw wire string by hand); it now resolves
  // to this repo's own popup picker, on the generic kind so every domain gets it.
  it('maps datetime to the popup date/time picker (spec 0055 D-4)', () => {
    const spec = resolveCellEditorSpec('datetime')
    expect(spec?.cellEditor).toBe(DateTimeCellEditor)
    expect(spec?.cellEditorPopup).toBe(true)
  })

  it('maps select to the popup listbox, fed by the column options and its id', () => {
    const options: SelectOption[] = [
      { value: 1, label: 'Da contattare', requires_note: false },
      { value: 2, label: 'Chiusa', requires_note: true },
    ]
    const spec = resolveCellEditorSpec('select')
    expect(spec?.cellEditor).toBe(SelectCellEditor)
    expect(spec?.cellEditorPopup).toBe(true)
    expect(spec?.cellEditorParams?.(stubColumn({ id: 'workflow_status', type: 'text', options }))).toEqual({
      columnId: 'workflow_status',
      options,
    })
  })

  // Spec 0064: the new `date` kind — a Product Category attribute of type
  // `date` (no time component) reuses the `datetime` popup picker, told to
  // render `type="date"` via `dateOnly`.
  it('maps date to the same popup picker as datetime, with dateOnly on', () => {
    const spec = resolveCellEditorSpec('date')
    expect(spec?.cellEditor).toBe(DateTimeCellEditor)
    expect(spec?.cellEditorPopup).toBe(true)
    expect(spec?.cellEditorParams?.(stubColumn({ id: 'attr.data_inizio', type: 'text' }))).toEqual({
      dateOnly: true,
    })
  })

  it('drops scalar options from a select column instead of coercing them', () => {
    const spec = resolveCellEditorSpec('select')
    const params = spec?.cellEditorParams?.(
      stubColumn({ id: 'workflow_status', type: 'text', options: ['open', 'closed'] }),
    ) as { options: SelectOption[] }
    expect(params.options).toEqual([])
  })

  it('maps enum/badge to a single-select rich editor sourced from column.options', () => {
    const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'], badges: ENUM_BADGES })
    const spec = resolveCellEditorSpec('badge')
    expect(spec?.cellEditor).toBe('agRichSelectCellEditor')
    const params = spec?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
      multiSelect: boolean
    }
    expect(params.values(valuesParams(undefined))).toEqual(['open', 'closed'])
    expect(params.multiSelect).toBe(false)
  })

  it('maps tags to a multi-select rich editor', () => {
    const column = stubColumn({ id: 'roles', type: 'tags', options: ['admin', 'editor'] })
    const spec = resolveCellEditorSpec('tags')
    const params = spec?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
      multiSelect: boolean
    }
    expect(params.values(valuesParams(undefined))).toEqual(['admin', 'editor'])
    expect(params.multiSelect).toBe(true)
  })

  it('falls back to an empty option list when the column has none', () => {
    const column = stubColumn({ id: 'status', type: 'enum' })
    const params = resolveCellEditorSpec('enum')?.cellEditorParams?.(column) as {
      values: (p: { data?: TableRow }) => string[]
    }
    expect(params.values(valuesParams(undefined))).toEqual([])
  })

  it('formats an enum/badge option through the column badges, raw value otherwise', () => {
    const column = stubColumn({ id: 'status', type: 'badge', badges: ENUM_BADGES })
    const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
      formatValue: (value: unknown) => string
    }
    expect(params.formatValue('open')).toBe('Open')
    expect(params.formatValue('unknown-value')).toBe('unknown-value')
  })

  it('every registry entry is reachable through the defensive lookup', () => {
    for (const kind of Object.keys(CELL_EDITOR_REGISTRY) as CellEditorKind[]) {
      expect(resolveCellEditorSpec(kind)).toBe(CELL_EDITOR_REGISTRY[kind])
    }
  })

  describe('row-scoped options (spec 0054 follow-up, AC-026/027)', () => {
    it("filters the catalog down to the row's <columnId>_options when present", () => {
      const column = stubColumn({ id: 'workflow_status', type: 'badge', options: ['1', '2', '3'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], workflow_status_options: [1, 3] }

      expect(params.values(valuesParams(row))).toEqual(['1', '3'])
    })

    it('offers the full catalog when the row carries no <columnId>_options (no regression)', () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [] }

      expect(params.values(valuesParams(row))).toEqual(['open', 'closed'])
    })

    it('offers the full catalog when there is no row at all (defensive)', () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }

      expect(params.values(valuesParams(undefined))).toEqual(['open', 'closed'])
    })

    it("is driven by the row's shape, never by column id: any column adopting the convention gets filtered", () => {
      const column = stubColumn({ id: 'anything', type: 'enum', options: ['a', 'b', 'c'] })
      const params = resolveCellEditorSpec('enum')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], anything_options: ['b'] }

      expect(params.values(valuesParams(row))).toEqual(['b'])
    })

    it("ignores a malformed <columnId>_options that isn't an array (defensive, falls back to the full catalog)", () => {
      const column = stubColumn({ id: 'status', type: 'badge', options: ['open', 'closed'] })
      const params = resolveCellEditorSpec('badge')?.cellEditorParams?.(column) as {
        values: (p: { data?: TableRow }) => string[]
      }
      const row: TableRow = { id: 1, actions: [], status_options: 'not-an-array' }

      expect(params.values(valuesParams(row))).toEqual(['open', 'closed'])
    })
  })

  describe('relation kind (spec 0054 D-7)', () => {
    it('maps to the custom RelationCellEditor component, rendered as a popup', () => {
      const spec = resolveCellEditorSpec('relation')
      expect(spec?.cellEditor).toBe(RelationCellEditor)
      expect(spec?.cellEditorPopup).toBe(true)
    })

    it('forwards the column\'s declared for-select resource as cellEditorParams (D-1)', () => {
      const column = stubColumn({ id: 'operator', type: 'text', relation: { resource: 'users' } })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({ resource: 'users', showAvatar: true })
    })

    it('turns avatars off for a non-people resource', () => {
      const column = stubColumn({ id: 'registry', type: 'text', relation: { resource: 'registries' } })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({ resource: 'registries', showAvatar: false })
    })

    it('falls back to an empty resource string when the column carries none (defensive)', () => {
      const column = stubColumn({ id: 'operator', type: 'text' })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({ resource: '', showAvatar: false })
    })

    // User directive 2026-07-23: the to-many twin — same `relation` metadata,
    // its own popup editor, no avatar opt-in (the resource is never people).
    it('resolves a `multiselect` column to the popup multi-select editor with its resource and scope', () => {
      const column = stubColumn({
        id: 'products_of_interest',
        type: 'text',
        relation: { resource: 'products', scope: { category_ids: 'product_category_ids' } },
      })
      const spec = resolveCellEditorSpec('multiselect')

      expect(spec?.cellEditor).toBe(MultiSelectCellEditor)
      expect(spec?.cellEditorPopup).toBe(true)
      expect(spec?.cellEditorParams?.(column)).toEqual({
        resource: 'products',
        scope: { category_ids: 'product_category_ids' },
        // Spec 0075, D-4: absent on the column ⇒ the unlock stays available.
        // Generic default: the products-of-interest column itself declares
        // `lockScope` on BOTH domains since the user directive 2026-08-05.
        lockScope: false,
      })
    })

    // Spec 0075, D-4: a column that declares it makes the editor drop its
    // whole-catalogue escape — the module refuses what falls outside the scope.
    it('forwards `lockScope` when the column declares it', () => {
      const column = stubColumn({
        id: 'products_of_interest',
        type: 'text',
        relation: { resource: 'products', scope: { category_ids: 'product_category_ids' }, lockScope: true },
      })

      expect(resolveCellEditorSpec('multiselect')?.cellEditorParams?.(column)).toMatchObject({ lockScope: true })
    })

    // Spec 0075: the {funzione aziendale, categoria prodotto} collection.
    it('resolves a `product_lines` column to the popup pair editor', () => {
      const spec = resolveCellEditorSpec('product_lines')

      expect(spec?.cellEditor).toBe(ProductLinesCellEditor)
      expect(spec?.cellEditorPopup).toBe(true)
      expect(spec?.cellEditorParams).toBeUndefined()
    })

    // User directive 2026-09-07: an offer's whole REVENUE collection. Its
    // editor renders nothing in the grid — it delegates to a dialog, since the
    // row's pickers cannot live inside a cell popup — hence NO
    // `cellEditorPopup`: there is no popup to lay out.
    it('resolves an `offer_lines` column to the editor that delegates to the dialog', () => {
      const spec = resolveCellEditorSpec('offer_lines')

      expect(spec?.cellEditor).toBe(OfferLinesCellEditor)
      expect(spec?.cellEditorPopup).toBeUndefined()
      expect(spec?.cellEditorParams).toBeUndefined()
    })

    // Forwarded UNRESOLVED on purpose: it names the column to read, and only
    // the editor holds the row (user directive 2026-07-23).
    it('forwards the row-scoped `scope` map when the column declares one', () => {
      const column = stubColumn({
        id: 'operator_ga2',
        type: 'text',
        relation: { resource: 'users', scope: { operational_site_id: 'operational_site' } },
      })
      const params = resolveCellEditorSpec('relation')?.cellEditorParams?.(column)
      expect(params).toEqual({
        resource: 'users',
        showAvatar: true,
        scope: { operational_site_id: 'operational_site' },
      })
    })
  })
})
