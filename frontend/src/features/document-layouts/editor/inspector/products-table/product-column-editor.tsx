import { useId } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ProductColumnLineEditor } from '@/features/document-layouts/editor/inspector/products-table/product-column-line-editor'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import {
  createDefaultProductColumnLine,
  MAX_LINES_PER_PRODUCT_COLUMN,
  WIDTH_PCT_MAX,
  WIDTH_PCT_MIN,
} from '@/features/document-layouts/layout-config-defaults'
import { ALIGN_LCR } from '@/features/document-layouts/layout-config'
import type { AlignLCR, ProductColumn, ProductColumnLine } from '@/features/document-layouts/layout-config'

interface ProductColumnEditorProps {
  column: ProductColumn
  onChange: (next: ProductColumn) => void
  onRemove: () => void
  disabled: boolean
}

/** One `products_table` column: label, width, alignment, and its stacked `lines` (D-11). */
export function ProductColumnEditor({ column, onChange, onRemove, disabled }: ProductColumnEditorProps) {
  const { t } = useTranslation()
  const alignId = useId()
  const linesFull = column.lines.length >= MAX_LINES_PER_PRODUCT_COLUMN

  function updateLine(index: number, next: ProductColumnLine) {
    onChange({ ...column, lines: column.lines.map((line, i) => (i === index ? next : line)) })
  }

  function removeLine(index: number) {
    onChange({ ...column, lines: column.lines.filter((_, i) => i !== index) })
  }

  function addLine() {
    if (linesFull) {
      return
    }
    onChange({ ...column, lines: [...column.lines, createDefaultProductColumnLine()] })
  }

  return (
    <div className="flex flex-col gap-2 rounded-md border border-border p-2">
      <div className="flex items-center gap-2">
        <Input
          value={column.label}
          disabled={disabled}
          placeholder={t('documentLayouts.editor.productsTable.columnLabel')}
          aria-label={t('documentLayouts.editor.productsTable.columnLabel')}
          onChange={(event) => onChange({ ...column, label: event.target.value })}
          className="h-7 flex-1 text-xs"
        />
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="shrink-0 text-muted-foreground hover:text-destructive"
          aria-label={t('documentLayouts.editor.productsTable.removeColumn')}
          disabled={disabled}
          onClick={onRemove}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>

      <div className="flex items-end gap-2">
        <IntegerField
          label={t('documentLayouts.editor.productsTable.widthPct')}
          className="w-20"
          value={column.width_pct}
          min={WIDTH_PCT_MIN}
          max={WIDTH_PCT_MAX}
          onCommit={(width_pct) => onChange({ ...column, width_pct })}
          disabled={disabled}
        />
        <div className="flex flex-1 flex-col gap-1">
          <label htmlFor={alignId} className="text-xs text-muted-foreground">
            {t('documentLayouts.editor.productsTable.align')}
          </label>
          <Select value={column.align} onValueChange={(value) => onChange({ ...column, align: value as AlignLCR })} disabled={disabled}>
            <SelectTrigger id={alignId} className="h-7 w-full text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ALIGN_LCR.map((align) => (
                <SelectItem key={align} value={align}>
                  {t(`documentLayouts.editor.image.aligns.${align}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="flex flex-col gap-1.5">
        {column.lines.map((line, index) => (
          <ProductColumnLineEditor
            key={index}
            line={line}
            onChange={(next) => updateLine(index, next)}
            onRemove={() => removeLine(index)}
            canRemove={column.lines.length > 1}
            disabled={disabled}
          />
        ))}
      </div>
      <Button type="button" variant="outline" size="xs" disabled={disabled || linesFull} onClick={addLine}>
        <Plus aria-hidden="true" />
        {t('documentLayouts.editor.productsTable.addLine')}
      </Button>
    </div>
  )
}
