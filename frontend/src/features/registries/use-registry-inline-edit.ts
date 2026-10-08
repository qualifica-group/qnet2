import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { REGISTRY_CARD_FIELD } from '@/features/registries/registry-record'
import { useRegistryForm, type RegistryFormValues } from '@/features/registries/use-registry-form'
import { useRegistryFormSubmit } from '@/features/registries/use-registry-form-submit'
import type { RegistryDetailWithPermissions, RegistryFormMode } from '@/features/registries/types'

/**
 * Drives the anagrafica detail's in-place editing (spec 0200) on top of the
 * edit-mode `useRegistryForm`: ONE editor open at a time, and Confirm submits
 * the whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed. A successful save resets the form on the saved record
 * and closes the editor.
 *
 * The anagraphic card is not an RHF field: its row edits a buffered draft that
 * exists only while that row is open. Closed, the card IS the persisted one, so
 * no other row's save can carry a stale card (contacts and addresses persist on
 * their own, spec 0200 D-4).
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useRegistryInlineEdit(registry: RegistryDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [cardDraft, setCardDraft] = useState<PersonalDataDraft | null>(null)
  // Stable per persisted record: `useRegistryForm` re-syncs its values on a new one.
  const mode = useMemo<RegistryFormMode>(() => ({ type: 'edit', registry }), [registry])
  const persistedCard = useMemo(
    () => (registry.personal_data ? cardToDraft(registry.personal_data) : emptyPersonalDataDraft()),
    [registry.personal_data],
  )
  const profileDraft = cardDraft ?? persistedCard

  const { form, customFieldErrorPaths } = useRegistryForm({ mode })
  const submit = useRegistryFormSubmit({
    form,
    mode,
    profileDraft,
    customFieldErrorPaths,
    onSuccess: () => {
      setEditingField(null)
      setCardDraft(null)
      onChanged?.()
    },
  })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    submit.clearServerError()
    setCardDraft(null)
  }

  const start = (field: string) => {
    discard()
    if (field === REGISTRY_CARD_FIELD) {
      setCardDraft(persistedCard)
    }
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<RegistryFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('registries.form.genericError'))
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

  const card = {
    draft: profileDraft,
    setDraft: setCardDraft,
    revalidateSignal: submit.revalidateSignal,
    fieldPermission: submit.personalDataFieldPermission,
  }

  return { form, inline, card }
}

/** Everything the detail's editable sections read: the form, the inline state and the card buffer. */
export type RegistryDetailEditor = ReturnType<typeof useRegistryInlineEdit>
