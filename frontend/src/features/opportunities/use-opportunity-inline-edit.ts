import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import {
  NO_LEAD_SUBMISSION,
  useOpportunityForm,
  useOpportunityFormSubmit,
  type OpportunityFormValues,
} from '@/features/opportunities/use-opportunity-form'
import type { OpportunityDetailWithPermissions, OpportunityFormMode } from '@/features/opportunities/types'

/**
 * Fields an editor shows besides its own: their messages are on screen, so a
 * refusal on them needs no toast (the Segnalatore carries its rewards).
 */
const EDITOR_COMPANION_FIELDS: Record<string, string[]> = {
  reporter_id: ['rewards'],
}

/**
 * Drives the opportunity detail's in-place editing (spec 0198) on top of the
 * edit-mode `useOpportunityForm`: ONE editor open at a time, and Confirm
 * submits the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends
 * only what that editor changed — plus what its cascade wrote (the anagrafica's
 * roles, the products a classification change prunes, the Segnalatore's
 * rewards). A successful save resets the form on the saved record and closes
 * the editor.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useOpportunityInlineEdit(opportunity: OpportunityDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useOpportunityForm` re-syncs its values on a new one.
  const mode = useMemo<OpportunityFormMode>(() => ({ type: 'edit', opportunity }), [opportunity])

  const { form } = useOpportunityForm({ mode })
  const { serverError, blockingOpportunity, onSubmit, clearSubmitErrors } = useOpportunityFormSubmit({
    form,
    mode,
    leadSubmission: NO_LEAD_SUBMISSION,
    onSuccess: () => {
      setEditingField(null)
      onChanged?.()
    },
  })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const start = (field: string) => {
    form.reset(undefined, { keepDirtyValues: false })
    clearSubmitErrors()
    setEditingField(field)
  }

  const cancel = () => {
    form.reset(undefined, { keepDirtyValues: false })
    clearSubmitErrors()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<OpportunityFormValues>) => {
    const shownFields = editingField === null ? [] : [editingField, ...(EDITOR_COMPANION_FIELDS[editingField] ?? [])]
    if (!shownFields.some((field) => get(errors, field) !== undefined)) {
      toast.error(firstErrorMessage(errors) ?? t('opportunities.form.genericError'))
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

  return { form, inline, blockingOpportunity }
}

/** Everything the detail's editable sections read: the form, the inline state and the open-opportunity refusal. */
export type OpportunityDetailEditor = ReturnType<typeof useOpportunityInlineEdit>
