import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { DOCUMENT_CHECKLIST_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/document-checklist-categories/api'
import type { DocumentChecklistCategoryRef } from '@/features/document-checklists/types'
import { CHECKLIST_CODE_MAX_LENGTH, type DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'

interface DocumentChecklistFormBodyProps {
  /** True once the checklist synced: the code is then read-only (spec 0403 D-4). */
  codeLocked: boolean
  /** Category already saved on the checklist, so the picker labels it without waiting on a lookup. */
  savedCategory: DocumentChecklistCategoryRef | null
}

/** Identity fields of the checklist: name, code, category, description and active flag. */
export function DocumentChecklistFormBody({ codeLocked, savedCategory }: DocumentChecklistFormBodyProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentChecklistFormValues>()

  return (
    <div className="grid grid-cols-1 gap-3 @lg:grid-cols-2">
      <FormField
        control={control}
        name="name"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel required>{t('documentChecklists.form.name')}</FormLabel>
            <FormControl>
              <Input {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="code"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel required>{t('documentChecklists.form.code')}</FormLabel>
            <FormControl>
              <Input
                {...field}
                readOnly={codeLocked}
                maxLength={CHECKLIST_CODE_MAX_LENGTH}
                className="uppercase"
                onChange={(event) => field.onChange(event.target.value.toUpperCase())}
              />
            </FormControl>
            <FormDescription className="text-xs">
              {t(codeLocked ? 'documentChecklists.form.codeLocked' : 'documentChecklists.form.codeHint')}
            </FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="category_id"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel>{t('documentChecklists.form.category')}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={DOCUMENT_CHECKLIST_CATEGORIES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={savedCategory ? { id: savedCategory.id, label: savedCategory.name } : null}
                labels={{
                  placeholder: t('documentChecklists.form.categoryPlaceholder'),
                  searchPlaceholder: t('documentChecklists.form.categorySearch'),
                  empty: t('documentChecklists.form.categoryEmpty'),
                  error: t('documentChecklists.form.categoryError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('documentChecklists.form.category'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="description"
        render={({ field }) => (
          <FormItem className="gap-1 @lg:col-span-2">
            <FormLabel>{t('documentChecklists.form.description')}</FormLabel>
            <FormControl>
              <Textarea {...field} rows={2} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={control}
        name="is_active"
        render={({ field }) => (
          <FormItem className="flex-row items-center gap-2">
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
            <FormLabel>{t('documentChecklists.form.isActive')}</FormLabel>
          </FormItem>
        )}
      />
    </div>
  )
}
