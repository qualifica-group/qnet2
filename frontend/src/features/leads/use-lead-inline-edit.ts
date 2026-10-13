import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useLeadCascades } from '@/features/leads/use-lead-cascades'
import { useLeadForm, type LeadFormValues } from '@/features/leads/use-lead-form'
import { useLeadFormSubmit } from '@/features/leads/use-lead-form-submit'
import type { LeadDetailWithPermissions, LeadFormMode } from '@/features/leads/types'

/**
 * Drives the lead detail's in-place editing (spec 0217) on top of the
 * edit-mode `useLeadForm`: ONE editor open at a time, and Confirm submits the
 * whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed — the fields its cascade wrote included (D-3). A
 * successful save resets the form on the saved record and closes the editor.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save. The cascades re-anchor on
 * the value the row opens with.
 */
export function useLeadInlineEdit(lead: LeadDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useLeadForm` re-syncs its values on a new one.
  const mode = useMemo<LeadFormMode>(() => ({ type: 'edit', lead }), [lead])

  const form = useLeadForm({ mode })
  const cascades = useLeadCascades(form, lead)
  const submit = useLeadFormSubmit({
    form,
    mode,
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
    cascades.syncBaseline()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<LeadFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('leads.form.genericError'))
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
    // A campaign switch waiting on the products confirmation must not be saved half-applied (AC-042).
    isSaving: form.formState.isSubmitting || cascades.isCampaignChangePending,
    error: submit.serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline, cascades }
}

/** Everything the detail's editable sections read: the form, the inline state and the cascades. */
export type LeadDetailEditor = ReturnType<typeof useLeadInlineEdit>
