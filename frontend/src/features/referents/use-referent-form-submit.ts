import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { hasPhoneContact } from '@/features/personal-data/create-validation'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
  type BlockedSection,
} from '@/features/personal-data/personal-data-issues'
import { personalDataServerErrorMessage } from '@/features/personal-data/personal-data-server-error'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { usePersonalDataFieldPermission } from '@/features/personal-data/use-personal-data-field-permission'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { createReferent, referentDetailQueryKey, updateReferent } from '@/features/referents/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/referents/referent-form-payload'
import { editDefaults, type ReferentFormValues } from '@/features/referents/use-referent-form'
import type {
  ReferentDetail,
  ReferentDetailWithPermissions,
  ReferentFormMode,
} from '@/features/referents/types'

/**
 * Server-side field names mapped onto the form for 422 handling. The nested
 * `personal_data.*` paths are NOT here — that buffer lives outside RHF (see
 * `personalDataServerErrorMessage`) — mirroring `registries`.
 */
const SERVER_ERROR_FIELDS = ['referent_type_id', 'user_id', 'supplier_registry_id', 'contact_scope', 'notes'] as const

/** Domain key of the module statistics (mirrors `REFERENTS_DOMAIN` in `referents-table.tsx`). */
const REFERENTS_DOMAIN = 'referents'

interface UseReferentFormSubmitArgs {
  form: UseFormReturn<ReferentFormValues>
  mode: ReferentFormMode
  /** The buffered anagraphic card: the create draft, or the detail's (open or persisted) card. */
  profileDraft: PersonalDataDraft
  /** The custom fields' 422 paths (spec 0021), from `useReferentForm`. */
  customFieldErrorPaths: string[]
  /** Called after a successful create/update. */
  onSuccess: (referent: ReferentDetail) => void
  /** Called when a client-side gate refuses the save over a block of the card (create: reopen it). */
  onRefused?: (section: BlockedSection) => void
}

/**
 * Owns the referente create/update submit: the card's client-side gates, the
 * partial PATCH, the cache seed and the server 422 mapping. A refused save
 * names the offending card fields in `serverError` and bumps
 * `revalidateSignal`, so the buffered card marks them itself.
 *
 * On the in-place detail (spec 0216) the card is validated only when it is
 * part of the PATCH: a historical referente with an incomplete card must not
 * block the edit of an unrelated field.
 */
export function useReferentFormSubmit({
  form,
  mode,
  profileDraft,
  customFieldErrorPaths,
  onSuccess,
  onRefused,
}: UseReferentFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(REFERENTS_DOMAIN)
  const personalDataFieldPermission = usePersonalDataFieldPermission()
  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered card marks its own missing
  // fields (it takes no part in this form's submit — see PersonalDataCardForm).
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  const [blockedSection, setBlockedSection] = useState<BlockedSection | null>(null)

  /**
   * Refuses the save: names the offending fields in the banner, marks them
   * inline, and records which block the owner form has to bring into view.
   */
  const refuse = (section: BlockedSection, messageKey: string, fields: string[]): void => {
    setServerError(t(messageKey, { fields: fields.join(' · ') }))
    setBlockedSection(section)
    setRevalidateSignal((signal) => signal + 1)
    onRefused?.(section)
  }

  /** The create-only gates on the buffered card; `false` when the save was refused. */
  const passesCreateGates = (): boolean => {
    if (!isPersonalDataCardValid(profileDraft, t)) {
      refuse('card', 'personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return false
    }
    // The quick-create fields are fully controlled and never block typing, so
    // an invalid buffer is caught once, right here.
    const addressIssues = describeAddressIssues(profileDraft.addresses, t)
    if (addressIssues.length > 0) {
      refuse('addresses', 'personalData.section.addressIncomplete', addressIssues)
      return false
    }
    const contactIssues = describeContactIssues(profileDraft.contacts, t)
    if (contactIssues.length > 0) {
      refuse('contacts', 'personalData.section.contactsInvalid', contactIssues)
      return false
    }
    // A referent must be reachable by phone (user directive 2026-07-31);
    // the server twin lives in StoreReferentRequest.
    if (!hasPhoneContact(profileDraft.contacts)) {
      setServerError(t('personalData.section.phoneRequired'))
      return false
    }
    return true
  }

  const submitUpdate = async (values: ReferentFormValues, referent: ReferentDetailWithPermissions) => {
    const payload = buildUpdatePayload(values, referent, profileDraft, personalDataFieldPermission)
    if (payload.personal_data && !isPersonalDataCardValid(profileDraft, t)) {
      refuse('card', 'personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }
    // Step 1 (edit): PATCH what changed and seed the cached detail with the
    // full shape the server returns (permissions included).
    const saved = await updateReferent(referent.id, payload)
    queryClient.setQueryData(referentDetailQueryKey(referent.id), saved)
    // Step 2 (edit): the detail stays mounted on the saved record, clean.
    form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
    toast.success(t('referents.form.updated'))
    invalidateStats()
    onSuccess(saved)
  }

  const onSubmit = async (values: ReferentFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        await submitUpdate(values, mode.referent)
        return
      }
      if (!passesCreateGates()) {
        return
      }
      const created = await createReferent(buildCreatePayload(values, profileDraft, personalDataFieldPermission))
      toast.success(t('referents.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFieldErrorPaths as Path<ReferentFormValues>[]),
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(t('referents.form.genericError'))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return {
    serverError,
    revalidateSignal,
    blockedSection,
    onSubmit,
    clearServerError,
    personalDataFieldPermission,
  }
}
