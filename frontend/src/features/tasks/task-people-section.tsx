import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'

interface TaskPeopleSectionProps {
  control: Control<TaskFormValues>
  /** Edit-mode hydration; `null`/an empty (module-level, stable) array on create. */
  requester: RelationFieldRef | null
  assignees: RelationFieldRef[]
  watchers: RelationFieldRef[]
}

/**
 * "Persone": Richiedente, Assegnatari, Osservatori.
 *
 * The CREATORE is deliberately absent: it is set server-side from the actor
 * and is immutable (D-10), so it has no control here — it is shown in the
 * detail instead.
 *
 * AC-083: the two sets are plain flat id arrays through the SHARED
 * `RelationMultiSelectField`, and nothing prevents the same user from being
 * both an assignee and a watcher — they are two independent pivots.
 */
export function TaskPeopleSection({
  control,
  requester,
  assignees,
  watchers,
}: TaskPeopleSectionProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  return (
    <FormSection
      icon={Users}
      title={t('tasks.form.sections.people.title')}
      description={t('tasks.form.sections.people.description')}
    >
      <RelationSelectField
        control={control}
        name="requester_id"
        metaKey="requester_id"
        label={t('tasks.form.requester')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('tasks.form.requesterSearch')}
        selected={requester}
        showAvatar
        {...selectLabels}
      />

      <RelationMultiSelectField
        control={control}
        name="assignee_ids"
        metaKey="assignee_ids"
        label={t('tasks.form.assignees')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('tasks.form.assigneesSearch')}
        selected={assignees}
        showAvatar
        placeholder={selectLabels.placeholder}
        emptyLabel={selectLabels.emptyLabel}
        errorLabel={selectLabels.errorLabel}
        removeLabel={t('common.remove')}
        retryLabel={selectLabels.retryLabel}
      />

      <RelationMultiSelectField
        control={control}
        name="watcher_ids"
        metaKey="watcher_ids"
        label={t('tasks.form.watchers')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('tasks.form.watchersSearch')}
        selected={watchers}
        showAvatar
        placeholder={selectLabels.placeholder}
        emptyLabel={selectLabels.emptyLabel}
        errorLabel={selectLabels.errorLabel}
        removeLabel={t('common.remove')}
        retryLabel={selectLabels.retryLabel}
      />
    </FormSection>
  )
}
