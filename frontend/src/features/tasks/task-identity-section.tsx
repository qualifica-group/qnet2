import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { TASKS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'

interface TaskIdentitySectionProps {
  control: Control<TaskFormValues>
  /** Edit-mode hydration of the parent link (`{id, title}` projected onto `{id, name}`). */
  parentTask: RelationFieldRef | null
  /** Edit mode only: the task's own id, excluded from the parent picker (AC-082). */
  excludeTaskId?: number
  /** "Crea sotto-task": the parent arrives prefilled and must not be changed (AC-085). */
  parentLocked?: boolean
  /** Spec 0146 D-3: a sub-task's fase is `prohibited` server-side — picking a parent clears it. */
  onParentChange: () => void
}

/**
 * "Identita'": what the task IS (title, description) and where it sits in the
 * hierarchy (parent task). Rendered as the form's lead card, without a section
 * header: the title is the first thing to type, so it gets the prominent,
 * document-like input CRM forms open with.
 *
 * AC-082: the parent picker never offers the task itself — `exclude_id` is
 * pushed to `GET /api/tasks/for-select`, so the option is gone from the LIST
 * rather than merely rejected on submit. The server still guards self-parent
 * and cycles (`TaskHierarchyGuard`, D-12): this is an affordance, not the rule.
 *
 * AC-085: opened as "crea sotto-task", the parent is prefilled and locked.
 */
export function TaskIdentitySection({
  control,
  parentTask,
  excludeTaskId,
  parentLocked = false,
  onParentChange,
}: TaskIdentitySectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  return (
    <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm">
      <MetaField control={control} name="title" metaKey="title" label={t('tasks.form.title')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              autoComplete="off"
              placeholder={t('tasks.form.titlePlaceholder')}
              className="h-10 text-base font-semibold md:text-base"
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="description"
        metaKey="description"
        label={t('tasks.form.description')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <RichTextEditor
              placeholder={t('tasks.form.descriptionPlaceholder')}
              disabled={disabled}
              value={field.value}
              onChange={field.onChange}
            />
          </FormControl>
        )}
      </MetaField>

      {/* Spec 0154 D-3: free rich text, sanitized like `description` server-side; same editor, own field. */}
      <MetaField control={control} name="evidence" metaKey="evidence" label={t('tasks.form.evidence')}>
        {({ field, disabled }) => (
          <FormControl>
            <RichTextEditor
              placeholder={t('tasks.form.evidencePlaceholder')}
              disabled={disabled}
              value={field.value}
              onChange={field.onChange}
            />
          </FormControl>
        )}
      </MetaField>

      <RelationSelectField
        control={control}
        name="parent_task_id"
        metaKey="parent_task_id"
        label={t('tasks.form.parentTask')}
        hint={parentLocked ? t('tasks.form.hints.parentLocked') : undefined}
        resource={TASKS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('tasks.form.parentTaskSearch')}
        selected={parentTask}
        // Same class of problem as the Commessa picker: `tasks/for-select` is
        // narrowed by `TaskVisibilityScope` (D-9), so an actor who can see
        // this task need not be able to browse its parent. Pin the persisted
        // parent so it stays selectable.
        pinned={parentTask}
        params={excludeTaskId !== undefined ? { exclude_id: excludeTaskId } : undefined}
        forceDisabled={parentLocked}
        onValueChange={onParentChange}
        {...selectLabels}
      />
    </section>
  )
}
