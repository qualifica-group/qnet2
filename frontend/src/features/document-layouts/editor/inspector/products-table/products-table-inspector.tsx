import { useId, useState } from 'react'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SortableList } from '@/components/ui/sortable-list'
import { ProductColumnEditor } from '@/features/document-layouts/editor/inspector/products-table/product-column-editor'
import { ProductsTableTotalsEditor } from '@/features/document-layouts/editor/inspector/products-table/products-table-totals-editor'
import { HexColorField } from '@/features/document-layouts/editor/shared/hex-color-field'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import {
  createDefaultProductColumn,
  MAX_PRODUCT_COLUMNS,
  WIDTH_PCT_MAX,
  WIDTH_PCT_MIN,
} from '@/features/document-layouts/layout-config-defaults'
import { PRODUCTS_TABLE_SOURCES } from '@/features/document-layouts/layout-config'
import type { ProductColumn, ProductsTableBlock, ProductsTableSource } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

const DEFAULT_HEADER_BACKGROUND = 'DDDDDD'

interface ProductsTableInspectorProps {
  block: ProductsTableBlock
  variablesCatalog?: DocumentLayoutVariablesCatalog
  onChange: (next: ProductsTableBlock) => void
  disabled: boolean
}

interface ColumnEntry {
  /** Client-only, drag-tracking id — `ProductColumn` itself has no `id` field in the frozen contract. */
  id: string
  column: ProductColumn
}

/**
 * The `products_table` block editor (AC-123): source, columns (chosen,
 * reordered via the same `SortableList` drag pattern as the zones, renamed),
 * and totals. `ProductColumn` has no `id` in the contract, so this component
 * is the sole owner of a small local id<->column correlation used only to
 * key the drag list — every mutation re-derives `block.columns` from it and
 * calls `onChange`, so the saved config never carries the synthetic id.
 */
export function ProductsTableInspector({ block, variablesCatalog, onChange, disabled }: ProductsTableInspectorProps) {
  const { t } = useTranslation()
  const sourceId = useId()
  const [entries, setEntries] = useState<ColumnEntry[]>(() =>
    block.columns.map((column) => ({ id: crypto.randomUUID(), column })),
  )
  const columnsFull = entries.length >= MAX_PRODUCT_COLUMNS

  function emit(nextEntries: ColumnEntry[]) {
    setEntries(nextEntries)
    onChange({ ...block, columns: nextEntries.map((entry) => entry.column) })
  }

  function handleReorder(orderedIds: string[]) {
    const byId = new Map(entries.map((entry) => [entry.id, entry]))
    emit(orderedIds.map((id) => byId.get(id)).filter((entry): entry is ColumnEntry => entry !== undefined))
  }

  function addColumn() {
    if (columnsFull) {
      return
    }
    emit([...entries, { id: crypto.randomUUID(), column: createDefaultProductColumn() }])
  }

  function removeColumn(id: string) {
    emit(entries.filter((entry) => entry.id !== id))
  }

  function updateColumn(id: string, next: ProductColumn) {
    emit(entries.map((entry) => (entry.id === id ? { id, column: next } : entry)))
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-2 gap-2">
        <div className="flex flex-col gap-1">
          <label htmlFor={sourceId} className="text-xs text-muted-foreground">
            {t('documentLayouts.editor.productsTable.source')}
          </label>
          <Select
            value={block.source}
            onValueChange={(value) => onChange({ ...block, source: value as ProductsTableSource })}
            disabled={disabled}
          >
            <SelectTrigger id={sourceId} className="h-7 w-full text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PRODUCTS_TABLE_SOURCES.map((source) => (
                <SelectItem key={source} value={source}>
                  {t(`documentLayouts.editor.productsTable.sources.${source}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <IntegerField
          label={t('documentLayouts.editor.productsTable.widthPct')}
          value={block.width_pct}
          min={WIDTH_PCT_MIN}
          max={WIDTH_PCT_MAX}
          onCommit={(width_pct) => onChange({ ...block, width_pct })}
          disabled={disabled}
        />
      </div>

      <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Checkbox
          checked={block.show_header}
          disabled={disabled}
          onCheckedChange={(value) => onChange({ ...block, show_header: value === true })}
        />
        {t('documentLayouts.editor.productsTable.showHeader')}
      </label>
      {block.show_header && (
        <HexColorField
          label={t('documentLayouts.editor.productsTable.headerBackground')}
          value={block.header_background ?? DEFAULT_HEADER_BACKGROUND}
          onCommit={(header_background) => onChange({ ...block, header_background })}
          disabled={disabled}
        />
      )}

      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <h4 className="text-xs font-semibold text-foreground">{t('documentLayouts.editor.productsTable.columns')}</h4>
          <Button type="button" variant="outline" size="xs" disabled={disabled || columnsFull} onClick={addColumn}>
            <Plus aria-hidden="true" />
            {t('documentLayouts.editor.productsTable.addColumn')}
          </Button>
        </div>
        <SortableList
          items={entries}
          dragHandleLabel={t('documentLayouts.editor.dragHandle')}
          onReorder={handleReorder}
          renderItem={(entry) => (
            <ProductColumnEditor
              column={entry.column}
              onChange={(next) => updateColumn(entry.id, next)}
              onRemove={() => removeColumn(entry.id)}
              disabled={disabled}
            />
          )}
        />
      </div>

      <ProductsTableTotalsEditor
        totals={block.totals}
        variablesCatalog={variablesCatalog}
        onChange={(totals) => onChange({ ...block, totals })}
        disabled={disabled}
      />
    </div>
  )
}
