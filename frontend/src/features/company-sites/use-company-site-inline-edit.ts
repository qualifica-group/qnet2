import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { get, type FieldErrors } from 'react-hook-form'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { PERSONAL_DATA_CARD_FIELD } from '@/features/personal-data/personal-data-identity-record-section'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { BANKS_FIELD } from '@/features/company-sites/company-site-rows'
import { useCompanySiteForm, type CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'
import { useCompanySiteFormSubmit } from '@/features/company-sites/use-company-site-form-submit'
import type {
  BankDraft,
  CompanySiteBank,
  CompanySiteDetailWithPermissions,
  CompanySiteFormMode,
} from '@/features/company-sites/types'

function banksToDraft(banks: CompanySiteBank[]): BankDraft[] {
  return banks.map((bank) => ({ _key: `bank-${bank.id}`, ...bank }))
}

/**
 * Drives the company-site detail's in-place editing on top of the edit-mode
 * `useCompanySiteForm`: ONE editor open at a time, and Confirm submits the
 * whole form, whose diff-based PATCH (`buildUpdatePayload`) sends only what
 * that editor changed. A successful save resets the form on the saved record
 * and closes the editor.
 *
 * The anagraphic card and the banks are not RHF fields: each row edits a
 * buffered draft that exists only while that row is open. Closed, they ARE the
 * persisted ones, so no other row's save can carry a stale copy (contacts and
 * addresses persist on their own).
 *
 * Opening another row first discards the unconfirmed one: an edit is never
 * carried silently into a different field's save.
 */
export function useCompanySiteInlineEdit(companySite: CompanySiteDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [cardDraft, setCardDraft] = useState<PersonalDataDraft | null>(null)
  const [banksDraft, setBanksDraft] = useState<BankDraft[] | null>(null)
  // Stable per persisted record: `useCompanySiteForm` re-syncs its values on a new one.
  const mode = useMemo<CompanySiteFormMode>(() => ({ type: 'edit', companySite }), [companySite])
  const persistedCard = useMemo(
    () =>
      companySite.personal_data ? cardToDraft(companySite.personal_data) : emptyPersonalDataDraft('company'),
    [companySite.personal_data],
  )
  const persistedBanks = useMemo(() => banksToDraft(companySite.banks), [companySite.banks])
  const profileDraft = cardDraft ?? persistedCard
  const banks = banksDraft ?? persistedBanks

  const { form, customFieldErrorPaths } = useCompanySiteForm({ mode })
  const submit = useCompanySiteFormSubmit({
    form,
    mode,
    profileDraft,
    banksDraft: banks,
    customFieldErrorPaths,
    onSuccess: () => {
      setEditingField(null)
      setCardDraft(null)
      setBanksDraft(null)
      onChanged?.()
    },
  })

  // `keepDirtyValues: false`: the form's `resetOptions` keep dirty values for
  // the background re-sync, but here the unconfirmed edit must really go.
  const discard = () => {
    form.reset(undefined, { keepDirtyValues: false })
    submit.clearServerError()
    setCardDraft(null)
    setBanksDraft(null)
  }

  const start = (field: string) => {
    discard()
    if (field === PERSONAL_DATA_CARD_FIELD) {
      setCardDraft(persistedCard)
    }
    if (field === BANKS_FIELD) {
      setBanksDraft(persistedBanks)
    }
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<CompanySiteFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('companySites.form.genericError'))
    }
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: () => void form.handleSubmit(submit.onSubmit, handleInvalid)(),
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

  return { form, inline, card, banks: { draft: banks, setDraft: setBanksDraft } }
}

/** Everything the detail's editable sections read: the form, the inline state and the two buffers. */
export type CompanySiteDetailEditor = ReturnType<typeof useCompanySiteInlineEdit>
