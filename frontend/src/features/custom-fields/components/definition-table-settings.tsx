import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import {
  TABLE_MAX_ROWS,
  type TableColumnRow,
  type TableDefinitionBag,
} from '@/features/custom-fields/field-definition-table'
import type { TableSummaryStrategy } from '@/features/custom-fields/types'

/** Radix `Select` cannot hold `''`, so "no summary" travels as this sentinel. */
const NO_SUMMARY = '__none__'
const SUMMARY_STRATEGIES: readonly TableSummaryStrategy[] = ['selected', 'max', 'min']

interface DefinitionTableSettingsProps {
  control: Control<FieldDefinitionFormValues>
  table: TableDefinitionBag
  summaryColumns: TableColumnRow[]
  onSelectableChange: (enabled: boolean) => void
  onSummaryColumnChange: (key: string) => void
}

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/** Row selection, grid summary and row-count limits of a `table` definition. */
export function DefinitionTableSettings({
  control,
  table,
  summaryColumns,
  onSelectableChange,
  onSummaryColumnChange,
}: DefinitionTableSettingsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-2 rounded-lg border bg-muted/40 p-2.5">
        <label className="flex items-center gap-2 text-xs font-medium">
          <Checkbox checked={table.selectable_enabled} onCheckedChange={(checked) => onSelectableChange(checked === true)} />
          {t('customFields.tableEditor.selectable')}
        </label>
        {table.selectable_enabled ? (
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <FormField
              control={control}
              name="table.selectable_label"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-xs">{t('customFields.tableEditor.selectableLabel')}</FormLabel>
                  <FormControl>
                    <Input className="h-8 text-sm" autoComplete="off" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={control}
              name="table.selectable_key"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-xs">{t('customFields.tableEditor.selectableKey')}</FormLabel>
                  <FormControl>
                    <Input className="h-8 font-mono text-xs" autoComplete="off" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        ) : null}
      </div>

      <div className="flex flex-col gap-2 rounded-lg border bg-muted/40 p-2.5">
        <span className="text-xs font-medium">{t('customFields.tableEditor.summary')}</span>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <FormItem>
            <FormLabel className="text-xs">{t('customFields.tableEditor.summaryColumn')}</FormLabel>
            <Select
              value={table.summary_column === '' ? NO_SUMMARY : table.summary_column}
              onValueChange={(next) => onSummaryColumnChange(next === NO_SUMMARY ? '' : next)}
            >
              <SelectTrigger className="h-8 w-full text-xs" aria-label={t('customFields.tableEditor.summaryColumn')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NO_SUMMARY}>{t('customFields.tableEditor.summaryNone')}</SelectItem>
                {summaryColumns.map((column) => (
                  <SelectItem key={column.key} value={column.key}>
                    {column.label || column.key}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </FormItem>
          {table.summary_column !== '' ? (
            <FormField
              control={control}
              name="table.summary_strategy"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-xs">{t('customFields.tableEditor.summaryStrategy')}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="h-8 w-full text-xs">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {SUMMARY_STRATEGIES.map((strategy) => (
                        <SelectItem
                          key={strategy}
                          value={strategy}
                          disabled={strategy === 'selected' && !table.selectable_enabled}
                        >
                          {t(`customFields.tableEditor.strategies.${strategy}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          ) : null}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <FormField
          control={control}
          name="table.min_rows"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.minRows')}</FormLabel>
              <FormControl>
                <Input
                  className="h-8 text-sm"
                  type="number"
                  min={0}
                  max={TABLE_MAX_ROWS}
                  value={numberInputValue(field.value)}
                  onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="table.max_rows"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.maxRows')}</FormLabel>
              <FormControl>
                <Input
                  className="h-8 text-sm"
                  type="number"
                  min={1}
                  max={TABLE_MAX_ROWS}
                  value={numberInputValue(field.value)}
                  onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </div>
  )
}
