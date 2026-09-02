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
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
} from '@/features/personal-data/personal-data-issues'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'
import {
  createCompanySite,
  deleteCompanySiteLogo,
  setDefaultCompanySite,
  updateCompanySite,
  uploadCompanySiteLogo,
} from '@/features/company-sites/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/company-sites/company-site-form-payload'
import {
  buildCreateCompanySiteSchema,
  buildUpdateCompanySiteSchema,
  type CreateCompanySiteFormValues,
  type UpdateCompanySiteFormValues,
} from '@/features/company-sites/company-site-schema'
import type { BankDraft, CompanySiteDetail } from '@/features/company-sites/types'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { ForSelectItem } from '@/features/for-select/types'

export type CompanySiteFormValues = CreateCompanySiteFormValues & UpdateCompanySiteFormValues

/**
 * Server-side scalar field names mapped onto the form for 422 handling. The
 * nested `personal_data.*` paths are NOT here — that buffer lives outside RHF —
 * their 422 messages surface in a banner (see `personalDataServerErrorMessage`).
 */
const SERVER_ERROR_FIELDS = ['name', 'notes', 'company_id'] as const

/**
 * Collects every `personal_data.*` (or bare `personal_data`) message from a
 * 422 response into a single banner string. The buffered anagraphic draft is
 * not an RHF field, so its server errors surface here rather than inline in the
 * shared card/contacts/address components (mirrors the Registries module).
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

interface UseCompanySiteFormArgs {
  mode: CompanySiteFormMode
  onSuccess: (companySite: CompanySiteDetail) => void
  onSiteChange?: () => void
}

/**
 * Owns every non-render concern of `CompanySiteForm`: RHF/Zod wiring, the
 * buffered banks collection, the deferred/immediate logo mutations, the
 * set-default action, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useCompanySiteForm({ mode, onSuccess, onSiteChange }: UseCompanySiteFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { canAction, field: fieldPermission } = useResourcePermissions()
  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered card marks its own missing
  // fields (it takes no part in this form's submit — see PersonalDataCardForm).
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  // CREATE mode only: logo chosen before the site exists, uploaded after save.
  const [pendingLogo, setPendingLogo] = useState<File | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'company-sites',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.companySite.custom_fields }
      : { type: 'create' },
  )

  // Adapts the resolved authorization metadata to the personal-data domain's
  // own gating shape (spec 0008 D3): the shared card/contacts/address
  // components stay decoupled from `@/features/authorization`.
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

  // The buffered anagraphic card (identity + contacts + single address). Seeded
  // from the loaded site's embedded card in edit mode; a blank company card
  // otherwise. Its `type` is always `company` (locked in the card form).
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(() =>
    mode.type === 'edit' && mode.companySite.personal_data
      ? cardToDraft(mode.companySite.personal_data)
      : emptyPersonalDataDraft('company'),
  )

  // The buffered banks collection (mirrors `ContactsManager`'s pattern): lives
  // outside RHF, seeded once from the loaded site and submitted as the
  // authoritative `banks[]` array on save.
  const [banksDraft, setBanksDraft] = useState<BankDraft[]>(() =>
    mode.type === 'edit'
      ? mode.companySite.banks.map((bank) => ({ _key: `bank-${bank.id}`, ...bank }))
      : [],
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCompanySiteSchema(t, customFields.schema)
        : buildCreateCompanySiteSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<CompanySiteFormValues>(() => {
    if (mode.type === 'edit') {
      const site = mode.companySite
      return {
        name: site.name,
        notes: site.notes ?? '',
        company_id: site.company?.id ?? null,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      notes: '',
      company_id: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  // EDIT: pre-known {id, label} for the company picker, so it shows its
  // current selection immediately without a hydration round-trip.
  const selectedCompanyItem = useMemo<ForSelectItem | null>(
    () =>
      mode.type === 'edit' && mode.companySite.company
        ? { id: mode.companySite.company.id, label: mode.companySite.company.label }
        : null,
    [mode],
  )

  const form = useForm<CompanySiteFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // The anagraphic card is mandatory (always a company): block the save until
  // its required identity fields are valid. The card form shows the field-level
  // messages inline (mirrors the Registries module).
  const profileValid = useMemo(
    () => isPersonalDataCardValid(profileDraft, t),
    [profileDraft, t],
  )

  /** Refuses the save, naming the offending fields and highlighting them inline. */
  const refuse = (messageKey: string, fields: string[]): void => {
    setServerError(t(messageKey, { fields: fields.join(' · ') }))
    setRevalidateSignal((signal) => signal + 1)
  }

  const onSubmit = async (values: CompanySiteFormValues) => {
    setServerError(null)

    if (!profileValid) {
      refuse('personalData.section.incomplete', describeCardIssues(profileDraft, t))
      return
    }

    // Create only: the quick-create fields are fully controlled and never
    // block typing, so an invalid buffer is caught once, right here.
    if (mode.type === 'create') {
      const addressIssues = describeAddressIssues(profileDraft.addresses, t)
      if (addressIssues.length > 0) {
        refuse('personalData.section.addressIncomplete', addressIssues)
        return
      }
      const contactIssues = describeContactIssues(profileDraft.contacts, t)
      if (contactIssues.length > 0) {
        refuse('personalData.section.contactsInvalid', contactIssues)
        return
      }
    }

    try {
      if (mode.type === 'edit') {
        const saved = await updateCompanySite(
          mode.companySite.id,
          buildUpdatePayload(
            values,
            mode.companySite,
            banksDraft,
            profileDraft,
            personalDataFieldPermission,
          ),
        )
        toast.success(t('companySites.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCompanySite(
        buildCreatePayload(values, banksDraft, profileDraft, personalDataFieldPermission),
      )
      toast.success(t('companySites.form.created'))

      // The site exists now; upload the deferred logo before handing off. A
      // failed logo upload must not lose the created site — surface a toast
      // and proceed, returning the freshest resource we have.
      if (pendingLogo) {
        try {
          const withLogo = await uploadCompanySiteLogo(created.id, pendingLogo)
          onSuccess(withLogo)
          return
        } catch {
          toast.error(t('avatar.avatarUploadError'))
        }
      }

      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
        ...(customFields.errorPaths as Path<CompanySiteFormValues>[]),
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(t('companySites.form.genericError'))
      }
    }
  }

  // EDIT mode: logo actions hit the backend immediately and refresh the
  // cached detail so the form (and any open detail view) reflects the change.
  const handleLogoUpload = async (file: File) => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await uploadCompanySiteLogo(mode.companySite.id, file)
    queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
    onSiteChange?.()
  }

  const handleLogoRemove = async () => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await deleteCompanySiteLogo(mode.companySite.id)
    queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
    onSiteChange?.()
  }

  // EDIT mode only: sets this site as the company's default one (AC-020). The
  // affordance itself is gated by the caller on `!is_default`.
  const [settingDefault, setSettingDefault] = useState(false)
  const handleSetDefault = async () => {
    if (mode.type !== 'edit') {
      return
    }
    setSettingDefault(true)
    try {
      const updated = await setDefaultCompanySite(mode.companySite.id)
      queryClient.setQueryData(['company-sites', 'detail', mode.companySite.id], updated)
      toast.success(t('companySites.form.defaultSet'))
      onSiteChange?.()
    } catch {
      toast.error(t('companySites.form.defaultError'))
    } finally {
      setSettingDefault(false)
    }
  }

  return {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    profileValid,
    revalidateSignal,
    personalDataFieldPermission,
    pendingLogo,
    setPendingLogo,
    banksDraft,
    setBanksDraft,
    selectedCompanyItem,
    onSubmit,
    handleLogoUpload,
    handleLogoRemove,
    canUploadLogo: canAction('upload_logo'),
    canRemoveLogo: canAction('delete_logo'),
    // Set-default (AC-020): visible only in edit mode, on a non-default site,
    // gated by the resolved `set_default` action permission.
    canSetDefault: isEdit && !mode.companySite.is_default && canAction('set_default'),
    settingDefault,
    handleSetDefault,
  }
}
