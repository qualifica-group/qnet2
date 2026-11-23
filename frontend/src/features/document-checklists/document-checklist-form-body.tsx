import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { CHECKLIST_CODE_MAX_LENGTH, type DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'

interface DocumentChecklistFormBodyProps {
  /** True once the checklist synced: the code is then read-only (spec 0403 D-4). */
  codeLocked: boolean
}

/** Identity fields of the checklist: name, code, description and active flag. */
export function DocumentChecklistFormBody({ codeLocked }: DocumentChecklistFormBodyProps) {
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
