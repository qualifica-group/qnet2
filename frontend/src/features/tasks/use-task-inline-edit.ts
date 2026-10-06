import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldErrors } from 'react-hook-form'
import { useTaskForm } from '@/features/tasks/use-task-form'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetailWithPermissions, TaskFormMode } from '@/features/tasks/types'

/** The single-open-editor state and actions every inline row of the task detail shares. */
export interface TaskInlineEdit {
  /** The form field whose editor is open, `null` when the detail only displays. */
  editingField: string | null
  start: (field: string) => void
  cancel: () => void
  save: () => void
  isSaving: boolean
  /** A refused save the open editor's own field message cannot carry (generic server error). */
  error: string | null
  /** The open editor's confirm button: "Salva" on the detail, "Fatto" on a create draft. */
  confirmLabel: string
  /** Its cancel button: "Annulla" on the detail, "Ripristina" on a create draft (whose own Annulla leaves the form). */
  cancelLabel: string
}

/** The first client-side message among `errors`, or `null` — for a field whose editor is not the open one. */
function firstErrorMessage(errors: FieldErrors<TaskFormValues>): string | null {
  for (const error of Object.values(errors)) {
    if (error && typeof error.message === 'string' && error.message !== '') {
      return error.message
    }
  }
  return null
}

/**
 * Drives the task detail's in-place editing (spec 0195 D-2/D-4) on top of the
 * edit-mode `useTaskForm`: ONE editor open at a time, and Confirm submits the
 * whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed — plus whatever its own handlers cleared alongside
 * (the anagrafica -> referente/opportunita'/lead cascade). A successful save
 * resets the form on the saved task and closes the editor.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useTaskInlineEdit(task: TaskDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted task: `useTaskForm` re-syncs its values on a new one.
  const mode = useMemo<TaskFormMode>(() => ({ type: 'edit', task }), [task])

  const taskForm = useTaskForm({
    mode,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })
  const { form, onSubmit, serverError, clearServerError } = taskForm

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const start = (field: string) => {
    form.reset(undefined, { keepDirtyValues: false })
    clearServerError()
    setEditingField(field)
  }

  const cancel = () => {
    form.reset(undefined, { keepDirtyValues: false })
    clearServerError()
    setEditingField(null)
  }

  // A rule failing on a field OTHER than the open one (e.g. a historical row
  // the stricter schema no longer accepts) has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<TaskFormValues>) => {
    const isOpenFieldError = editingField !== null && editingField in errors
    if (!isOpenFieldError) {
      toast.error(firstErrorMessage(errors) ?? t('tasks.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(onSubmit, handleInvalid)()
  }

  const inline: TaskInlineEdit = {
    editingField,
    start,
    cancel,
    save,
    isSaving: form.formState.isSubmitting,
    error: serverError,
    confirmLabel: t('tasks.detail.inlineEdit.save'),
    cancelLabel: t('tasks.detail.inlineEdit.cancel'),
  }

  return { ...taskForm, inline }
}

/** Everything the detail's editable sections read: the form, its cascade handlers and the inline state. */
export type TaskDetailEditor = ReturnType<typeof useTaskInlineEdit>
