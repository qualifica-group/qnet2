import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/*
 * "Persone": Richiedente, Assegnatari, Osservatori, Task privato and the
 * notification switch — one component per field (spec 0195 D-3).
 *
 * The CREATORE is deliberately absent: it is set server-side from the actor
 * and is immutable (D-10), so it has no control here — it is shown in the
 * detail instead. On create, `requester` arrives already prefilled with the
 * connected actor (spec 0118 D-1: `requester_id` is required, and the actor
 * is the requester in the overwhelming majority of cases) — see
 * `useTaskForm.currentUserRef` — left editable, never locked.
 *
 * Spec 0118 D-9 RETIRES AC-083 of spec 0101 ("si puo' essere assegnatario e
 * osservatore insieme"): an id in `watcher_ids` may no longer also be the
 * creator, the requester or an assignee. The client mirror of that rule is
 * TWO-FOLD: the `addWatcherOverlapIssue` refinement in `task-schema.ts`
 * (submit-time, field-scoped on `watcher_ids`, requester+assignees only —
 * the creator is not a form value), and the watchers picker's own
 * `excludeIds` (AC-035, "not offered" — creator+requester+assignees, live).
 * Both mirror the 422 from `TaskService`, which is the actual defense.
 */

interface TaskPeopleFieldProps {
  control: Control<TaskFormValues>
}

export function TaskRequesterField({ control, requester }: TaskPeopleFieldProps & { requester: RelationFieldRef | null }) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  return (
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
  )
}

export function TaskAssigneesField({ control, assignees }: TaskPeopleFieldProps & { assignees: RelationFieldRef[] }) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  return (
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
  )
}

interface TaskWatchersFieldProps extends TaskPeopleFieldProps {
  watchers: RelationFieldRef[]
  /**
   * The task's creator (detail: `task.creator.id`) or the connected actor
   * (create: `currentUserRef.id`) — never a form value (D-10), so it has to
   * arrive as a prop. Feeds the watchers picker's exclusion set (D-9).
   */
  creatorId: number | null
}

export function TaskWatchersField({ control, watchers, creatorId }: TaskWatchersFieldProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()

  // Live, not the hydration props: the exclusion set must follow the actor's
  // OWN edits to the requester/assignees, not just their persisted starting
  // point (spec 0118 AC-035).
  const requesterId = useWatch({ control, name: 'requester_id' })
  const assigneeIds = useWatch({ control, name: 'assignee_ids' })

  const watcherExcludeIds = useMemo(() => {
    const excluded = new Set(assigneeIds)
    if (requesterId !== null) {
      excluded.add(requesterId)
    }
    if (creatorId !== null) {
      excluded.add(creatorId)
    }
    return Array.from(excluded)
  }, [assigneeIds, requesterId, creatorId])

  return (
    <RelationMultiSelectField
      control={control}
      name="watcher_ids"
      metaKey="watcher_ids"
      label={t('tasks.form.watchers')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('tasks.form.watchersSearch')}
      selected={watchers}
      excludeIds={watcherExcludeIds}
      showAvatar
      placeholder={selectLabels.placeholder}
      emptyLabel={selectLabels.emptyLabel}
      errorLabel={selectLabels.errorLabel}
      removeLabel={t('common.remove')}
      retryLabel={selectLabels.retryLabel}
    />
  )
}

export function TaskIsPrivateField({ control }: TaskPeopleFieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="is_private"
      metaKey="is_private"
      label={t('tasks.form.isPrivate')}
      hint={t('tasks.form.isPrivateHint')}
      layout="inline"
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * Spec 0154 D-7: ONE form field (`suppress_notifications`) behind two wire
 * keys — the label/hint swap by mode so the operator reads the instruction
 * that actually matches what this submit will do.
 */
export function TaskSuppressNotificationsField({ control, isCreate }: TaskPeopleFieldProps & { isCreate: boolean }) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="suppress_notifications"
      metaKey="suppress_notifications"
      label={t(isCreate ? 'tasks.form.suppressNotificationsCreate' : 'tasks.form.suppressNotificationsEdit')}
      hint={t(
        isCreate ? 'tasks.form.suppressNotificationsCreateHint' : 'tasks.form.suppressNotificationsEditHint',
      )}
      layout="inline"
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}
