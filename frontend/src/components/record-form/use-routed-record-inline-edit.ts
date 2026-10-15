import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldValues, Path, UseFormReturn, UseFormSetError } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { isLineEditingKey, lineEditingKey } from '@/components/record-form/use-lines-record-inline-edit'

/** One group of record fields and the endpoint that saves it. */
export interface InlineSaveRoute<TValues extends FieldValues, TRecord> {
  keys: readonly Path<TValues>[]
  /** Keys that always travel together when any of them changed (e.g. a number and its date). */
  together?: readonly Path<TValues>[]
  save: (payload: Record<string, unknown>) => Promise<TRecord>
}

/** A record whose lines are edited in place one at a time (spec 0263). */
export interface LinesSaveRoute<TValues extends FieldValues, TRecord> {
  /** Saves the whole `lines` set (the other lines travel unchanged). */
  save: (lines: TValues['lines']) => Promise<TRecord>
}

interface UseRoutedRecordInlineEditArgs<TValues extends FieldValues, TRecord> {
  form: UseFormReturn<TValues>
  /** The saved record as form values: what Cancel restores and what the diff compares against. */
  persisted: TValues
  /** First matching route wins; one open row only ever changes the keys of one route. */
  routes: readonly InlineSaveRoute<TValues, TRecord>[]
  /** Present when the record's lines are edited in place too (`lines.<index>` editors). */
  lines?: LinesSaveRoute<TValues, TRecord>
  /** Puts the saved record back into the caches; returns its form values, on which the form is reset. */
  onSaved: (saved: TRecord) => TValues
  /** Maps a 422 onto the form fields; the unmapped messages back, or null when the failure is not a 422. */
  applyServerErrors: (error: unknown, setError: UseFormSetError<TValues>) => string[] | null
  /** Message of a refusal the fields cannot carry. */
  errorMessage: (error: unknown) => string
  /** Toast when a field the open row does not show fails client validation. */
  invalidMessage: string
}

/** A blank text is "no value", as the servers store it. */
function normalized(value: unknown): unknown {
  return typeof value === 'string' && value.trim() === '' ? null : value
}

/**
 * In-place editing of a record whose fields save through different endpoints
 * (active invoices spec 0261, passive invoices spec 0262): ONE row open at a
 * time; Confirm validates and sends only the keys that row changed, to the
 * endpoint of their group; an open line (`lines.<index>`, spec 0263) sends the
 * whole `lines` set. A success resets the form on the saved record; Cancel
 * puts the saved values back.
 */
export function useRoutedRecordInlineEdit<TValues extends FieldValues, TRecord>({
  form,
  persisted,
  routes,
  lines,
  onSaved,
  applyServerErrors,
  errorMessage,
  invalidMessage,
}: UseRoutedRecordInlineEditArgs<TValues, TRecord>) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const persistedLines = (persisted.lines ?? []) as TValues['lines'] & unknown[]

  const mutation = useMutation<TRecord, unknown, () => Promise<TRecord>>({
    mutationFn: (send) => send(),
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

  const save = () => {
    void form.handleSubmit(
      (values) => {
        setServerError(null)
        if (lines && editingField !== null && isLineEditingKey(editingField)) {
          mutation.mutate(() => lines.save(values.lines))
          return
        }
        for (const route of routes) {
          const changed = route.keys.filter((key) => normalized(values[key]) !== normalized(persisted[key]))
          if (changed.length === 0) {
            continue
          }
          const together = route.together ?? []
          const keys = changed.some((key) => together.includes(key)) ? [...new Set([...changed, ...together])] : changed
          const payload = Object.fromEntries(keys.map((key) => [key, normalized(values[key])]))
          mutation.mutate(() => route.save(payload))
          return
        }
        cancel()
      },
      () => toast.error(invalidMessage),
    )()
  }

  /** Appends a new line through `append` and opens it in edit mode at the end of the list. */
  const startNewLine = (append: () => void) => {
    discard()
    append()
    setEditingField(lineEditingKey(persistedLines.length))
  }

  /** Deletes a saved line right away (the caller has already asked for confirmation). */
  const removeLine = (index: number) => {
    if (!lines) {
      return
    }
    cancel()
    const kept = persistedLines.filter((_: unknown, position: number) => position !== index) as TValues['lines']
    mutation.mutate(() => lines.save(kept))
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
