import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldValues, Path, UseFormReturn, UseFormSetError } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

/** One group of record fields and the endpoint that saves it. */
export interface InlineSaveRoute<TValues extends FieldValues, TRecord> {
  keys: readonly Path<TValues>[]
  /** Keys that always travel together when any of them changed (e.g. a number and its date). */
  together?: readonly Path<TValues>[]
  save: (payload: Record<string, unknown>) => Promise<TRecord>
}

interface UseRoutedRecordInlineEditArgs<TValues extends FieldValues, TRecord> {
  form: UseFormReturn<TValues>
  /** The saved record as form values: what Cancel restores and what the diff compares against. */
  persisted: TValues
  /** First matching route wins; one open row only ever changes the keys of one route. */
  routes: readonly InlineSaveRoute<TValues, TRecord>[]
  /** Puts the saved record back into the caches; returns its form values, on which the form is reset. */
  onSaved: (saved: TRecord) => TValues
  /** Maps a 422 onto the form fields; false when it is not one. */
  applyServerErrors: (error: unknown, setError: UseFormSetError<TValues>) => boolean
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
 * endpoint of their group. A success resets the form on the saved record;
 * Cancel puts the saved values back.
 */
export function useRoutedRecordInlineEdit<TValues extends FieldValues, TRecord>({
  form,
  persisted,
  routes,
  onSaved,
  applyServerErrors,
  errorMessage,
  invalidMessage,
}: UseRoutedRecordInlineEditArgs<TValues, TRecord>) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const mutation = useMutation<TRecord, unknown, { route: InlineSaveRoute<TValues, TRecord>; payload: Record<string, unknown> }>({
    mutationFn: ({ route, payload }) => route.save(payload),
    onSuccess: (saved) => {
      form.reset(onSaved(saved))
      setEditingField(null)
    },
    onError: (error) => {
      if (!applyServerErrors(error, form.setError)) {
        setServerError(errorMessage(error))
      }
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
        for (const route of routes) {
          const changed = route.keys.filter((key) => normalized(values[key]) !== normalized(persisted[key]))
          if (changed.length === 0) {
            continue
          }
          const together = route.together ?? []
          const keys = changed.some((key) => together.includes(key)) ? [...new Set([...changed, ...together])] : changed
          mutation.mutate({ route, payload: Object.fromEntries(keys.map((key) => [key, normalized(values[key])])) })
          return
        }
        cancel()
      },
      () => toast.error(invalidMessage),
    )()
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

  return { inline }
}
