import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { NO_SECTION_KEY } from '@/features/document-checklists/document-checklist-form-values'
import type { DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'

/** Radix Select forbids an empty item value, so "no group" travels as this sentinel. */
const NO_SECTION_OPTION = '__none__'

/** "Gruppo" select of an entry: the groups in their current order plus "Nessun gruppo". */
export function ChecklistItemSectionSelect({ index }: { index: number }) {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentChecklistFormValues>()
  const sections = useWatch({ control, name: 'sections' })

  return (
    <FormField
      control={control}
      name={`items.${index}.section_key`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{t('documentChecklists.item.section')}</FormLabel>
          <Select
            value={field.value === NO_SECTION_KEY ? NO_SECTION_OPTION : field.value}
            onValueChange={(value) => field.onChange(value === NO_SECTION_OPTION ? NO_SECTION_KEY : value)}
          >
            <FormControl>
              <SelectTrigger className="h-8 w-full text-sm">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value={NO_SECTION_OPTION}>{t('documentChecklists.item.noSection')}</SelectItem>
              {sections.map((section, sectionIndex) => (
                <SelectItem key={section.key} value={section.key}>
                  {section.name.trim() || t('documentChecklists.section.heading', { position: sectionIndex + 1 })}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormDescription className="text-xs">{t('documentChecklists.item.sectionHint')}</FormDescription>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
