/**
 * "Segnatempo" section of the Completa pop-up (spec 0123 D-1, AC-039/AC-040):
 * the shared `TimeEntryEditor` card without title or context fields, exactly
 * as `TaskTimeEntryEditor` (`time-entries/task/`) embeds it in the Task
 * detail's own "Nuovo intervallo" — this one has no submit button of its own,
 * the dialog's "Completa" submits both sections together.
 */

import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { TimeEntryEditor } from '@/features/time-entries/form/time-entry-editor'
import type { TaskTimeEntryFormValues } from '@/features/time-entries/task/task-time-entry-schema'

/** No-op forwarded to `TimeEntryEditor`'s context-field handlers, unreachable with `showContext={false}`. */
function noop(): void {}

interface TaskCompleteTimeEntrySectionProps {
  control: Control<TaskTimeEntryFormValues>
  disabled: boolean
  onStartTimeChange: (value: string) => void
  onEndTimeChange: (value: string) => void
}

export function TaskCompleteTimeEntrySection({
  control,
  disabled,
  onStartTimeChange,
  onEndTimeChange,
}: TaskCompleteTimeEntrySectionProps) {
  const { t } = useTranslation()

  return (
    <TimeEntryEditor
      control={control}
      disabled={disabled}
      showTitle={false}
      showContext={false}
      headerTitle={t('tasks.actions.completeDialog.timeEntryTitle')}
      headerDescription={t('tasks.actions.completeDialog.timeEntryDescription')}
      isTaskLinked
      taskDetail={undefined}
      registry={null}
      opportunity={null}
      workOrder={null}
      task={null}
      onRegistryChange={noop}
      onOpportunityItemChange={noop}
      onWorkOrderItemChange={noop}
      onStartTimeChange={onStartTimeChange}
      onEndTimeChange={onEndTimeChange}
      serverError={null}
    />
  )
}
