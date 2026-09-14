import { FileStack, Info } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { TaskTemplateItemsEditor } from '@/features/task-templates/task-template-items-editor'
import { useTaskTemplateForm } from '@/features/task-templates/use-task-template-form'
import type { TaskTemplateDetail, TaskTemplateFormMode } from '@/features/task-templates/types'

interface TaskTemplateFormBodyProps {
  mode: TaskTemplateFormMode
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
  onCancel: () => void
}

/**
 * The task template create/edit form UI: identity fields (`MetaField`-driven,
 * spec 0004) plus the row editor. `items` is a CUSTOM authorization field
 * (D-1): gated the same way every `MetaField` is — hidden when not visible,
 * disabled when not editable — but through `useResourcePermissions()`
 * directly, since it edits a whole local array rather than a single RHF
 * path. All non-render logic lives in `useTaskTemplateForm`.
 */
export function TaskTemplateFormBody({ mode, onSuccess, onCancel }: TaskTemplateFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const {
    form,
    serverError,
    itemsError,
    itemErrors,
    itemRows,
    addItemRow,
    removeItemRow,
    updateItemRow,
    reorderItemRows,
    stagedFilesByRow,
    addStagedRowFiles,
    removeStagedRowFile,
    onSubmit,
  } = useTaskTemplateForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('is_active').visible
  const itemsPermission = fieldPermission('items')

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Info}
              title={t('taskTemplates.form.sections.identity.title')}
              description={t('taskTemplates.form.sections.identity.description')}
            >
              <MetaField control={form.control} name="name" metaKey="name" label={t('taskTemplates.form.name')}>
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
                label={t('taskTemplates.form.description')}
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
                name="is_active"
                metaKey="is_active"
                label={t('taskTemplates.form.isActive')}
                layout="inline"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {itemsPermission.visible ? (
            <FormSection
              icon={FileStack}
              title={t('taskTemplates.form.sections.items.title')}
              description={t('taskTemplates.form.sections.items.description')}
            >
              <TaskTemplateItemsEditor
                rows={itemRows}
                errors={itemErrors}
                stagedFilesByRow={stagedFilesByRow}
                onReorder={reorderItemRows}
                onAdd={addItemRow}
                onRemove={removeItemRow}
                onUpdateRow={updateItemRow}
                onAddStagedFiles={addStagedRowFiles}
                onRemoveStagedFile={removeStagedRowFile}
                disabled={!itemsPermission.editable || itemsPermission.disabled}
              />
              {itemsError ? (
                <p className="text-sm font-medium text-destructive" role="alert">
                  {itemsError}
                </p>
              ) : null}
            </FormSection>
          ) : null}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('taskTemplates.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('taskTemplates.form.saving') : t('taskTemplates.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
