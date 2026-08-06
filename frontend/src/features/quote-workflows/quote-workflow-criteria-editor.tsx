import { useTranslation } from 'react-i18next'
import { ListFilter, Plus, Trash2 } from 'lucide-react'
import { useWatch, type Control, type UseFieldArrayReturn, type UseFormSetValue } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import type { CriterionFieldOption } from '@/features/quote-workflows/types'
import type { QuoteWorkflowFormValues } from '@/features/quote-workflows/use-quote-workflow-form'

interface QuoteWorkflowCriteriaEditorProps {
  control: Control<QuoteWorkflowFormValues>
  setValue: UseFormSetValue<QuoteWorkflowFormValues>
  criteria: UseFieldArrayReturn<QuoteWorkflowFormValues, 'criteria'>
  criterionFields: CriterionFieldOption[]
  disabled?: boolean
}

/** A fresh, still-empty criteria row appended by "Add criterion". */
const EMPTY_ROW = { field: null, value_id: null }

/** Grid template shared by every criteria row: field select | value select | icon-button (mirrors `extra-fields-editor`). */
const ROW_GRID_CLASS = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_1.5rem] items-start gap-2'

/**
 * The workflow's criteria editor (spec 0047 AC-024): a real RHF field array,
 * one row per `{field, value_id}` pair. Each row's field select excludes
 * whatever field is already picked in ANOTHER row (server-enforced
 * distinctness, AC-008) and its value select is an `AsyncPaginatedSelect`
 * scoped to the chosen field's `for_select_resource`, disabled until a field
 * is picked. All non-render logic (default values, submit) lives in
 * `useQuoteWorkflowForm`; this component only renders the array.
 */
export function QuoteWorkflowCriteriaEditor({
  control,
  setValue,
  criteria,
  criterionFields,
  disabled = false,
}: QuoteWorkflowCriteriaEditorProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = criteria
  const rows = useWatch({ control, name: 'criteria' }) ?? []

  const fieldOptionsFor = (index: number): CriterionFieldOption[] => {
    const usedElsewhere = new Set(
      rows
        .filter((row, rowIndex) => rowIndex !== index && row.field !== null && row.field !== '')
        .map((row) => row.field as string),
    )
    return criterionFields.filter((option) => !usedElsewhere.has(option.field))
  }

  return (
    <FormSection
      icon={ListFilter}
      title={t('quoteWorkflows.form.sections.criteria.title')}
      description={t('quoteWorkflows.form.sections.criteria.description')}
    >
      <FormField control={control} name="criteria" render={() => <FormMessage />} />

      <div className="flex flex-col gap-2">
        {fields.map((rowField, index) => {
          const currentField = rows[index]?.field ?? null
          const selectedFieldOption = criterionFields.find((option) => option.field === currentField)

          return (
            <div key={rowField.id} className={ROW_GRID_CLASS}>
              <FormField
                control={control}
                name={`criteria.${index}.field`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="sr-only">{t('quoteWorkflows.form.criteria.field')}</FormLabel>
                    <Select
                      value={field.value ?? undefined}
                      onValueChange={(next) => {
                        setValue(`criteria.${index}.field`, next, { shouldValidate: true })
                        setValue(`criteria.${index}.value_id`, null)
                      }}
                      disabled={disabled}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('quoteWorkflows.form.criteria.fieldPlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {fieldOptionsFor(index).map((option) => (
                          <SelectItem key={option.field} value={option.field}>
                            {option.source === 'custom' ? option.label : t(option.label)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={control}
                name={`criteria.${index}.value_id`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="sr-only">{t('quoteWorkflows.form.criteria.value')}</FormLabel>
                    <FormControl>
                      <AsyncPaginatedSelect
                        resource={selectedFieldOption?.for_select_resource ?? ''}
                        value={field.value}
                        onChange={field.onChange}
                        disabled={disabled || !selectedFieldOption}
                        labels={{
                          placeholder: t('quoteWorkflows.form.criteria.valuePlaceholder'),
                          searchPlaceholder: t('quoteWorkflows.form.criteria.valueSearchPlaceholder'),
                          empty: t('quoteWorkflows.form.criteria.valueEmpty'),
                          error: t('quoteWorkflows.form.criteria.valueError'),
                          clearLabel: t('common.clear'),
                          triggerLabel: t('quoteWorkflows.form.criteria.value'),
                          retry: t('common.retry'),
                        }}
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
                className="mt-1.5 shrink-0 text-muted-foreground hover:text-destructive"
                aria-label={t('quoteWorkflows.form.criteria.remove')}
                disabled={disabled}
                onClick={() => remove(index)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
          )
        })}

        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-full border-dashed text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={() => append({ ...EMPTY_ROW })}
        >
          <Plus aria-hidden="true" />
          {t('quoteWorkflows.form.criteria.add')}
        </Button>
      </div>
    </FormSection>
  )
}
