/**
 * The time entry create form (spec 0226 D-6): a replica of the detail, its
 * rows closed and opened one at a time with Fatto/Ripristina; nothing is saved
 * per row, "Aggiungi" validates and creates everything at once with the
 * payload of spec 0122 (the caller's `userId` included). Shared by the create
 * Sheet and the `/time-entries/new` page.
 */

import { useEffect, useMemo, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldErrors } from 'react-hook-form'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TimeEntryFormActions } from '@/features/time-entries/form/time-entry-form-actions'
import { useTimeEntryDraftValues, type TimeEntryContextControls } from '@/features/time-entries/form/time-entry-record'
import { TimeEntryRecord } from '@/features/time-entries/form/time-entry-record-sections'
import { hiddenErrorMessage } from '@/features/time-entries/form/time-entry-row-fields'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import { useTimeEntryForm, type TimeEntryFormMode } from '@/features/time-entries/form/use-time-entry-form'
import type { TimeEntry } from '@/features/time-entries/types'
import { cn } from '@/lib/utils'

interface TimeEntryCreateFormProps {
  /** D-8 `manageAll` target, from the caller's own context (a team member's dashboard). */
  userId?: number
  /** Pre-filled date (D-13 "+" on a day card). */
  defaultDate?: string
  onSuccess: (entry: TimeEntry) => void
  onCancel: () => void
  /** Reports `formState.isDirty` changes so a Sheet wrapper can guard its close. */
  onDirtyChange?: (dirty: boolean) => void
  /** Extra classes for the footer (e.g. `sticky bottom-0` inside a Sheet). */
  footerClassName?: string
}

export function TimeEntryCreateForm({
  userId,
  defaultDate,
  onSuccess,
  onCancel,
  onDirtyChange,
  footerClassName,
}: TimeEntryCreateFormProps) {
  const { t } = useTranslation()
  const mode = useMemo<TimeEntryFormMode>(() => ({ type: 'create', userId, defaultDate }), [userId, defaultDate])
  const timeEntryForm = useTimeEntryForm({ mode, onSuccess })
  const { form } = timeEntryForm
  const inline = useDraftInlineEdit(form)
  const values = useTimeEntryDraftValues(form.control, timeEntryForm.taskQuery.data)

  const isDirty = form.formState.isDirty
  useEffect(() => {
    onDirtyChange?.(isDirty)
  }, [isDirty, onDirtyChange])

  const controls: TimeEntryContextControls = {
    isTaskLinked: timeEntryForm.isTaskLinked,
    linkedTask: timeEntryForm.taskQuery.data,
    persistedStage: null,
    onRegistryChange: timeEntryForm.handleRegistryChange,
    onOpportunityItemChange: timeEntryForm.handleOpportunityItemChange,
    onWorkOrderItemChange: timeEntryForm.handleWorkOrderItemChange,
    onStartTimeChange: timeEntryForm.handleStartTimeChange,
    onEndTimeChange: timeEntryForm.handleEndTimeChange,
  }
  // A refusal on a field no closed row shows (e.g. "Alle ore") still has to reach the user.
  const handleInvalid = (errors: FieldErrors<TimeEntryFormValues>) => {
    const message = hiddenErrorMessage(errors, null)
    if (message !== null) {
      toast.error(message)
    }
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    // An open row is confirmed first ("Fatto"): Aggiungi never drops what it holds.
    inline.save()
    void form.handleSubmit(timeEntryForm.submit, handleInvalid)(event)
  }

  const handleReset = () => {
    inline.cancel()
    timeEntryForm.resetToDefaults()
  }

  return (
    <Form {...form}>
      <form onSubmit={handleSubmit} className="flex flex-1 flex-col overflow-hidden" noValidate>
        <TimeEntryRecord
          title={t('timeEntries.form.createTitle')}
          subtitle={t('timeEntries.form.createSubtitle')}
          values={values}
          form={form}
          inline={inline}
          controls={controls}
          canEdit
        >
          {timeEntryForm.serverError ? (
            <p className="text-sm font-medium text-destructive" role="alert">
              {timeEntryForm.serverError}
            </p>
          ) : null}
        </TimeEntryRecord>
        <div className={cn('border-t p-4', footerClassName)}>
          <TimeEntryFormActions isSubmitting={timeEntryForm.isSubmitting} onCancel={onCancel} onReset={handleReset} />
        </div>
      </form>
    </Form>
  )
}
