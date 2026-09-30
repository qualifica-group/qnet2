import { useFieldArray, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import { blankTableColumnOption } from '@/features/custom-fields/field-definition-table'

interface DefinitionTableColumnOptionsProps {
  control: Control<FieldDefinitionFormValues>
  columnIndex: number
  /** Cross-field error of the column's option list (enum without options). */
  error?: string
}

/** Inline value/label options of an `enum` column of a `table` definition. */
export function DefinitionTableColumnOptions({ control, columnIndex, error }: DefinitionTableColumnOptionsProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: `table.columns.${columnIndex}.options` })

  return (
    <div className="flex flex-col gap-2 rounded-md border bg-card p-2">
      <div className="flex items-center justify-between gap-2">
        <span className="text-xs font-medium">{t('customFields.tableEditor.columnOptions')}</span>
        <Button type="button" variant="secondary" size="xs" onClick={() => append(blankTableColumnOption())}>
          <Plus aria-hidden="true" />
          {t('customFields.tableEditor.addOption')}
        </Button>
      </div>
      {fields.map((optionField, optionIndex) => (
        <div key={optionField.id} className="flex items-start gap-2">
          <FormField
            control={control}
            name={`table.columns.${columnIndex}.options.${optionIndex}.value`}
            render={({ field }) => (
              <FormItem className="min-w-0 flex-1">
                <FormControl>
                  <Input
                    className="h-8 text-xs"
                    autoComplete="off"
                    aria-label={`${t('customFields.tableEditor.optionValue')} ${optionIndex + 1}`}
                    placeholder={t('customFields.tableEditor.optionValue')}
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={control}
            name={`table.columns.${columnIndex}.options.${optionIndex}.label`}
            render={({ field }) => (
              <FormItem className="min-w-0 flex-1">
                <FormControl>
                  <Input
                    className="h-8 text-xs"
                    autoComplete="off"
                    aria-label={`${t('customFields.tableEditor.optionLabel')} ${optionIndex + 1}`}
                    placeholder={t('customFields.tableEditor.optionLabel')}
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="mt-1 shrink-0"
            aria-label={`${t('customFields.tableEditor.removeOption')} ${optionIndex + 1}`}
            onClick={() => remove(optionIndex)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        </div>
      ))}
      {error ? (
        <p className="text-xs font-medium text-destructive" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  )
}
