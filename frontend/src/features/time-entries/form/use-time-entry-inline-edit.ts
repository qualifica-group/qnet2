import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldErrors } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { TimeEntryContextControls } from '@/features/time-entries/form/time-entry-record'
import { hiddenErrorMessage } from '@/features/time-entries/form/time-entry-row-fields'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import { useTimeEntryForm, type TimeEntryFormMode } from '@/features/time-entries/form/use-time-entry-form'
import type { TimeEntry } from '@/features/time-entries/types'

/**
 * Drives the time entry detail's in-place editing (spec 0226) on top of the
 * edit-mode `useTimeEntryForm`: ONE editor open at a time, and Salva submits
 * the whole form as the full PUT the endpoint expects (D-4), in which only the
 * open row's fields changed. The saved record re-syncs the form.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useTimeEntryInlineEdit(entry: TimeEntry) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useTimeEntryForm` re-syncs its values on a new one.
  const mode = useMemo<TimeEntryFormMode>(() => ({ type: 'edit', entry }), [entry])
  const timeEntryForm = useTimeEntryForm({ mode, onSuccess: () => setEditingField(null) })
  const { form } = timeEntryForm

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    timeEntryForm.clearServerError()
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  const handleInvalid = (errors: FieldErrors<TimeEntryFormValues>) => {
    const message = hiddenErrorMessage(errors, editingField)
    if (message !== null) {
      toast.error(message)
    }
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: () => void form.handleSubmit(timeEntryForm.submit, handleInvalid)(),
    dismiss: cancel,
    isSaving: timeEntryForm.isSubmitting,
    error: timeEntryForm.serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  const controls: TimeEntryContextControls = {
    isTaskLinked: timeEntryForm.isTaskLinked,
    linkedTask: timeEntryForm.taskQuery.data,
    persistedStage: entry.work_order_stage,
    onRegistryChange: timeEntryForm.handleRegistryChange,
    onOpportunityItemChange: timeEntryForm.handleOpportunityItemChange,
    onWorkOrderItemChange: timeEntryForm.handleWorkOrderItemChange,
    onStartTimeChange: timeEntryForm.handleStartTimeChange,
    onEndTimeChange: timeEntryForm.handleEndTimeChange,
  }

  return { form, inline, controls }
}
