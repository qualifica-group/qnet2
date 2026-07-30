import { useId } from 'react'
import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import { FONT_SIZE_MAX, FONT_SIZE_MIN, MAX_KEYS_PER_LINE } from '@/features/document-layouts/layout-config-defaults'
import { COLUMN_KEYS } from '@/features/document-layouts/layout-config'
import type { ColumnKey, ProductColumnLine } from '@/features/document-layouts/layout-config'

interface ProductColumnLineEditorProps {
  line: ProductColumnLine
  onChange: (next: ProductColumnLine) => void
  onRemove: () => void
  canRemove: boolean
  disabled: boolean
}

/**
 * One `products_table` column line (D-11: a column's cell content stacks
 * `lines` paragraphs, each composed from `keys` joined by `separator`).
 * Keys come from the CLOSED `COLUMN_KEYS` allow-list — `discount` is not a
 * member of that list (D-4), so it can never appear here (AC-123).
 */
export function ProductColumnLineEditor({ line, onChange, onRemove, canRemove, disabled }: ProductColumnLineEditorProps) {
  const { t } = useTranslation()
  const separatorId = useId()
  const keysFull = line.keys.length >= MAX_KEYS_PER_LINE

  function toggleKey(key: ColumnKey, checked: boolean) {
    if (checked) {
      if (keysFull) {
        return
      }
      onChange({ ...line, keys: [...line.keys, key] })
      return
    }
    onChange({ ...line, keys: line.keys.filter((existing) => existing !== key) })
  }

  return (
    <div className="flex flex-col gap-1.5 rounded-md border border-border p-2">
      <div className="flex flex-wrap gap-x-3 gap-y-1">
        {COLUMN_KEYS.map((key) => {
          const checked = line.keys.includes(key)
          return (
            <label key={key} className="flex items-center gap-1 text-xs text-muted-foreground">
              <Checkbox
                checked={checked}
                disabled={disabled || (!checked && keysFull)}
                onCheckedChange={(value) => toggleKey(key, value === true)}
              />
              {t(`documentLayouts.editor.productsTable.columnKeys.${key}`)}
            </label>
          )
        })}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex flex-col gap-1">
          <label className="text-xs text-muted-foreground" htmlFor={separatorId}>
            {t('documentLayouts.editor.productsTable.separator')}
          </label>
          <Input
            id={separatorId}
            value={line.separator}
            disabled={disabled}
            onChange={(event) => onChange({ ...line, separator: event.target.value })}
            className="h-7 w-16 text-xs"
          />
        </div>
        <label className="flex items-center gap-1 text-xs text-muted-foreground">
          <Checkbox checked={line.bold} disabled={disabled} onCheckedChange={(value) => onChange({ ...line, bold: value === true })} />
          {t('documentLayouts.editor.textBlock.bold')}
        </label>
        <label className="flex items-center gap-1 text-xs text-muted-foreground">
          <Checkbox
            checked={line.italic}
            disabled={disabled}
            onCheckedChange={(value) => onChange({ ...line, italic: value === true })}
          />
          {t('documentLayouts.editor.textBlock.italic')}
        </label>
        <IntegerField
          label=""
          ariaLabel={t('documentLayouts.editor.textBlock.size')}
          className="w-16"
          value={line.size ?? FONT_SIZE_MIN}
          min={FONT_SIZE_MIN}
          max={FONT_SIZE_MAX}
          onCommit={(size) => onChange({ ...line, size })}
          disabled={disabled}
        />
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="ml-auto text-muted-foreground hover:text-destructive"
          aria-label={t('documentLayouts.editor.productsTable.removeLine')}
          disabled={disabled || !canRemove}
          onClick={onRemove}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>
    </div>
  )
}
