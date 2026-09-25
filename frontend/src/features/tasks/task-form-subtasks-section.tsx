import { useTranslation } from 'react-i18next'
import { ListTree, Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useWatch, type Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import {
  emptySubtaskGrandchildRow,
  emptySubtaskGreatGrandchildRow,
  emptySubtaskRow,
} from '@/features/tasks/task-subtask-rows'
import { MAX_TASK_FORM_SUBTASKS, type TaskFormValues } from '@/features/tasks/task-schema'
import { countSubtaskTreeNodes } from '@/features/tasks/task-subtask-types'

/** The one meta key every row control shares: no per-field authorization axis of its own, only the section's. */
const SUBTASKS_META_KEY = 'subtasks'

interface TaskFormSubtasksSectionProps {
  control: Control<TaskFormValues>
}

interface TaskFormSubtaskGreatGrandchildRowProps {
  control: Control<TaskFormValues>
  childIndex: number
  grandchildIndex: number
  index: number
  onRemove: () => void
}

/**
 * Level 3 (pronipote), spec 0161 D-1's deepest row — no `subtasks` field of
 * its own, so no "add child" button here at all (structurally, not by a
 * runtime depth check): the tree simply cannot grow a 4th level from this
 * form.
 */
function TaskFormSubtaskGreatGrandchildRow({
  control,
  childIndex,
  grandchildIndex,
  index,
  onRemove,
}: TaskFormSubtaskGreatGrandchildRowProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const rowPath = `subtasks.${childIndex}.subtasks.${grandchildIndex}.subtasks.${index}` as const

  return (
    <div className="flex flex-col gap-2 rounded-lg border bg-surface p-2 @md:flex-row @md:items-start">
      <MetaField
        control={control}
        name={`${rowPath}.title`}
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
        name={`${rowPath}.end_date`}
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
          name={`${rowPath}.assignee_ids`}
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
        onClick={onRemove}
        aria-label={t('tasks.form.subtasks.remove')}
      >
        <Trash2 className="size-3.5" aria-hidden="true" />
      </Button>
    </div>
  )
}

interface TaskFormSubtaskGrandchildRowProps {
  control: Control<TaskFormValues>
  childIndex: number
  index: number
  onRemove: () => void
  limitReached: boolean
}

/** Level 2 (nipote): may append its own level-3 (pronipote) children, capped by the tree's own 50-node total (D-1). */
function TaskFormSubtaskGrandchildRow({
  control,
  childIndex,
  index,
  onRemove,
  limitReached,
}: TaskFormSubtaskGrandchildRowProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const rowPath = `subtasks.${childIndex}.subtasks.${index}` as const
  const { fields, append, remove } = useFieldArray({ control, name: `${rowPath}.subtasks` })

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-col gap-2 rounded-lg border bg-card p-2.5 @md:flex-row @md:items-start">
        <MetaField
          control={control}
          name={`${rowPath}.title`}
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
          name={`${rowPath}.end_date`}
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
            name={`${rowPath}.assignee_ids`}
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

        <div className="flex shrink-0 items-center gap-1 self-start">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={limitReached}
            onClick={() => append(emptySubtaskGreatGrandchildRow())}
            aria-label={t('tasks.form.subtasks.add')}
          >
            <Plus className="size-3.5" aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onRemove}
            aria-label={t('tasks.form.subtasks.remove')}
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>

      {fields.length > 0 ? (
        <div className="flex flex-col gap-2 border-l border-border pl-3">
          {fields.map((rowField, greatGrandchildIndex) => (
            <TaskFormSubtaskGreatGrandchildRow
              key={rowField.id}
              control={control}
              childIndex={childIndex}
              grandchildIndex={index}
              index={greatGrandchildIndex}
              onRemove={() => remove(greatGrandchildIndex)}
            />
          ))}
        </div>
      ) : null}
    </div>
  )
}

interface TaskFormSubtaskChildRowProps {
  control: Control<TaskFormValues>
  index: number
  onRemove: () => void
  limitReached: boolean
}

/** Level 1 (figlio), one root `subtasks[]` row: may append its own level-2 (nipote) children. */
function TaskFormSubtaskChildRow({ control, index, onRemove, limitReached }: TaskFormSubtaskChildRowProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const { fields, append, remove } = useFieldArray({ control, name: `subtasks.${index}.subtasks` })

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-col gap-2 rounded-lg border bg-surface p-3 @md:flex-row @md:items-start">
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

        <div className="flex shrink-0 items-center gap-1 self-start">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={limitReached}
            onClick={() => append(emptySubtaskGrandchildRow())}
            aria-label={t('tasks.form.subtasks.add')}
          >
            <Plus className="size-3.5" aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onRemove}
            aria-label={t('tasks.form.subtasks.remove')}
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>

      {fields.length > 0 ? (
        <div className="flex flex-col gap-2 border-l border-border pl-3">
          {fields.map((rowField, grandchildIndex) => (
            <TaskFormSubtaskGrandchildRow
              key={rowField.id}
              control={control}
              childIndex={index}
              index={grandchildIndex}
              onRemove={() => remove(grandchildIndex)}
              limitReached={limitReached}
            />
          ))}
        </div>
      ) : null}
    </div>
  )
}

/**
 * Spec 0155 D-3, extended by spec 0161 D-1: the compact "Sottotask" block of
 * the create form — a bounded TREE of rows (title required, end date and
 * assignees optional at every level) sent as `subtasks[]` alongside the
 * parent on the SAME submit, each row able to append its own children down
 * to the 3rd level (figlio/nipote/pronipote — `TaskFormSubtaskChildRow` /
 * `TaskFormSubtaskGrandchildRow` / `TaskFormSubtaskGreatGrandchildRow`, the
 * last with no "add child" button at all). Deliberately small
 * (ui-design.md §2): every other field of `CreateTaskSubtaskPayload` is left
 * to the server's own inheritance from the parent (D-3), not exposed here.
 * `limitReached` (D-1's 50-node cap, counted across the WHOLE tree) gates
 * every "add child" button, not just the root one. Create-only —
 * `TaskFormBody` mounts this section only when `mode.type === 'create'`,
 * mirroring `TaskAttachmentStaging`.
 */
export function TaskFormSubtasksSection({ control }: TaskFormSubtasksSectionProps) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: 'subtasks' })
  const subtaskTree = useWatch({ control, name: 'subtasks' })
  const limitReached = countSubtaskTreeNodes(subtaskTree) >= MAX_TASK_FORM_SUBTASKS

  return (
    <FormSection
      icon={ListTree}
      title={t('tasks.form.sections.subtasks.title')}
      description={t('tasks.form.sections.subtasks.description')}
    >
      {fields.length > 0 ? (
        <div className="flex flex-col gap-3">
          {fields.map((rowField, index) => (
            <TaskFormSubtaskChildRow
              key={rowField.id}
              control={control}
              index={index}
              onRemove={() => remove(index)}
              limitReached={limitReached}
            />
          ))}
        </div>
      ) : null}

      <Button
        type="button"
        variant="outline"
        size="sm"
        className="bg-card"
        disabled={limitReached}
        onClick={() => append(emptySubtaskRow())}
      >
        <Plus className="size-3.5" aria-hidden="true" />
        {t('tasks.form.subtasks.add')}
      </Button>
    </FormSection>
  )
}
