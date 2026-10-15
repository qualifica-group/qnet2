import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors, type FieldValues, type UseFormReturn, type UseFormSetError } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

/** Editing key of one line: `lines.<index>`. */
const LINE_FIELD_PREFIX = 'lines.'

export function lineEditingKey(index: number): string {
  return `${LINE_FIELD_PREFIX}${index}`
}

/** True when the open editor is a line, not a header field. */
export function isLineEditingKey(field: string): boolean {
  return field.startsWith(LINE_FIELD_PREFIX)
}

/** Index of the open line, or null when no line is open. */
export function editingLineIndex(field: string | null): number | null {
  return field !== null && isLineEditingKey(field) ? Number(field.slice(LINE_FIELD_PREFIX.length)) : null
}

interface UseLinesRecordInlineEditArgs<TValues extends FieldValues, TPayload extends object, TRecord> {
  form: UseFormReturn<TValues>
  /** The saved record as form values: what Cancel restores and what the header diff compares against. */
  persisted: TValues
  /** Appends a new, empty line to the form's `lines` field array. */
  addLine: () => void
  /** The header keys the open editor changed (empty = nothing to save). */
  headerPatch: (values: TValues, persisted: TValues) => Partial<TPayload>
  /** The whole `lines` set as the PATCH carries it. */
  linesPatch: (lines: TValues['lines']) => Partial<TPayload>
  save: (payload: Partial<TPayload>) => Promise<TRecord>
  /** Puts the saved record back into the cache; returns its form values, on which the form is reset. */
  onSaved: (saved: TRecord) => TValues
  /** Maps a 422 onto the form fields; the unmapped messages back, or null when the failure is not a 422. */
  applyServerErrors: (error: unknown, setError: UseFormSetError<TValues>) => string[] | null
  /** Message of a refusal that is not a 422 (409, 500...). */
  errorMessage: (error: unknown) => string
  /** Rows whose editor shows more than their own field: a rule failing there is visible. */
  extraFields?: Record<string, string[]>
}

/**
 * In-place editing of a record with lines (RDA spec 0256, ODA spec 0257): ONE
 * editor open at a time, a header row (`field`) or a line (`lines.<index>`).
 * Confirm validates the whole form and PATCHes only what that editor changed:
 * the header keys that differ from the saved record, or the whole `lines`
 * set. A success resets the form on the saved record; Cancel puts the saved
 * values back. Opening another editor first discards the unconfirmed one.
 */
export function useLinesRecordInlineEdit<TValues extends FieldValues, TPayload extends object, TRecord>({
  form,
  persisted,
  addLine,
  headerPatch,
  linesPatch,
  save: saveRecord,
  onSaved,
  applyServerErrors,
  errorMessage,
  extraFields = {},
}: UseLinesRecordInlineEditArgs<TValues, TPayload, TRecord>) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const persistedLines = persisted.lines as TValues['lines'] & unknown[]
  // The fields the open editor shows: its own and, for a coupled row, the extra ones.
  const shownFields = editingField === null ? [] : [editingField, ...(extraFields[editingField] ?? [])]

  const mutation = useMutation<TRecord, unknown, Partial<TPayload>>({
    mutationFn: saveRecord,
    onSuccess: (saved) => {
      form.reset(onSaved(saved))
      setEditingField(null)
    },
    onError: (error) => {
      // A 422 lands on its fields (an open editor or a closed row shows it); only the rest needs the slot.
      const unmapped = applyServerErrors(error, form.setError)
      setServerError(unmapped === null ? errorMessage(error) : unmapped.join(' ') || null)
    },
  })

  const discard = () => {
    form.reset(persisted)
    setServerError(null)
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  /** Opens a new, empty line in edit mode at the end of the list. */
  const startNewLine = () => {
    discard()
    addLine()
    setEditingField(lineEditingKey(persistedLines.length))
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<TValues>) => {
    if (!shownFields.some((field) => get(errors, field) !== undefined)) {
      toast.error(firstErrorMessage(errors) ?? errorMessage(null))
    }
  }

  const save = () => {
    void form.handleSubmit((values) => {
      setServerError(null)
      const payload = editingField !== null && isLineEditingKey(editingField)
        ? linesPatch(values.lines)
        : headerPatch(values, persisted)
      if (Object.keys(payload).length === 0) {
        cancel()
        return
      }
      mutation.mutate(payload)
    }, handleInvalid)()
  }

  /** Deletes a saved line right away (the caller has already asked for confirmation). */
  const removeLine = (index: number) => {
    discard()
    setEditingField(null)
    mutation.mutate(linesPatch(persistedLines.filter((_: unknown, position: number) => position !== index) as TValues['lines']))
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: mutation.isPending,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { inline, startNewLine, removeLine }
}
