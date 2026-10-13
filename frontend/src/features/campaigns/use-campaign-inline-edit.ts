import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { EDITOR_EXTRA_FIELDS } from '@/features/campaigns/campaign-record'
import { useCampaignForm, type CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import { useCampaignFormSubmit } from '@/features/campaigns/use-campaign-form-submit'
import type { CampaignDetailWithPermissions, CampaignFormMode } from '@/features/campaigns/types'

/**
 * Drives the campaign detail's in-place editing (spec 0221) on top of the
 * edit-mode `useCampaignForm`: ONE editor open at a time, and Confirm submits
 * the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed. A successful save resets the form on the saved record.
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useCampaignInlineEdit(campaign: CampaignDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  // Stable per persisted record: `useCampaignForm` re-syncs its values on a new one.
  const mode = useMemo<CampaignFormMode>(() => ({ type: 'edit', campaign }), [campaign])

  const { form, customFieldErrorPaths } = useCampaignForm({ mode })
  const submit = useCampaignFormSubmit({
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
  const handleInvalid = (errors: FieldErrors<CampaignFormValues>) => {
    const shown = editingField === null ? [] : [editingField, ...(EDITOR_EXTRA_FIELDS[editingField] ?? [])]
    if (!shown.some((field) => get(errors, field) !== undefined)) {
      toast.error(firstErrorMessage(errors) ?? t('campaigns.form.genericError'))
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
export type CampaignDetailEditor = ReturnType<typeof useCampaignInlineEdit>
