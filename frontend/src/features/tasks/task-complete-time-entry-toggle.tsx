/**
 * Wraps the "Segnatempo" section with the optional "Registra il tempo"
 * switch (spec 0162 D-3/D-4): shared by `TaskCompleteDialog` and
 * `TaskBulkCompleteDialog` so the toggle behaviour lives in ONE place.
 * `timeEntryForm.optional` is `false` for a task (or a bulk selection) that
 * requires the segnatempo — the section then renders exactly as before, no
 * switch. `optional: true` renders the switch (ON by default, spec D-3) and
 * hides the section entirely while it is off, mirroring `useTaskCompleteTimeEntryForm.validate()`'s
 * own `undefined` outcome for that state.
 */
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { TaskCompleteTimeEntrySection } from '@/features/tasks/task-complete-time-entry-section'
import type { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'

const TOGGLE_ID = 'task-complete-track-time-toggle'

interface TaskCompleteTimeEntryToggleSectionProps {
  timeEntryForm: ReturnType<typeof useTaskCompleteTimeEntryForm>
  disabled: boolean
}

export function TaskCompleteTimeEntryToggleSection({
  timeEntryForm,
  disabled,
}: TaskCompleteTimeEntryToggleSectionProps) {
  const { t } = useTranslation()

  const section = timeEntryForm.enabled ? (
    <Form {...timeEntryForm.form}>
      <TaskCompleteTimeEntrySection
        control={timeEntryForm.form.control}
        disabled={disabled}
        onStartTimeChange={timeEntryForm.handleStartTimeChange}
        onEndTimeChange={timeEntryForm.handleEndTimeChange}
      />
    </Form>
  ) : null

  if (!timeEntryForm.optional) {
    return section
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <Switch
          id={TOGGLE_ID}
          checked={timeEntryForm.enabled}
          onCheckedChange={timeEntryForm.setEnabled}
          disabled={disabled}
        />
        <Label htmlFor={TOGGLE_ID} className="text-sm font-normal">
          {t('tasks.actions.completeDialog.trackTime')}
        </Label>
      </div>
      {section}
    </div>
  )
}
