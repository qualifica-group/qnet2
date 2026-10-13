import { useFieldArray, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Database, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

interface ExtraFieldsEditorProps {
  control: Control<LeadFormValues>
}

/** Grid template shared by the header row and every entry row: key | value | icon-button (`size-6` = 1.5rem). */
const ROW_GRID_CLASS = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_1.5rem] items-start gap-2'

/**
 * Free-form key/value pairs editor for `leads.extra_fields` (spec 0033):
 * add/remove rows, no fixed shape, no per-field permissions — mirrors
 * `DefinitionOptionsEditor`'s field-array pattern. Client-only UI state
 * (RHF field array); converted to/from the API record shape at the form
 * boundary (`recordToEntries`/`entriesToRecord`). Column labels render once
 * in a header row; per-row labels stay `sr-only` so every input keeps its
 * accessible name. It is the editor of the record's "Dati importati" row
 * (spec 0217), so it brings no section chrome of its own.
 */
export function ExtraFieldsEditor({ control }: ExtraFieldsEditorProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: 'extra_fields' })

  const addRow = () => append({ key: '', value: '' })

  return (
    <>
      {fields.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-6 text-center">
          <Database className="size-5 text-muted-foreground/60" aria-hidden="true" />
          <p className="text-sm text-muted-foreground">{t('leads.form.extraFields.empty')}</p>
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={addRow}>
            <Plus aria-hidden="true" />
            {t('leads.form.extraFields.add')}
          </Button>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          <div className={ROW_GRID_CLASS} aria-hidden="true">
            <span className="text-xs font-medium text-muted-foreground">
              {t('leads.form.extraFields.key')}
            </span>
            <span className="text-xs font-medium text-muted-foreground">
              {t('leads.form.extraFields.value')}
            </span>
            <span />
          </div>

          {fields.map((entryField, index) => (
            <div
              key={entryField.id}
              className={`${ROW_GRID_CLASS} motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-200`}
            >
              <FormField
                control={control}
                name={`extra_fields.${index}.key`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="sr-only">{t('leads.form.extraFields.key')}</FormLabel>
                    <FormControl>
                      <Input
                        autoComplete="off"
                        placeholder={t('leads.form.extraFields.keyPlaceholder')}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={control}
                name={`extra_fields.${index}.value`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="sr-only">{t('leads.form.extraFields.value')}</FormLabel>
                    <FormControl>
                      <Input
                        autoComplete="off"
                        placeholder={t('leads.form.extraFields.valuePlaceholder')}
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
                className="mt-1.5 shrink-0 text-muted-foreground hover:text-destructive"
                aria-label={t('leads.form.extraFields.remove')}
                onClick={() => remove(index)}
              >
                <Trash2 aria-hidden="true" />
              </Button>
            </div>
          ))}

          <Button
            type="button"
            variant="outline"
            size="sm"
            className="w-full border-dashed bg-card text-muted-foreground hover:text-foreground"
            onClick={addRow}
          >
            <Plus aria-hidden="true" />
            {t('leads.form.extraFields.add')}
          </Button>
        </div>
      )}
    </>
  )
}
