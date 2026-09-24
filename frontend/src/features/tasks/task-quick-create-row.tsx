/**
 * The tasks list's quick-create row (spec 0156 D-7): a compact bar docked
 * right below the grid's rows (`TableView`'s generic `pinnedRowSlot`),
 * visible only with `tasks.create`. All the non-render logic lives in
 * `useTaskQuickCreateRow`; this component only wires the compact controls.
 */
import { Controller } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Can } from '@/features/auth/can'
import { taskLookupOptionRenderer } from '@/features/tasks/task-lookup-option-renderer'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useTaskQuickCreateRow } from '@/features/tasks/use-task-quick-create-row'
import {
  TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
  TASK_PRIORITIES_FOR_SELECT_RESOURCE,
  TASK_STATUSES_FOR_SELECT_RESOURCE,
  TASK_TYPES_FOR_SELECT_RESOURCE,
} from '@/features/tasks/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

interface TaskQuickCreateRowProps {
  /** Called after a successful create, so the caller refreshes the grid. */
  onCreated: () => void
}

export function TaskQuickCreateRow({ onCreated }: TaskQuickCreateRowProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const { form, onSubmit } = useTaskQuickCreateRow({ onCreated })
  const { isSubmitting, errors } = form.formState

  // `TaskSelectLabels` and `AsyncPaginatedSelect`'s own `labels` prop use
  // different key names for the same five strings; this maps them once per
  // picker instead of repeating the mapping five times below.
  const singleSelectLabels = (triggerLabel: string, searchPlaceholder: string) => ({
    placeholder: selectLabels.placeholder,
    searchPlaceholder,
    empty: selectLabels.emptyLabel,
    error: selectLabels.errorLabel,
    clearLabel: selectLabels.clearLabel,
    triggerLabel,
    retry: selectLabels.retryLabel,
  })

  return (
    <Can permission="tasks.create">
      <form
        onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
        className="flex flex-wrap items-start gap-2 border-t border-border bg-surface p-2"
        aria-label={t('tasks.quickCreate.formLabel')}
      >
        <div className="flex min-w-40 flex-1 flex-col gap-0.5">
          <Input
            {...form.register('title')}
            placeholder={t('tasks.quickCreate.titlePlaceholder')}
            aria-label={t('tasks.form.title')}
            aria-invalid={!!errors.title}
            className="h-8 text-xs"
            disabled={isSubmitting}
          />
          {errors.title ? <span className="text-[10px] text-destructive">{errors.title.message}</span> : null}
        </div>

        <Controller
          control={form.control}
          name="task_type_id"
          render={({ field }) => (
            <AsyncPaginatedSelect
              resource={TASK_TYPES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              disabled={isSubmitting}
              renderItem={taskLookupOptionRenderer}
              className="h-8 w-36 shrink-0 text-xs"
              labels={singleSelectLabels(t('tasks.form.type'), t('tasks.form.typeSearch'))}
            />
          )}
        />

        <Controller
          control={form.control}
          name="task_priority_id"
          render={({ field }) => (
            <AsyncPaginatedSelect
              resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              disabled={isSubmitting}
              renderItem={taskLookupOptionRenderer}
              className="h-8 w-36 shrink-0 text-xs"
              labels={singleSelectLabels(t('tasks.form.priority'), t('tasks.form.prioritySearch'))}
            />
          )}
        />

        <Controller
          control={form.control}
          name="task_importance_id"
          render={({ field }) => (
            <AsyncPaginatedSelect
              resource={TASK_IMPORTANCES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              disabled={isSubmitting}
              renderItem={taskLookupOptionRenderer}
              className="h-8 w-36 shrink-0 text-xs"
              labels={singleSelectLabels(t('tasks.form.importance'), t('tasks.form.importanceSearch'))}
            />
          )}
        />

        <Controller
          control={form.control}
          name="task_status_id"
          render={({ field }) => (
            <AsyncPaginatedSelect
              resource={TASK_STATUSES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              disabled={isSubmitting}
              renderItem={taskLookupOptionRenderer}
              className="h-8 w-36 shrink-0 text-xs"
              labels={singleSelectLabels(t('tasks.form.status'), t('tasks.form.statusSearch'))}
            />
          )}
        />

        <div className="flex flex-col gap-0.5">
          <Input
            type="date"
            {...form.register('end_date')}
            aria-label={t('tasks.form.endDate')}
            aria-invalid={!!errors.end_date}
            className="h-8 w-36 shrink-0 text-xs"
            disabled={isSubmitting}
          />
          {errors.end_date ? <span className="text-[10px] text-destructive">{errors.end_date.message}</span> : null}
        </div>

        <div className="flex flex-col gap-0.5">
          <Controller
            control={form.control}
            name="requester_id"
            render={({ field }) => (
              <AsyncPaginatedSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                showAvatar
                disabled={isSubmitting}
                className="h-8 w-40 shrink-0 text-xs"
                labels={singleSelectLabels(t('tasks.form.requester'), t('tasks.form.requesterSearch'))}
              />
            )}
          />
          {errors.requester_id ? (
            <span className="text-[10px] text-destructive">{errors.requester_id.message}</span>
          ) : null}
        </div>

        <div className="flex flex-col gap-0.5">
          <Controller
            control={form.control}
            name="assignee_ids"
            render={({ field }) => (
              <AsyncPaginatedMultiSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                showAvatar
                disabled={isSubmitting}
                className="w-40 shrink-0 text-xs"
                labels={{
                  placeholder: selectLabels.placeholder,
                  searchPlaceholder: t('tasks.form.assigneesSearch'),
                  empty: selectLabels.emptyLabel,
                  error: selectLabels.errorLabel,
                  removeLabel: t('common.remove'),
                  triggerLabel: t('tasks.form.assignees'),
                  retry: selectLabels.retryLabel,
                }}
              />
            )}
          />
          {errors.assignee_ids ? (
            <span className="text-[10px] text-destructive">{errors.assignee_ids.message}</span>
          ) : null}
        </div>

        <Controller
          control={form.control}
          name="watcher_ids"
          render={({ field }) => (
            <AsyncPaginatedMultiSelect
              resource={USERS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              showAvatar
              disabled={isSubmitting}
              className="w-40 shrink-0 text-xs"
              labels={{
                placeholder: selectLabels.placeholder,
                searchPlaceholder: t('tasks.form.watchersSearch'),
                empty: selectLabels.emptyLabel,
                error: selectLabels.errorLabel,
                removeLabel: t('common.remove'),
                triggerLabel: t('tasks.form.watchers'),
                retry: selectLabels.retryLabel,
              }}
            />
          )}
        />

        <Button type="submit" size="icon-xs" disabled={isSubmitting} aria-label={t('tasks.quickCreate.submit')}>
          <Plus aria-hidden="true" />
        </Button>
      </form>
    </Can>
  )
}
