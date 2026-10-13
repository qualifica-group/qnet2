/**
 * "Nuovo intervallo" editor embedded in the Task detail's Segnatempo tab
 * (spec 0122 MT-F6, D-9): the shared `TimeEntryEditor` card (`form/`)
 * without title or context fields ("editor senza titolo e senza
 * collegamenti"), wired to the task-scoped create endpoint via
 * `useTaskTimeEntryForm`.
 */

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { TimeEntryEditor } from '@/features/time-entries/form/time-entry-editor'
import { useTimeEntryTypeOptions } from '@/features/time-entries/form/time-entry-type-picker'
import { useTaskTimeEntryForm } from '@/features/time-entries/task/use-task-time-entry-form'

interface TaskTimeEntryEditorProps {
  taskId: number
}

export function TaskTimeEntryEditor({ taskId }: TaskTimeEntryEditorProps) {
  const { t } = useTranslation()
  const { options } = useTimeEntryTypeOptions()
  const defaultTaskTypeId = options[0]?.id ?? null

  const { form, serverError, isSubmitting, onSubmit, handleStartTimeChange, handleEndTimeChange } =
    useTaskTimeEntryForm({ taskId, defaultTaskTypeId })

  return (
    <Form {...form}>
      <form onSubmit={onSubmit} className="grid gap-3" noValidate>
        <TimeEntryEditor
          control={form.control}
          disabled={isSubmitting}
          headerTitle={t('timeEntries.task.newInterval')}
          headerDescription={t('timeEntries.task.register')}
          onStartTimeChange={handleStartTimeChange}
          onEndTimeChange={handleEndTimeChange}
          serverError={serverError}
        />
        <div className="flex justify-end">
          <Button type="submit" size="sm" disabled={isSubmitting}>
            {isSubmitting ? t('timeEntries.form.saving') : t('timeEntries.task.addButton')}
          </Button>
        </div>
      </form>
    </Form>
  )
}
