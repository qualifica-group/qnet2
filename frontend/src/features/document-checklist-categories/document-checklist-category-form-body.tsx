import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  CATEGORY_SORT_ORDER_MAX,
  CATEGORY_SORT_ORDER_MIN,
  type DocumentChecklistCategoryFormValues,
} from '@/features/document-checklist-categories/document-checklist-category-schema'

/** Empty numeric input: NaN makes the schema report the range message. */
function parseSortOrder(raw: string): number {
  return raw.trim() === '' ? Number.NaN : Number(raw)
}

/** Fields of the category: name, sort order and description. */
export function DocumentChecklistCategoryFormBody() {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentChecklistCategoryFormValues>()

  return (
    <div className="grid grid-cols-1 gap-3 @lg:grid-cols-2">
      <FormField
        control={control}
        name="name"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel required>{t('documentChecklistCategories.form.name')}</FormLabel>
            <FormControl>
              <Input {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="sort_order"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel>{t('documentChecklistCategories.form.sortOrder')}</FormLabel>
            <FormControl>
              <Input
                {...field}
                type="number"
                inputMode="numeric"
                min={CATEGORY_SORT_ORDER_MIN}
                max={CATEGORY_SORT_ORDER_MAX}
                value={Number.isNaN(field.value) ? '' : field.value}
                onChange={(event) => field.onChange(parseSortOrder(event.target.value))}
              />
            </FormControl>
            <FormDescription className="text-xs">{t('documentChecklistCategories.form.sortOrderHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="description"
        render={({ field }) => (
          <FormItem className="gap-1 @lg:col-span-2">
            <FormLabel>{t('documentChecklistCategories.form.description')}</FormLabel>
            <FormControl>
              <Textarea {...field} rows={2} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  )
}
