import { Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { createDefaultTotalsRow, MAX_TOTALS_ROWS } from '@/features/document-layouts/layout-config-defaults'
import type { ProductsTableTotals, ProductsTableTotalsRow } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

/** The category whose variables are the only ones valid in `totals.rows[].variable` (spec 0069 `config_schema`). */
const TOTALS_CATEGORY_KEY = 'totals'

interface ProductsTableTotalsEditorProps {
  totals: ProductsTableTotals
  variablesCatalog?: DocumentLayoutVariablesCatalog
  onChange: (next: ProductsTableTotals) => void
  disabled: boolean
}

/** `products_table.totals`: show toggle + rows, each bound to a `totals.*` variable (spec 0069 `config_schema`). */
export function ProductsTableTotalsEditor({ totals, variablesCatalog, onChange, disabled }: ProductsTableTotalsEditorProps) {
  const { t } = useTranslation()
  const totalsVariables = variablesCatalog?.categories.find((category) => category.key === TOTALS_CATEGORY_KEY)?.variables ?? []
  const rowsFull = totals.rows.length >= MAX_TOTALS_ROWS

  function updateRow(index: number, next: ProductsTableTotalsRow) {
    onChange({ ...totals, rows: totals.rows.map((row, i) => (i === index ? next : row)) })
  }

  function removeRow(index: number) {
    onChange({ ...totals, rows: totals.rows.filter((_, i) => i !== index) })
  }

  function addRow() {
    if (rowsFull) {
      return
    }
    onChange({ ...totals, rows: [...totals.rows, createDefaultTotalsRow()] })
  }

  return (
    <div className="flex flex-col gap-2 border-t border-border pt-2">
      <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Checkbox checked={totals.show} disabled={disabled} onCheckedChange={(value) => onChange({ ...totals, show: value === true })} />
        {t('documentLayouts.editor.productsTable.showTotals')}
      </label>
      {totals.show && (
        <>
          {totals.rows.map((row, index) => (
            <div key={index} className="flex items-center gap-1.5">
              <Input
                value={row.label}
                disabled={disabled}
                placeholder={t('documentLayouts.editor.productsTable.totalsRowLabel')}
                aria-label={t('documentLayouts.editor.productsTable.totalsRowLabel')}
                onChange={(event) => updateRow(index, { ...row, label: event.target.value })}
                className="h-7 flex-1 text-xs"
              />
              <Select value={row.variable} onValueChange={(value) => updateRow(index, { ...row, variable: value })} disabled={disabled}>
                <SelectTrigger className="h-7 flex-1 text-xs" aria-label={t('documentLayouts.editor.productsTable.totalsRowVariable')}>
                  <SelectValue placeholder={t('documentLayouts.editor.productsTable.totalsRowVariable')} />
                </SelectTrigger>
                <SelectContent>
                  {totalsVariables.map((variable) => (
                    <SelectItem key={variable.variable} value={variable.variable}>
                      {variable.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <label className="flex items-center gap-1 text-xs text-muted-foreground">
                <Checkbox
                  checked={row.bold}
                  disabled={disabled}
                  onCheckedChange={(value) => updateRow(index, { ...row, bold: value === true })}
                />
                {t('documentLayouts.editor.textBlock.bold')}
              </label>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="text-muted-foreground hover:text-destructive"
                aria-label={t('documentLayouts.editor.productsTable.removeTotalsRow')}
                disabled={disabled}
                onClick={() => removeRow(index)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
          ))}
          <Button type="button" variant="outline" size="xs" disabled={disabled || rowsFull} onClick={addRow}>
            <Plus aria-hidden="true" />
            {t('documentLayouts.editor.productsTable.addTotalsRow')}
          </Button>
        </>
      )}
    </div>
  )
}
