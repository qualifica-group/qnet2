import { useTranslation } from 'react-i18next'
import { ListTree, Plus, Trash2 } from 'lucide-react'
import { useFieldArray, type Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { emptySubtaskRow } from '@/features/tasks/task-subtask-rows'
import { MAX_TASK_FORM_SUBTASKS, type TaskFormValues } from '@/features/tasks/task-schema'

/** The one meta key every row control shares: no per-field authorization axis of its own, only the section's. */
const SUBTASKS_META_KEY = 'subtasks'

interface TaskFormSubtasksSectionProps {
  control: Control<TaskFormValues>
}

/**
 * Spec 0155 D-3: the compact "Sottotask" block of the create form — a
 * bounded list of rows (title required, end date and assignees optional)
 * sent as `subtasks[]` alongside the parent on the SAME submit. Deliberately
 * small (ui-design.md §2): every other field of `CreateTaskSubtaskPayload`
 * is left to the server's own inheritance from the parent (D-3), not
 * exposed here. Create-only — `TaskFormBody` mounts this section only when
 * `mode.type === 'create'`, mirroring `TaskAttachmentStaging`.
 */
export function TaskFormSubtasksSection({ control }: TaskFormSubtasksSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const { fields, append, remove } = useFieldArray({ control, name: 'subtasks' })

  return (
    <FormSection
      icon={ListTree}
      title={t('tasks.form.sections.subtasks.title')}
      description={t('tasks.form.sections.subtasks.description')}
    >
      {fields.length > 0 ? (
        <div className="flex flex-col gap-3">
          {fields.map((rowField, index) => (
            <div
              key={rowField.id}
              className="flex flex-col gap-2 rounded-lg border bg-surface p-3 @md:flex-row @md:items-start"
            >
              <MetaField
                control={control}
                name={`subtasks.${index}.title`}
                metaKey={SUBTASKS_META_KEY}
                label={t('tasks.form.subtasks.title')}
                className="min-w-0 flex-1"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Input
                      value={field.value}
                      disabled={disabled}
                      onChange={field.onChange}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                      placeholder={t('tasks.form.subtasks.titlePlaceholder')}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={control}
                name={`subtasks.${index}.end_date`}
                metaKey={SUBTASKS_META_KEY}
                label={t('tasks.form.subtasks.endDate')}
                className="w-full @md:w-40"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Input
                      type="date"
                      disabled={disabled}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <div className="w-full @md:w-64">
                <RelationMultiSelectField
                  control={control}
                  name={`subtasks.${index}.assignee_ids`}
                  metaKey={SUBTASKS_META_KEY}
                  label={t('tasks.form.subtasks.assignees')}
                  resource={USERS_FOR_SELECT_RESOURCE}
                  searchPlaceholder={t('tasks.form.assigneesSearch')}
                  showAvatar
                  placeholder={selectLabels.placeholder}
                  emptyLabel={selectLabels.emptyLabel}
                  errorLabel={selectLabels.errorLabel}
                  removeLabel={t('common.remove')}
                  retryLabel={selectLabels.retryLabel}
                />
              </div>

              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="shrink-0 self-start"
                onClick={() => remove(index)}
                aria-label={t('tasks.form.subtasks.remove')}
              >
                <Trash2 className="size-3.5" aria-hidden="true" />
              </Button>
            </div>
          ))}
        </div>
      ) : null}

      <Button
        type="button"
        variant="outline"
        size="sm"
        className="bg-card"
        disabled={fields.length >= MAX_TASK_FORM_SUBTASKS}
        onClick={() => append(emptySubtaskRow())}
      >
        <Plus className="size-3.5" aria-hidden="true" />
        {t('tasks.form.subtasks.add')}
      </Button>
    </FormSection>
  )
}
