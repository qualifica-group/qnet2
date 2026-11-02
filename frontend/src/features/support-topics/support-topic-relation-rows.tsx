import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import {
  TASK_CATEGORIES_FOR_SELECT_RESOURCE,
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import { LookupBadgeDisplay, PeopleDisplay } from '@/features/tasks/task-create-displays'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import type { SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'
import type { SupportTopicDetail } from '@/features/support-topics/types'

type LookupField =
  | 'task_type_id'
  | 'task_category_id'
  | 'task_priority_id'
  | 'urgent_task_priority_id'
  | 'task_importance_id'

/** Stable empty refs: a fresh array per render would break the pickers' hydration memo. */
const NO_REFS: RelationFieldRef[] = []

interface RowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The saved topic on a detail; absent on the create draft. */
  supportTopic?: SupportTopicDetail
}

interface LookupRowProps extends Omit<RowsProps, 'supportTopic'> {
  name: LookupField
  label: string
  resource: string
  selected: RelationFieldRef | null
}

/** One Task lookup the topic prefills (type, category, priorities, importance): badge when closed, picker when open. */
function LookupRow({ name, label, resource, selected, inline, canEdit }: LookupRowProps) {
  const { t } = useTranslation()
  const labels = useTaskSelectLabels()
  const { control } = useFormContext<SupportTopicFormValues>()
  const id = useWatch({ control, name })

  return (
    <RecordInlineField
      field={name}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name={name}
          metaKey={name}
          label={label}
          resource={resource}
          searchPlaceholder={t('supportTopics.form.lookupSearch')}
          selected={selected}
          {...labels}
        />
      }
    >
      <LookupBadgeDisplay resource={resource} id={id} />
    </RecordInlineField>
  )
}

interface PeopleRowProps extends Omit<RowsProps, 'supportTopic'> {
  name: 'assignee_ids' | 'watcher_ids'
  label: string
  searchPlaceholder: string
  selected: RelationFieldRef[]
}

/** Assignees / watchers: a user multi-picker; watchers never offer an assignee (server rule, 422 otherwise). */
function PeopleRow({ name, label, searchPlaceholder, selected, inline, canEdit }: PeopleRowProps) {
  const labels = useTaskSelectLabels()
  const { t } = useTranslation()
  const { control } = useFormContext<SupportTopicFormValues>()
  const ids = useWatch({ control, name })
  const assigneeIds = useWatch({ control, name: 'assignee_ids' })

  return (
    <RecordInlineField
      field={name}
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationMultiSelectField
          control={control}
          name={name}
          metaKey={name}
          label={label}
          resource={USERS_FOR_SELECT_RESOURCE}
          searchPlaceholder={searchPlaceholder}
          selected={selected}
          excludeIds={name === 'watcher_ids' ? assigneeIds : undefined}
          showAvatar
          placeholder={labels.placeholder}
          emptyLabel={labels.emptyLabel}
          errorLabel={labels.errorLabel}
          removeLabel={t('common.remove')}
          retryLabel={labels.retryLabel}
        />
      }
    >
      <PeopleDisplay ids={ids} known={selected} />
    </RecordInlineField>
  )
}

function refOf(ref: { id: number; name: string } | null | undefined): RelationFieldRef | null {
  return ref ?? null
}

/** The Task lookups the topic prefills plus its recipients. */
export function SupportTopicRelationRows({ inline, canEdit, supportTopic }: RowsProps) {
  const { t } = useTranslation()
  const base = { inline, canEdit }

  return (
    <>
      <LookupRow {...base} name="task_type_id" label={t('supportTopics.form.taskType')} resource={TASK_TYPES_FOR_SELECT_RESOURCE} selected={refOf(supportTopic?.task_type)} />
      <LookupRow {...base} name="task_category_id" label={t('supportTopics.form.taskCategory')} resource={TASK_CATEGORIES_FOR_SELECT_RESOURCE} selected={refOf(supportTopic?.task_category)} />
      <LookupRow {...base} name="task_priority_id" label={t('supportTopics.form.taskPriority')} resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE} selected={refOf(supportTopic?.task_priority)} />
      <LookupRow {...base} name="urgent_task_priority_id" label={t('supportTopics.form.urgentTaskPriority')} resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE} selected={refOf(supportTopic?.urgent_task_priority)} />
      <LookupRow {...base} name="task_importance_id" label={t('supportTopics.form.taskImportance')} resource={TASK_IMPORTANCES_FOR_SELECT_RESOURCE} selected={refOf(supportTopic?.task_importance)} />
      <PeopleRow {...base} name="assignee_ids" label={t('supportTopics.form.assignees')} searchPlaceholder={t('supportTopics.form.assigneesSearch')} selected={supportTopic?.assignees ?? NO_REFS} />
      <PeopleRow {...base} name="watcher_ids" label={t('supportTopics.form.watchers')} searchPlaceholder={t('supportTopics.form.watchersSearch')} selected={supportTopic?.watchers ?? NO_REFS} />
    </>
  )
}
