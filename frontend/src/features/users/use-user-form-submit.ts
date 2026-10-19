import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
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
import { createUser, updateUser, uploadUserAvatar, userDetailQueryKey } from '@/features/users/api'
import { editDefaults } from '@/features/users/user-form-defaults'
import { buildCreatePayload, buildUpdatePayload } from '@/features/users/user-form-payload'
import {
  collectProductLinesServerError,
  BUSINESS_FUNCTION_IDS_FIELD,
  collectBusinessFunctionIdsServerError,
  collectManagedBusinessFunctionIdsServerError,
  MANAGED_BUSINESS_FUNCTION_IDS_FIELD,
  collectReportsToServerError,
  PRODUCT_LINES_FIELD,
  REPORTS_TO_IDS_FIELD,
  SERVER_ERROR_FIELDS,
} from '@/features/users/user-form-server-errors'
import type { UserFormValues } from '@/features/users/use-user-form'
import type { UserDetail, UserDetailWithPermissions, UserFormMode } from '@/features/users/types'

interface UseUserFormSubmitArgs {
  form: UseFormReturn<UserFormValues>
  mode: UserFormMode
  /** The buffered anagraphic card: the create draft, or the detail's (open or persisted) card. */
  profileDraft: PersonalDataDraft
  /** The custom fields' 422 paths (spec 0021), from `useUserForm`. */
  customFieldErrorPaths: string[]
  /** Create only: the avatar chosen before the user exists, uploaded right after the create. */
  pendingAvatar?: File | null
  onSuccess: (user: UserDetail) => void
  /** Called when a client-side gate refuses the save over a block of the card (create: reopen it). */
  onRefused?: (section: BlockedSection) => void
}

/**
 * Owns the user create/update submit: the card's client-side gates, the
 * partial PATCH, the cache seed, the deferred avatar upload and the server 422
 * mapping (the competence rows and the reports-to ids report per index, spec
 * 0111/0166). A refused save names the offending card fields in
 * `serverError` and bumps `revalidateSignal`, so the buffered card marks them.
 *
 * On the in-place detail (spec 0222 D-3) the card is validated only when it is
 * part of the PATCH: an incomplete historical card must not block the edit of
 * an unrelated field.
 */
export function useUserFormSubmit({
  form,
  mode,
  profileDraft,
  customFieldErrorPaths,
  pendingAvatar = null,
  onSuccess,
  onRefused,
}: UseUserFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const personalDataFieldPermission = usePersonalDataFieldPermission()
  const [serverError, setServerError] = useState<string | null>(null)
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  const [blockedSection, setBlockedSection] = useState<BlockedSection | null>(null)

  /** Refuses the save: names the offending fields, marks them inline, records the block to reveal. */
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
    return true
  }

  const submitUpdate = async (values: UserFormValues, user: UserDetailWithPermissions) => {
    const payload = buildUpdatePayload(values, user, profileDraft, personalDataFieldPermission)
    if (payload.personal_data && !isPersonalDataCardValid(profileDraft, t)) {
      refuse('card', 'personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }
    // Step 1 (edit): PATCH what changed and seed the cached detail with the
    // full shape the server returns (permissions included).
    const saved = await updateUser(user.id, payload)
    queryClient.setQueryData(userDetailQueryKey(user.id), saved)
    // Step 2 (edit): the detail stays mounted on the saved record, clean.
    form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
    toast.success(t('users.form.updated'))
    onSuccess(saved)
  }

  const submitCreate = async (values: UserFormValues) => {
    const created = await createUser(buildCreatePayload(values, profileDraft, personalDataFieldPermission))
    toast.success(t('users.form.created'))
    // The user exists now: upload the deferred avatar. A failed upload must
    // not lose the created user — say so and hand off what we have.
    if (pendingAvatar) {
      try {
        onSuccess(await uploadUserAvatar(created.id, pendingAvatar))
        return
      } catch {
        toast.error(t('avatar.avatarUploadError'))
      }
    }
    onSuccess(created)
  }

  const mapServerErrors = (error: unknown) => {
    const mappedScalar = applyServerValidationErrors(error, form.setError, [
      ...SERVER_ERROR_FIELDS,
      ...(customFieldErrorPaths as Path<UserFormValues>[]),
    ])
    const productLinesMessage = collectProductLinesServerError(error)
    if (productLinesMessage) {
      form.setError(PRODUCT_LINES_FIELD, { message: productLinesMessage })
    }
    const reportsToMessage = collectReportsToServerError(error)
    if (reportsToMessage) {
      form.setError(REPORTS_TO_IDS_FIELD, { message: reportsToMessage })
    }
    const membershipMessage = collectBusinessFunctionIdsServerError(error)
    if (membershipMessage) {
      form.setError(BUSINESS_FUNCTION_IDS_FIELD, { message: membershipMessage })
    }
    const managedMessage = collectManagedBusinessFunctionIdsServerError(error)
    if (managedMessage) {
      form.setError(MANAGED_BUSINESS_FUNCTION_IDS_FIELD, { message: managedMessage })
    }
    const personalDataMessage = personalDataServerErrorMessage(error)
    if (personalDataMessage) {
      setServerError(personalDataMessage)
    } else if (!mappedScalar && !productLinesMessage && !reportsToMessage && !membershipMessage && !managedMessage) {
      setServerError(t('users.form.genericError'))
    }
  }

  const onSubmit = async (values: UserFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        await submitUpdate(values, mode.user)
        return
      }
      if (passesCreateGates()) {
        await submitCreate(values)
      }
    } catch (error) {
      mapServerErrors(error)
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
