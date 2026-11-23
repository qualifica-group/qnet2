import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { Checkbox } from '@/components/ui/checkbox'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { ChecklistItemSectionSelect } from '@/features/document-checklists/checklist-item-section-select'
import {
  ITEM_DATE_FIELD_MAX_LENGTH,
  type DocumentChecklistFormValues,
} from '@/features/document-checklists/document-checklist-schema'
import { DOCUMENT_CHECKLIST_FILE_TYPES } from '@/features/document-checklists/types'

type NumericItemField = 'max_file_size_kb' | 'max_age_days'

interface ItemFieldsProps {
  index: number
}

interface NumericFieldProps extends ItemFieldsProps {
  name: NumericItemField
  label: string
  hint?: string
  /** Empty input means "no value" (null) instead of an invalid number. */
  nullable?: boolean
}

/** Empty numeric input: NaN makes the schema report the range message, null clears an optional value. */
function parseNumber(raw: string, nullable: boolean): number | null {
  if (raw.trim() === '') {
    return nullable ? null : Number.NaN
  }
  return Number(raw)
}

function NumericField({ index, name, label, hint, nullable = false }: NumericFieldProps) {
  const { control } = useFormContext<DocumentChecklistFormValues>()

  return (
    <FormField
      control={control}
      name={`items.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <Input
              {...field}
              type="number"
              inputMode="numeric"
              className="h-8"
              value={field.value === null || Number.isNaN(field.value) ? '' : field.value}
              onChange={(event) => field.onChange(parseNumber(event.target.value, nullable))}
            />
          </FormControl>
          {hint ? <FormDescription className="text-xs">{hint}</FormDescription> : null}
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

function TextAreaField({
  index,
  name,
  label,
  hint,
}: ItemFieldsProps & { name: 'description' | 'validation_prompt'; label: string; hint?: string }) {
  const { control } = useFormContext<DocumentChecklistFormValues>()

  return (
    <FormField
      control={control}
      name={`items.${index}.${name}`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <FormControl>
            <Textarea {...field} rows={2} className="min-h-0 text-sm" />
          </FormControl>
          {hint ? <FormDescription className="text-xs">{hint}</FormDescription> : null}
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

/** Every editable field of one checklist entry (spec 0403 AC-023), bound by position `items.<index>`. */
export function ChecklistItemFields({ index }: ItemFieldsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentChecklistFormValues>()
  const maxAgeDays = useWatch({ control, name: `items.${index}.max_age_days` })

  return (
    <div className="grid grid-cols-1 gap-3 @lg:grid-cols-2">
      <FormField
        control={control}
        name={`items.${index}.name`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel required className="text-xs">
              {t('documentChecklists.item.name')}
            </FormLabel>
            <FormControl>
              <Input {...field} className="h-8" />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <ChecklistItemSectionSelect index={index} />
      <div className="@lg:col-span-2">
        <TextAreaField index={index} name="description" label={t('documentChecklists.item.description')} />
      </div>
      <FormField
        control={control}
        name={`items.${index}.is_required`}
        render={({ field }) => (
          <FormItem className="flex-row items-center gap-2">
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
            <FormLabel className="text-xs">{t('documentChecklists.item.isRequired')}</FormLabel>
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={`items.${index}.allowed_file_types`}
        render={({ field }) => (
          <FormItem className="gap-1 @lg:col-span-2">
            <FormLabel id={`${field.name}-label`} className="text-xs">
              {t('documentChecklists.item.allowedFileTypes')}
            </FormLabel>
            <FormControl>
              <div role="group" aria-labelledby={`${field.name}-label`} className="flex flex-wrap gap-x-3 gap-y-1.5">
                {DOCUMENT_CHECKLIST_FILE_TYPES.map((fileType) => (
                  <label key={fileType} className="flex items-center gap-1.5 text-xs">
                    <Checkbox
                      checked={field.value.includes(fileType)}
                      onCheckedChange={(checked) =>
                        field.onChange(
                          checked === true
                            ? [...field.value, fileType]
                            : field.value.filter((current) => current !== fileType),
                        )
                      }
                    />
                    {t(`documentChecklists.fileTypes.${fileType}`)}
                  </label>
                ))}
              </div>
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <NumericField index={index} name="max_file_size_kb" label={t('documentChecklists.item.maxFileSizeKb')} />
      <NumericField
        index={index}
        name="max_age_days"
        label={t('documentChecklists.item.maxAgeDays')}
        hint={t('documentChecklists.item.maxAgeDaysHint')}
        nullable
      />
      <FormField
        control={control}
        name={`items.${index}.date_field`}
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs">{t('documentChecklists.item.dateField')}</FormLabel>
            <FormControl>
              <Input
                {...field}
                className="h-8"
                disabled={maxAgeDays === null}
                maxLength={ITEM_DATE_FIELD_MAX_LENGTH}
                placeholder="issue_date"
              />
            </FormControl>
            <FormDescription className="text-xs">{t('documentChecklists.item.dateFieldHint')}</FormDescription>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name={`items.${index}.allow_multiple`}
        render={({ field }) => (
          <FormItem className="gap-1 @lg:col-span-2">
            <label className="flex items-center gap-1.5 text-xs font-medium">
              <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
              {t('documentChecklists.item.allowMultiple')}
            </label>
            <FormDescription className="text-xs">{t('documentChecklists.item.allowMultipleHint')}</FormDescription>
          </FormItem>
        )}
      />
      <div className="@lg:col-span-2">
        <TextAreaField
          index={index}
          name="validation_prompt"
          label={t('documentChecklists.item.validationPrompt')}
          hint={t('documentChecklists.item.validationPromptHint')}
        />
      </div>
    </div>
  )
}
