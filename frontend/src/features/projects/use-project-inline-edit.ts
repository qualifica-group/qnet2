import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useProjectForm, type ProjectFormValues } from '@/features/projects/use-project-form'
import { useProjectFormSubmit } from '@/features/projects/use-project-form-submit'
import type { ProjectDetailWithPermissions, ProjectFormMode } from '@/features/projects/types'

/**
 * Drives the project detail's in-place editing (spec 0220) on top of the
 * edit-mode `useProjectForm`: ONE editor open at a time, and Confirm submits
 * the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed. A successful save resets the form on the saved record.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useProjectInlineEdit(project: ProjectDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useProjectForm` re-syncs its values on a new one.
  const mode = useMemo<ProjectFormMode>(() => ({ type: 'edit', project }), [project])

  const { form, customFieldErrorPaths } = useProjectForm({ mode })
  const submit = useProjectFormSubmit({
    form,
    mode,
    customFieldErrorPaths,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    submit.clearServerError()
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<ProjectFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('projects.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(submit.onSubmit, handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: form.formState.isSubmitting,
    error: submit.serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline }
}

/** Everything the detail's editable sections read: the form and the inline state. */
export type ProjectDetailEditor = ReturnType<typeof useProjectInlineEdit>
