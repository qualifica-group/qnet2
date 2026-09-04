import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import {
  TASK_CATEGORIES_FOR_SELECT_RESOURCE,
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_STATUSES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import { TaskCompletionReadout } from '@/features/tasks/task-completion-readout'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail } from '@/features/tasks/types'

interface TaskClassificationSectionProps {
  control: Control<TaskFormValues>
  /** The persisted task in edit mode, used to hydrate the five pickers; `null` on create. */
  task: TaskDetail | null
  /** Re-derives the read-only percentage and re-arms the D-7 rule (AC-084). */
  onStatusItemChange: (item: ForSelectItem | null) => void
  /** Derived from the picked status, never a form value; `null` while unpicked. */
  completionPercentage: number | null
}

/** Projects a lookup row onto the `{id, name}` shape the pickers hydrate from. */
function refOf(value: { id: number; name: string } | null | undefined): RelationFieldRef | null {
  return value ? { id: value.id, name: value.name } : null
}

/**
 * "Classificazione": the required Stato plus the four optional lookups, the
 * DERIVED completion percentage and the bloccato/contestato flag.
 *
 * AC-084: the percentage is a read-only readout, NOT a form field — it has no
 * RHF binding and no key in `TaskFormValues`, so it is structurally
 * impossible to submit (D-6: `tasks` has no such column).
 *
 * AC-086: "Bloccato/contestato" lives here as its OWN control, visibly
 * separate from Stato — it is a flag, not a phase.
 */
export function TaskClassificationSection({
  control,
  task,
  onStatusItemChange,
  completionPercentage,
}: TaskClassificationSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  return (
    <FormSection
      icon={Tags}
      title={t('tasks.form.sections.classification.title')}
      description={t('tasks.form.sections.classification.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <RelationSelectField
          control={control}
          name="task_status_id"
          metaKey="task_status_id"
          label={t('tasks.form.status')}
          required
          resource={TASK_STATUSES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.statusSearch')}
          selected={refOf(task?.task_status)}
          onItemChange={onStatusItemChange}
          {...selectLabels}
        />

        <TaskCompletionReadout percentage={completionPercentage} />

        <RelationSelectField
          control={control}
          name="task_type_id"
          metaKey="task_type_id"
          label={t('tasks.form.type')}
          resource={TASK_TYPES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.typeSearch')}
          selected={refOf(task?.task_type)}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="task_category_id"
          metaKey="task_category_id"
          label={t('tasks.form.category')}
          resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.categorySearch')}
          selected={refOf(task?.task_category)}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="task_priority_id"
          metaKey="task_priority_id"
          label={t('tasks.form.priority')}
          resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.prioritySearch')}
          selected={refOf(task?.task_priority)}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="task_importance_id"
          metaKey="task_importance_id"
          label={t('tasks.form.importance')}
          resource={TASK_IMPORTANCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('tasks.form.importanceSearch')}
          selected={refOf(task?.task_importance)}
          {...selectLabels}
        />
      </div>

      <MetaField
        control={control}
        name="is_blocked"
        metaKey="is_blocked"
        label={t('tasks.form.isBlocked')}
        description={t('tasks.form.isBlockedHint')}
        layout="inline"
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
