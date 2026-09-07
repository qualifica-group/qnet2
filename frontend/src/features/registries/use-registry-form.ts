import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { hasPhoneContact } from '@/features/personal-data/create-validation'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
  type BlockedSection,
} from '@/features/personal-data/personal-data-issues'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'
import type { ForSelectItem } from '@/features/for-select/types'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { createRegistry, registryDetailQueryKey, updateRegistry } from '@/features/registries/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/registries/registry-form-payload'
import {
  buildCreateRegistrySchema,
  buildUpdateRegistrySchema,
  type CreateRegistryFormValues,
  type UpdateRegistryFormValues,
} from '@/features/registries/registry-schema'
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
] as const

/** Domain key of the module statistics (mirrors `REGISTRIES_DOMAIN` in `registries-table.tsx`). */
const REGISTRIES_DOMAIN = 'registries'

export type RegistryFormValues = CreateRegistryFormValues & UpdateRegistryFormValues

interface UseRegistryFormArgs {
  mode: RegistryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (registry: RegistryDetail) => void
}

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

/**
 * Owns every non-render concern of `RegistryForm`: RHF/Zod wiring, the
 * buffered personal-data card, the relation selects' hydration and server 422
 * mapping. The component stays UI-only; this hook is the orchestration point
 * (`onSubmit`). Like `referents`, the card is NOT fetched separately: the
 * registry `show` endpoint already embeds `personal_data`, so edit mode seeds
 * the buffer straight from `mode.registry.personal_data` (spec 0020).
 */
export function useRegistryForm({ mode, onSuccess }: UseRegistryFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(REGISTRIES_DOMAIN)
  const { field: fieldPermission } = useResourcePermissions()

  // Adapts the resolved authorization metadata to the personal-data domain's
  // own gating shape (spec 0008 D3): the shared PersonalDataCardForm/
  // ContactsManager/AddressesManager stay decoupled from `@/features/authorization`.
  const personalDataFieldPermission = (key: string): PersonalDataFieldPermission => {
    const permission = fieldPermission(key)
    return {
      visible: permission.visible,
      editable: permission.editable,
      required: permission.required,
      disabled: permission.disabled,
      readonly: permission.readonly,
    }
  }

  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered card marks its own missing
  // fields (it takes no part in this form's submit — see PersonalDataCardForm).
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  const [blockedSection, setBlockedSection] = useState<BlockedSection | null>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(() =>
    mode.type === 'edit' && mode.registry.personal_data
      ? cardToDraft(mode.registry.personal_data)
      : emptyPersonalDataDraft(),
  )

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'registries',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.registry.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateRegistrySchema(t, customFields.schema)
        : buildCreateRegistrySchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<RegistryFormValues>(() => {
    if (mode.type === 'edit') {
      const registry = mode.registry
      return {
        source_id: registry.source_id,
        sector_ids: registry.sector_ids,
        referent_ids: registry.referent_ids,
        manager_slots: registry.manager_slots,
        supervisor_id: registry.supervisor_id,
        commercial_id: registry.commercial_id,
        reporter_id: registry.reporter_id,
        vat_group: registry.vat_group ?? '',
        is_supplier: registry.is_supplier,
        is_qualified_supplier: registry.is_qualified_supplier,
        agreement_status: registry.agreement_status,
        agreement_notes: registry.agreement_notes ?? '',
        size_class: registry.size_class,
        employee_count: registry.employee_count,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      source_id: null,
      sector_ids: [],
      referent_ids: [],
      manager_slots: [],
      supervisor_id: null,
      commercial_id: null,
      reporter_id: null,
      vat_group: '',
      is_supplier: false,
      is_qualified_supplier: false,
      agreement_status: null,
      agreement_notes: '',
      size_class: null,
      employee_count: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  // EDIT: pre-known {id, label} for every relation picker, so it shows its
  // current selection immediately (no hydration round-trip).
  const selectedItems = useMemo(() => {
    if (mode.type !== 'edit') {
      return {
        source: null,
        sectors: [],
        referents: [],
        managers: [],
        supervisor: null,
        commercial: null,
        reporter: null,
      }
    }
    const registry = mode.registry
    const toItem = (ref: { id: number; name: string } | null): ForSelectItem | null =>
      ref ? { id: ref.id, label: ref.name } : null
    const toItems = (refs: { id: number; name: string }[]): ForSelectItem[] =>
      refs.map((ref) => ({ id: ref.id, label: ref.name }))
    return {
      source: toItem(registry.source),
      sectors: toItems(registry.sectors),
      referents: toItems(registry.referents),
      managers: toItems(registry.managers),
      supervisor: toItem(registry.supervisor),
      commercial: toItem(registry.commercial),
      reporter: toItem(registry.reporter),
    }
  }, [mode])

  const form = useForm<RegistryFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // The anagraphic card is mandatory (create requires it; edit always shows
  // one): block the save until the required identity fields are valid. The
  // card form shows the field-level messages inline.
  const profileValid = useMemo(
    () => isPersonalDataCardValid(profileDraft, t),
    [profileDraft, t],
  )

  /**
   * Refuses the save: names the offending fields in the banner, marks them
   * inline, and records which block the owner form has to bring into view.
   */
  const refuse = (section: BlockedSection, messageKey: string, fields: string[]): void => {
    setServerError(t(messageKey, { fields: fields.join(' · ') }))
    setBlockedSection(section)
    setRevalidateSignal((signal) => signal + 1)
  }

  const onSubmit = async (values: RegistryFormValues) => {
    setServerError(null)

    if (!profileValid) {
      refuse('card', 'personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }

    // Create only: the quick-create fields are fully controlled and never
    // block typing, so an invalid buffer is caught once, right here.
    if (mode.type === 'create') {
      const addressIssues = describeAddressIssues(profileDraft.addresses, t)
      if (addressIssues.length > 0) {
        refuse('addresses', 'personalData.section.addressIncomplete', addressIssues)
        return
      }
      const contactIssues = describeContactIssues(profileDraft.contacts, t)
      if (contactIssues.length > 0) {
        refuse('contacts', 'personalData.section.contactsInvalid', contactIssues)
        return
      }
      // An anagrafica must be reachable by phone (user directive 2026-09-07,
      // same rule the referenti carry); the server twin lives in
      // StoreRegistryRequest via ValidatesRequiredPhoneContact.
      if (!hasPhoneContact(profileDraft.contacts)) {
        setServerError(t('personalData.section.phoneRequired'))
        return
      }
    }

    try {
      if (mode.type === 'edit') {
        const saved = await updateRegistry(
          mode.registry.id,
          buildUpdatePayload(values, mode.registry, profileDraft, personalDataFieldPermission),
        )
        // Seed the detail cache with the FULL `RegistryDetailWithPermissions`
        // shape: `updateRegistry` returns a bare `RegistryDetail` (no
        // `permissions` envelope), so writing it verbatim would leave the
        // detail page reading `registry.permissions.resource` off `undefined`
        // and crash. Carry the permissions from the edited instance; the
        // page's own invalidate-on-success refetches the authoritative set.
        queryClient.setQueryData<RegistryDetailWithPermissions>(
          registryDetailQueryKey(mode.registry.id),
          { ...saved, permissions: mode.registry.permissions },
        )
        toast.success(t('registries.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createRegistry(
        buildCreatePayload(values, profileDraft, personalDataFieldPermission),
      )
      toast.success(t('registries.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFields.errorPaths as Path<RegistryFormValues>[]),
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(t('registries.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    revalidateSignal,
    blockedSection,
    selectedItems,
    onSubmit,
    personalDataFieldPermission,
  }
}
