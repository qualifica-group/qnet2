import { Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { createBlockId } from '@/features/document-layouts/editor/document-layout-editor-state'
import { HexColorField } from '@/features/document-layouts/editor/shared/hex-color-field'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import {
  createDefaultRun,
  createDefaultTableCell,
  createDefaultTableRow,
  createDefaultTextBlock,
  MAX_TABLE_COLUMNS,
  MAX_TABLE_ROWS,
  WIDTH_PCT_MAX,
  WIDTH_PCT_MIN,
} from '@/features/document-layouts/layout-config-defaults'
import type { TableBlock, TableCell } from '@/features/document-layouts/layout-config'

const DEFAULT_BORDER_SIZE = 4
const DEFAULT_BORDER_COLOR = '000000'

interface TableBlockInspectorProps {
  block: TableBlock
  onChange: (next: TableBlock) => void
  disabled: boolean
}

function cellText(cell: TableCell): string {
  return cell.blocks[0]?.runs[0]?.text ?? ''
}

/** Rewrites a cell's content to a single `text` block with a single plain run holding `text`. */
function withCellText(cell: TableCell, text: string): TableCell {
  const block = cell.blocks[0] ?? createDefaultTextBlock(createBlockId())
  const run = block.runs[0] ?? createDefaultRun()
  return { ...cell, blocks: [{ ...block, runs: [{ ...run, text }] }] }
}

/**
 * The static `table` block editor: row/column count, borders, and per-cell
 * plain text. Each cell is modeled as a single `text` block with a single
 * run — the contract allows richer per-run formatting inside a cell, but a
 * static table's cell content is a secondary concern next to the ACs this
 * spec actually requires (AC-121/AC-123/AC-124/AC-126/AC-127); this keeps
 * the editor usable without ballooning scope, and the shape stays a fully
 * valid `TextBlock[]` either way.
 */
export function TableBlockInspector({ block, onChange, disabled }: TableBlockInspectorProps) {
  const { t } = useTranslation()
  const columnCount = block.columns.length

  function addColumn() {
    if (block.columns.length >= MAX_TABLE_COLUMNS) {
      return
    }
    onChange({
      ...block,
      columns: [...block.columns, { width_pct: Math.floor(WIDTH_PCT_MAX / (columnCount + 1)) }],
      rows: block.rows.map((row) => ({ ...row, cells: [...row.cells, createDefaultTableCell()] })),
    })
  }

  function removeColumn() {
    if (block.columns.length === 0) {
      return
    }
    onChange({
      ...block,
      columns: block.columns.slice(0, -1),
      rows: block.rows.map((row) => ({ ...row, cells: row.cells.slice(0, -1) })),
    })
  }

  function addRow() {
    if (block.rows.length >= MAX_TABLE_ROWS) {
      return
    }
    onChange({ ...block, rows: [...block.rows, createDefaultTableRow(columnCount)] })
  }

  function removeRow(index: number) {
    onChange({ ...block, rows: block.rows.filter((_, i) => i !== index) })
  }

  function updateCellText(rowIndex: number, cellIndex: number, text: string) {
    onChange({
      ...block,
      rows: block.rows.map((row, ri) =>
        ri === rowIndex
          ? { ...row, cells: row.cells.map((cell, ci) => (ci === cellIndex ? withCellText(cell, text) : cell)) }
          : row,
      ),
    })
  }

  function toggleHeaderRow(rowIndex: number) {
    onChange({
      ...block,
      rows: block.rows.map((row, ri) => (ri === rowIndex ? { ...row, is_header: !row.is_header } : row)),
    })
  }

  const bordersEnabled = block.borders !== null

  return (
    <div className="flex flex-col gap-3">
      <IntegerField
        label={t('documentLayouts.editor.table.widthPct')}
        value={block.width_pct}
        min={WIDTH_PCT_MIN}
        max={WIDTH_PCT_MAX}
        onCommit={(width_pct) => onChange({ ...block, width_pct })}
        disabled={disabled}
      />

      <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <input
          type="checkbox"
          checked={bordersEnabled}
          disabled={disabled}
          onChange={(event) =>
            onChange({
              ...block,
              borders: event.target.checked ? { size: DEFAULT_BORDER_SIZE, color: DEFAULT_BORDER_COLOR } : null,
            })
          }
        />
        {t('documentLayouts.editor.table.borders')}
      </label>
      {block.borders && (
        <div className="grid grid-cols-2 gap-2">
          <IntegerField
            label={t('documentLayouts.editor.table.borderSize')}
            value={block.borders.size}
            min={0}
            max={96}
            onCommit={(size) => onChange({ ...block, borders: { size, color: block.borders?.color ?? DEFAULT_BORDER_COLOR } })}
            disabled={disabled}
          />
          <HexColorField
            label={t('documentLayouts.editor.table.borderColor')}
            value={block.borders.color}
            onCommit={(color) => onChange({ ...block, borders: { size: block.borders?.size ?? DEFAULT_BORDER_SIZE, color } })}
            disabled={disabled}
          />
        </div>
      )}

      <div className="flex flex-wrap gap-1.5">
        <Button type="button" variant="outline" size="xs" disabled={disabled} onClick={addColumn}>
          <Plus aria-hidden="true" />
          {t('documentLayouts.editor.table.addColumn')}
        </Button>
        <Button type="button" variant="outline" size="xs" disabled={disabled || columnCount === 0} onClick={removeColumn}>
          <Trash2 aria-hidden="true" />
          {t('documentLayouts.editor.table.removeColumn')}
        </Button>
        <Button type="button" variant="outline" size="xs" disabled={disabled} onClick={addRow}>
          <Plus aria-hidden="true" />
          {t('documentLayouts.editor.table.addRow')}
        </Button>
      </div>

      <div className="flex flex-col gap-2">
        {block.rows.map((row, rowIndex) => (
          <div key={rowIndex} className="flex flex-col gap-1 rounded-md border border-border p-2">
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <input type="checkbox" checked={row.is_header} disabled={disabled} onChange={() => toggleHeaderRow(rowIndex)} />
                {t('documentLayouts.editor.table.headerRow')}
              </label>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="text-muted-foreground hover:text-destructive"
                aria-label={t('documentLayouts.editor.table.removeRow')}
                disabled={disabled}
                onClick={() => removeRow(rowIndex)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
            <div className="grid gap-1" style={{ gridTemplateColumns: `repeat(${Math.max(columnCount, 1)}, minmax(0, 1fr))` }}>
              {row.cells.map((cell, cellIndex) => (
                <Textarea
                  key={cellIndex}
                  value={cellText(cell)}
                  disabled={disabled}
                  rows={1}
                  aria-label={t('documentLayouts.editor.table.cellLabel', { row: rowIndex + 1, column: cellIndex + 1 })}
                  onChange={(event) => updateCellText(rowIndex, cellIndex, event.target.value)}
                  className="text-xs"
                />
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
