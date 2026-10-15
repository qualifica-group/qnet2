import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors, type FieldValues, type UseFormReturn } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

/** Which row of a record detail is open; the save callback closes it (spec 0195 D-2). */
export interface InlineEditingField {
  editingField: string | null
  open: (field: string) => void
  close: () => void
}

/**
 * The open-row state, created BEFORE the module's edit form hook so that hook
 * can close the row from its own `onSuccess` (a successful PATCH).
 */
export function useInlineEditingField(): InlineEditingField {
  const [editingField, setEditingField] = useState<string | null>(null)
  return { editingField, open: setEditingField, close: () => setEditingField(null) }
}

interface UseRecordInlineEditArgs<TFieldValues extends FieldValues, TTransformedValues> {
  editing: InlineEditingField
  /** The module's edit-mode form: `values` re-synced on the saved record, `keepDirtyValues` in `resetOptions`. */
  form: UseFormReturn<TFieldValues, unknown, TTransformedValues>
  /** The module's own edit submit (a diff PATCH) on the validated values; on success it calls `editing.close()`. */
  onSubmit: (values: TTransformedValues) => Promise<void>
  /** A refused save its field message cannot carry (generic server error). */
  serverError: string | null
  clearServerError: () => void
  /** Toasted when a refused field is not on screen and the form has no message for it. */
  genericErrorMessage: string
}

/**
 * The in-place editing of a record detail (spec 0195, the kit of the "no edit
 * pages" plan): ONE row open at a time, Save submits the whole edit form,
 * whose diff payload sends only what that row changed. Opening another row
 * first discards the unconfirmed one: an edit is never carried silently into
 * a different field's save.
 */
export function useRecordInlineEdit<TFieldValues extends FieldValues, TTransformedValues = TFieldValues>({
  editing,
  form,
  onSubmit,
  serverError,
  clearServerError,
  genericErrorMessage,
}: UseRecordInlineEditArgs<TFieldValues, TTransformedValues>): InlineEdit {
  const { t } = useTranslation()

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    clearServerError()
  }

  const cancel = () => {
    discard()
    editing.close()
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<TFieldValues>) => {
    if (editing.editingField === null || get(errors, editing.editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? genericErrorMessage)
    }
  }

  return {
    editingField: editing.editingField,
    start: (field) => {
      discard()
      editing.open(field)
    },
    cancel,
    save: () => void form.handleSubmit(onSubmit, handleInvalid)(),
    dismiss: cancel,
    isSaving: form.formState.isSubmitting,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }
}
