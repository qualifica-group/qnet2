import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DefinitionTableColumnOptions } from '@/features/custom-fields/components/definition-table-column-options'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import { TABLE_COLUMN_TYPES } from '@/features/custom-fields/field-definition-table'
import type { TableColumnType } from '@/features/custom-fields/types'

interface DefinitionTableColumnRowProps {
  control: Control<FieldDefinitionFormValues>
  index: number
  total: number
  type: TableColumnType
  optionsError?: string
  onLabelChange: (label: string) => void
  onKeyChange: (key: string) => void
  onTypeChange: (type: TableColumnType) => void
  onMove: (to: number) => void
  onRemove: () => void
}

/** One column of the `table` definition: label, key, type, required, and the inline options for an `enum` column. */
export function DefinitionTableColumnRow({
  control,
  index,
  total,
  type,
  optionsError,
  onLabelChange,
  onKeyChange,
  onTypeChange,
  onMove,
  onRemove,
}: DefinitionTableColumnRowProps) {
  const { t } = useTranslation()
  const position = index + 1

  return (
    <li className="flex flex-col gap-2 rounded-lg border bg-muted/40 p-2.5">
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <FormField
          control={control}
          name={`table.columns.${index}.label`}
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.columnLabel')}</FormLabel>
              <FormControl>
                <Input
                  className="h-8 text-sm"
                  autoComplete="off"
                  {...field}
                  onChange={(event) => onLabelChange(event.target.value)}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name={`table.columns.${index}.key`}
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.columnKey')}</FormLabel>
              <FormControl>
                <Input
                  className="h-8 font-mono text-xs"
                  autoComplete="off"
                  {...field}
                  onChange={(event) => onKeyChange(event.target.value)}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      <div className="flex flex-wrap items-end gap-2">
        <FormField
          control={control}
          name={`table.columns.${index}.type`}
          render={({ field }) => (
            <FormItem className="min-w-40 flex-1">
              <FormLabel className="text-xs">{t('customFields.tableEditor.columnType')}</FormLabel>
              <Select value={field.value} onValueChange={(next) => onTypeChange(next as TableColumnType)}>
                <FormControl>
                  <SelectTrigger className="h-8 w-full text-xs">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {TABLE_COLUMN_TYPES.map((columnType) => {
                    const Icon = FIELD_TYPE_ICONS[columnType]
                    return (
                      <SelectItem key={columnType} value={columnType}>
                        <span className="flex items-center gap-2">
                          <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                          {t(`customFields.types.${columnType}`)}
                        </span>
                      </SelectItem>
                    )
                  })}
                </SelectContent>
              </Select>
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name={`table.columns.${index}.required`}
          render={({ field }) => (
            <FormItem className="flex items-center gap-2 pb-1.5">
              <FormControl>
                <Checkbox checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
              <FormLabel className="text-xs">{t('customFields.tableEditor.columnRequired')}</FormLabel>
            </FormItem>
          )}
        />
        <div className="ml-auto flex items-center gap-1 pb-0.5">
          <Button
            type="button"
            variant="secondary"
            size="icon-xs"
            aria-label={`${t('customFields.tableEditor.moveUp')} ${position}`}
            disabled={index === 0}
            onClick={() => onMove(index - 1)}
          >
            <ArrowUp aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="icon-xs"
            aria-label={`${t('customFields.tableEditor.moveDown')} ${position}`}
            disabled={index === total - 1}
            onClick={() => onMove(index + 1)}
          >
            <ArrowDown aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="icon-xs"
            aria-label={`${t('customFields.tableEditor.removeColumn')} ${position}`}
            onClick={onRemove}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        </div>
      </div>

      {type === 'enum' ? (
        <DefinitionTableColumnOptions control={control} columnIndex={index} error={optionsError} />
      ) : null}
    </li>
  )
}
