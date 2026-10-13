import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import type { TaskTemplateFormValues } from '@/features/task-templates/use-task-template-form'

/**
 * The task template's header field controls (spec 0227), shared by the
 * detail's in-place rows and the create form's closed rows: a row's editor is
 * the very control the create form uses (spec 0195 D-3).
 */

interface FieldProps {
  control: Control<TaskTemplateFormValues>
}

export function TaskTemplateNameField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="name" metaKey="name" label={t('taskTemplates.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function TaskTemplateDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="description" metaKey="description" label={t('taskTemplates.form.description')}>
      {({ field, disabled }) => (
        <FormControl>
          <RichTextEditor disabled={disabled} value={field.value} onChange={field.onChange} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function TaskTemplateIsActiveField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="is_active" metaKey="is_active" label={t('taskTemplates.form.isActive')} layout="inline">
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}
