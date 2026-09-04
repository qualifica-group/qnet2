import { Shapes } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { IconPicker } from '@/components/icon-picker'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { useTaskTypeForm } from '@/features/task-types/use-task-type-form'
import type { TaskTypeDetail, TaskTypeFormMode } from '@/features/task-types/types'

interface TaskTypeFormBodyProps {
  mode: TaskTypeFormMode
  onSuccess: (taskType: TaskTypeDetail) => void
  onCancel: () => void
}

/** The metadata keys this form owns, in render order. */
const FIELD_KEYS = ['name', 'description', 'color', 'icon', 'is_active'] as const

/**
 * The task type create/edit form UI. Every field is wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions` — no hardcoded permission
 * logic lives here. `color` and `icon` reuse the shared `ColorTokenPicker`
 * and `IconPicker` (spec 0101 AC-087), never a bespoke copy.
 * All non-render logic lives in `useTaskTypeForm`.
 */
export function TaskTypeFormBody({ mode, onSuccess, onCancel }: TaskTypeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useTaskTypeForm({ mode, onSuccess })

  const identityVisible = FIELD_KEYS.some((key) => fieldPermission(key).visible)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Shapes}
              title={t('taskTypes.form.sections.identity.title')}
              description={t('taskTypes.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('taskTypes.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('taskTypes.form.description')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('taskTypes.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker
                      value={field.value}
                      onChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="icon"
                metaKey="icon"
                label={t('taskTypes.form.icon')}
              >
                {({ field, disabled, readOnly }) => (
                  <IconPickerControl
                    value={field.value}
                    onChange={field.onChange}
                    disabled={disabled}
                    readOnly={readOnly}
                  />
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('taskTypes.form.isActive')}
                layout="inline"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('taskTypes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('taskTypes.form.saving') : t('taskTypes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

interface IconPickerControlProps {
  value: string
  onChange: (name: string) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Bridges the shared `IconPicker` into the RHF/`FormItem` context: reads the
 * accessible-error triad ids via `useFormField()` (one level inside the
 * `MetaField` render prop) and supplies the localized picker labels, which are
 * generic UI vocabulary shared with the custom-fields module.
 */
function IconPickerControl({ value, onChange, disabled, readOnly }: IconPickerControlProps) {
  const { t } = useTranslation()
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()

  return (
    <IconPicker
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
      labels={{
        placeholder: t('customFields.form.iconPickerPlaceholder'),
        searchPlaceholder: t('customFields.form.iconSearchPlaceholder'),
        empty: t('customFields.form.iconEmpty'),
        clearLabel: t('customFields.form.iconClear'),
      }}
    />
  )
}
