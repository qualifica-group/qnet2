import { Flag } from 'lucide-react'
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
import { useTaskStatusForm } from '@/features/task-statuses/use-task-status-form'
import type { TaskStatusDetail, TaskStatusFormMode } from '@/features/task-statuses/types'

interface TaskStatusFormBodyProps {
  mode: TaskStatusFormMode
  onSuccess: (taskStatus: TaskStatusDetail) => void
  onCancel: () => void
}

/** The metadata keys this form owns, in render order. */
const FIELD_KEYS = ['name', 'description', 'color', 'icon', 'completion_percentage', 'is_active'] as const

/** Formats the raw RHF value for a controlled `<input type="number">`; an emptied field is NaN. */
function numberInputValue(value: number): string {
  return Number.isNaN(value) ? '' : String(value)
}

/**
 * The task status create/edit form UI. Every field is wrapped in `MetaField`
 * (spec 0004): hidden means absent, non-editable means disabled, `required`
 * comes from the resolved `ResourcePermissions` — no hardcoded permission
 * logic lives here. `color` and `icon` reuse the shared `ColorTokenPicker`
 * and `IconPicker` (spec 0101 AC-087), never a bespoke copy.
 * A system row (D-5) forces `description` and `is_active` disabled whatever
 * the field permissions say, mirroring `SystemStatusGuard::assertUpdatable`
 * server-side, whose `MUTABLE_SYSTEM_FIELDS` is name/color/icon/
 * completion_percentage.
 * All non-render logic lives in `useTaskStatusForm`.
 */
export function TaskStatusFormBody({ mode, onSuccess, onCancel }: TaskStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useTaskStatusForm({ mode, onSuccess })

  const isSystemRow = mode.type === 'edit' && mode.taskStatus.system_key !== null
  const systemFieldsHint = isSystemRow ? t('taskStatuses.form.hints.systemStatusFields') : undefined

  const identityVisible = FIELD_KEYS.some((key) => fieldPermission(key).visible)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Flag}
              title={t('taskStatuses.form.sections.identity.title')}
              description={t('taskStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('taskStatuses.form.name')}
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
                label={t('taskStatuses.form.description')}
                hint={systemFieldsHint}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled || isSystemRow}
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
                label={t('taskStatuses.form.color')}
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
                label={t('taskStatuses.form.icon')}
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
                name="completion_percentage"
                metaKey="completion_percentage"
                label={t('taskStatuses.form.completionPercentage')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      inputMode="numeric"
                      step={1}
                      min={0}
                      max={100}
                      disabled={disabled}
                      readOnly={readOnly}
                      value={numberInputValue(field.value)}
                      onChange={(event) => field.onChange(event.target.valueAsNumber)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('taskStatuses.form.isActive')}
                layout="inline"
                hint={systemFieldsHint}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled || isSystemRow}
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
              {t('taskStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('taskStatuses.form.saving') : t('taskStatuses.form.save')}
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
