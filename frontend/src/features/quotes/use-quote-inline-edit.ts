import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useQuoteForm } from '@/features/quotes/use-quote-form'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetailWithPermissions, QuoteFormMode } from '@/features/quotes/types'

/**
 * Fields an editor shows besides its own: their messages are on screen, so a
 * refusal on them needs no toast. The offer lines carry the Attributes their
 * new products bring in, the status its transition note, the Segnalatore its
 * buoni.
 */
const EDITOR_COMPANION_FIELDS: Record<string, string[]> = {
  offer_lines: ['attribute_values'],
  quote_workflow_status_id: ['note'],
  reporter_id: ['rewards'],
}

/**
 * Drives the quote detail's in-place editing (spec 0197, the Commesse model
 * of spec 0196) on top of the edit-mode `useQuoteForm`: ONE editor open at a
 * time, and Confirm submits the whole form, whose diff-based PATCH
 * (`buildUpdatePayload`) sends only what that editor changed — plus whatever
 * its own handlers cleared alongside (the Societa' -> Societa' sede cascade)
 * and the Attributes new offer lines bring in. A successful save resets the
 * form on the saved record and closes the editor.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useQuoteInlineEdit(quote: QuoteDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useQuoteForm` re-syncs its values on a new one.
  const mode = useMemo<QuoteFormMode>(() => ({ type: 'edit', quote }), [quote])

  const quoteForm = useQuoteForm({
    mode,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })
  const { form, onSubmit, serverError, clearServerError } = quoteForm

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

  // A rule failing on a field the open editor does not show (e.g. a required
  // Attribute a historical record left empty) has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<QuoteFormValues>) => {
    const shownFields = editingField === null ? [] : [editingField, ...(EDITOR_COMPANION_FIELDS[editingField] ?? [])]
    if (!shownFields.some((field) => get(errors, field) !== undefined)) {
      toast.error(firstErrorMessage(errors) ?? t('quotes.form.genericError'))
    }
  }

  const save = () => {
    void form.handleSubmit(onSubmit, handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: form.formState.isSubmitting,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { ...quoteForm, inline }
}

/** Everything the detail's editable sections read: the form, its caches and the inline state. */
export type QuoteDetailEditor = ReturnType<typeof useQuoteInlineEdit>
