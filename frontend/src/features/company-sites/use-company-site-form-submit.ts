import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
} from '@/features/personal-data/personal-data-issues'
import { personalDataServerErrorMessage } from '@/features/personal-data/personal-data-server-error'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { usePersonalDataFieldPermission } from '@/features/personal-data/use-personal-data-field-permission'
import {
  companySiteDetailQueryKey,
  createCompanySite,
  updateCompanySite,
  uploadCompanySiteLogo,
} from '@/features/company-sites/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/company-sites/company-site-form-payload'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'
import type { BankDraft, CompanySiteDetail, CompanySiteFormMode } from '@/features/company-sites/types'

/**
 * Server-side scalar field names mapped onto the form for 422 handling. The
 * nested `personal_data.*` paths are NOT here — that buffer lives outside RHF —
 * their 422 messages surface in the open editor (see `personalDataServerErrorMessage`).
 */
const SERVER_ERROR_FIELDS = ['name', 'notes', 'company_id'] as const

interface UseCompanySiteFormSubmitArgs {
  form: UseFormReturn<CompanySiteFormValues>
  mode: CompanySiteFormMode
  /** The buffered anagraphic card: the create draft, or the detail's (open or persisted) card. */
  profileDraft: PersonalDataDraft
  /** The buffered banks: the create draft, or the detail's (open or persisted) list. */
  banksDraft: BankDraft[]
  /** CREATE only: the logo chosen before the site exists, uploaded right after the save. */
  pendingLogo?: File | null
  /** The custom fields' 422 paths (spec 0021), from `useCompanySiteForm`. */
  customFieldErrorPaths: string[]
  /** Called after a successful create/update. */
  onSuccess: (companySite: CompanySiteDetail) => void
}

/**
 * Owns the company-site create/update submit: the card's client-side gates,
 * the partial PATCH, the cache seed and the server 422 mapping. A refused save
 * names the offending card fields in `serverError` and bumps `revalidateSignal`,
 * so the buffered card marks them itself.
 *
 * On the in-place detail the card is validated only when it is part of the
 * PATCH: a historical site with an incomplete card must not block the edit of
 * an unrelated field.
 */
export function useCompanySiteFormSubmit({
  form,
  mode,
  profileDraft,
  banksDraft,
  pendingLogo = null,
  customFieldErrorPaths,
  onSuccess,
}: UseCompanySiteFormSubmitArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const personalDataFieldPermission = usePersonalDataFieldPermission()
  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered card marks its own missing
  // fields (it takes no part in this form's submit — see PersonalDataCardForm).
  const [revalidateSignal, setRevalidateSignal] = useState(0)

  /** Refuses the save, naming the offending fields and highlighting them inline. */
  const refuse = (messageKey: string, fields: string[]): void => {
    setServerError(t(messageKey, { fields: fields.join(' · ') }))
    setRevalidateSignal((signal) => signal + 1)
  }

  /** The create-only gates on the buffered card; `false` when the save was refused. */
  const passesCreateGates = (): boolean => {
    if (!isPersonalDataCardValid(profileDraft, t)) {
      refuse('personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return false
    }
    // The quick-create fields are fully controlled and never block typing, so
    // an invalid buffer is caught once, right here.
    const addressIssues = describeAddressIssues(profileDraft.addresses, t)
    if (addressIssues.length > 0) {
      refuse('personalData.section.addressIncomplete', addressIssues)
      return false
    }
    const contactIssues = describeContactIssues(profileDraft.contacts, t)
    if (contactIssues.length > 0) {
      refuse('personalData.section.contactsInvalid', contactIssues)
      return false
    }
    return true
  }

  const submitUpdate = async (values: CompanySiteFormValues, site: CompanySiteFormMode & { type: 'edit' }) => {
    const payload = buildUpdatePayload(values, site.companySite, banksDraft, profileDraft, personalDataFieldPermission)
    if (payload.personal_data && !isPersonalDataCardValid(profileDraft, t)) {
      refuse('personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }
    const saved = await updateCompanySite(site.companySite.id, payload)
    queryClient.setQueryData(companySiteDetailQueryKey(site.companySite.id), saved)
    toast.success(t('companySites.form.updated'))
    onSuccess(saved)
  }

  const submitCreate = async (values: CompanySiteFormValues) => {
    if (!passesCreateGates()) {
      return
    }
    const created = await createCompanySite(
      buildCreatePayload(values, banksDraft, profileDraft, personalDataFieldPermission),
    )
    toast.success(t('companySites.form.created'))

    // The site exists now; upload the deferred logo before handing off. A
    // failed upload must not lose the created site: toast and hand off the
    // freshest resource we have.
    if (pendingLogo) {
      try {
        onSuccess(await uploadCompanySiteLogo(created.id, pendingLogo))
        return
      } catch {
        toast.error(t('avatar.avatarUploadError'))
      }
    }
    onSuccess(created)
  }

  const onSubmit = async (values: CompanySiteFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        await submitUpdate(values, mode)
        return
      }
      await submitCreate(values)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFieldErrorPaths as Path<CompanySiteFormValues>[]),
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(apiErrorMessage(error, t('companySites.form.genericError'), i18n))
      }
    }
  }

  return {
    serverError,
    revalidateSignal,
    onSubmit,
    // Opening or closing an in-place editor starts from a clean slate.
    clearServerError: () => setServerError(null),
    personalDataFieldPermission,
  }
}
