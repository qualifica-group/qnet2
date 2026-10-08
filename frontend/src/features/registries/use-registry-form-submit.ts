import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
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
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { createRegistry, registryDetailQueryKey, updateRegistry } from '@/features/registries/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/registries/registry-form-payload'
import {
  editDefaults,
  usePersonalDataFieldPermission,
  type RegistryFormValues,
} from '@/features/registries/use-registry-form'
import type {
  RegistryDetail,
  RegistryDetailWithPermissions,
  RegistryFormMode,
} from '@/features/registries/types'

/**
 * Server-side field names mapped onto the form for 422 handling. The nested
 * `personal_data.*` paths are NOT here — that buffer lives outside RHF (see
 * `personalDataServerErrorMessage` below) — mirroring `referents`.
 */
const SERVER_ERROR_FIELDS = [
  'source_id',
  'sector_ids',
  'referent_ids',
  'manager_slots',
  'supervisor_id',
  'commercial_id',
  'reporter_id',
  'vat_group',
  'is_supplier',
  'is_qualified_supplier',
  'agreement_status',
  'agreement_notes',
  'size_class',
  'employee_count',
  'general_notes',
] as const

/** Domain key of the module statistics (mirrors `REGISTRIES_DOMAIN` in `registries-table.tsx`). */
const REGISTRIES_DOMAIN = 'registries'

/**
 * Collects every `personal_data.*` (or bare `personal_data`) message from a
 * 422 response into a single banner string. The buffered anagraphic draft is
 * NOT an RHF field (mirroring `referents`), so its server errors cannot be
 * routed inline into `PersonalDataCardForm`/`ContactsManager`/
 * `AddressesManager` (owner-agnostic, reused unchanged) — they surface here
 * instead, alongside the per-field mapped scalar errors.
 */
function personalDataServerErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => key === 'personal_data' || key.startsWith('personal_data.'))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}

interface UseRegistryFormSubmitArgs {
  form: UseFormReturn<RegistryFormValues>
  mode: RegistryFormMode
  /** The buffered anagraphic card: the create draft, or the detail's (open or persisted) card. */
  profileDraft: PersonalDataDraft
  /** The custom fields' 422 paths (spec 0021), from `useRegistryForm`. */
  customFieldErrorPaths: string[]
  /** Called after a successful create/update. */
  onSuccess: (registry: RegistryDetail) => void
  /** Called when a client-side gate refuses the save over a block of the card (create: reopen it). */
  onRefused?: (section: BlockedSection) => void
}

/**
 * Owns the anagrafica create/update submit: the card's client-side gates, the
 * partial PATCH, the cache seed and the server 422 mapping. A refused save
 * names the offending card fields in `serverError` and bumps
 * `revalidateSignal`, so the buffered card marks them itself.
 *
 * On the in-place detail (spec 0200 D-4) the card is validated only when it is
 * part of the PATCH: a historical anagrafica with an incomplete card must not
 * block the edit of an unrelated field.
 */
export function useRegistryFormSubmit({
  form,
  mode,
  profileDraft,
  customFieldErrorPaths,
  onSuccess,
  onRefused,
}: UseRegistryFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(REGISTRIES_DOMAIN)
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
    // An anagrafica must be reachable by phone (user directive 2026-09-07,
    // same rule the referenti carry); the server twin lives in
    // StoreRegistryRequest via ValidatesRequiredPhoneContact.
    if (!hasPhoneContact(profileDraft.contacts)) {
      setServerError(t('personalData.section.phoneRequired'))
      return false
    }
    return true
  }

  const submitUpdate = async (values: RegistryFormValues, registry: RegistryDetailWithPermissions) => {
    const payload = buildUpdatePayload(values, registry, profileDraft, personalDataFieldPermission)
    if (payload.personal_data && !isPersonalDataCardValid(profileDraft, t)) {
      refuse('card', 'personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }
    // Step 1 (edit): PATCH what changed and seed the cached detail with the
    // full shape the server returns (permissions included).
    const saved = await updateRegistry(registry.id, payload)
    queryClient.setQueryData(registryDetailQueryKey(registry.id), saved)
    // Step 2 (edit): the detail stays mounted on the saved record, clean.
    form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
    toast.success(t('registries.form.updated'))
    invalidateStats()
    onSuccess(saved)
  }

  const onSubmit = async (values: RegistryFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        await submitUpdate(values, mode.registry)
        return
      }
      if (!passesCreateGates()) {
        return
      }
      const created = await createRegistry(buildCreatePayload(values, profileDraft, personalDataFieldPermission))
      toast.success(t('registries.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFieldErrorPaths as Path<RegistryFormValues>[]),
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(t('registries.form.genericError'))
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
