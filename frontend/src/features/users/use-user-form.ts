import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import {
  describeAddressIssues,
  describeCardIssues,
  describeContactIssues,
  isPersonalDataCardValid,
  type BlockedSection,
} from '@/features/personal-data/personal-data-issues'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { usePersonalDataByOwner } from '@/features/personal-data/use-personal-data'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'
import {
  createUser,
  deleteUserAvatar,
  updateUser,
  uploadUserAvatar,
} from '@/features/users/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/users/user-form-payload'
import {
  useUserFormDefaults,
  useUserFormHydration,
} from '@/features/users/user-form-defaults'
import {
  collectProductLinesServerError,
  PRODUCT_LINES_FIELD,
  SERVER_ERROR_FIELDS,
} from '@/features/users/user-form-server-errors'
import {
  buildCreateUserSchema,
  buildUpdateUserSchema,
  type CreateUserFormValues,
  type UpdateUserFormValues,
} from '@/features/users/user-schema'
import type { UserDetail } from '@/features/users/types'
import type { UserFormMode } from '@/features/users/user-form'

/**
 * Distinct "not yet seeded" marker for the profile buffer: `undefined` is the
 * query's loading state and `null` is a valid "user has no card" result, so a
 * dedicated sentinel is needed to detect the first resolved value.
 */
const SEED_PENDING = Symbol('seed-pending')

export type UserFormValues = CreateUserFormValues & UpdateUserFormValues

interface UseUserFormArgs {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onAvatarChange?: () => void
}

/**
 * Owns every non-render concern of `UserForm`: RHF/Zod wiring, the buffered
 * personal-data card, server 422 mapping and the avatar mutations. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useUserForm({ mode, onSuccess, onAvatarChange }: UseUserFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { canAction, field: fieldPermission } = useResourcePermissions()

  // Adapts the resolved authorization metadata to the personal-data domain's
  // own gating shape (spec 0008 D3): the shared PersonalDataSection/CardForm/
  // ContactsManager/AddressesManager stay decoupled from `@/features/authorization`
  // and only see `visible/editable/required/disabled/readonly` for a dot-path key.
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
  // CREATE mode only: avatar chosen before the user exists, uploaded after save.
  const [pendingAvatar, setPendingAvatar] = useState<File | null>(null)
  // The buffered personal-data tree, owned here for both create and edit. The
  // card is always active (no add/remove): it starts blank and is submitted
  // inside the single user payload (ADR 0012).
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(
    emptyPersonalDataDraft,
  )
  // Tracks the loaded card we already seeded the buffer from, so the seed runs
  // once per fetch and the user's subsequent edits are never clobbered.
  const [seededFrom, setSeededFrom] = useState<unknown>(SEED_PENDING)

  const isEdit = mode.type === 'edit'

  // EDIT: load the user's card once and seed the buffer from it. The fetch is
  // disabled in create mode (no owner yet).
  const profileQuery = usePersonalDataByOwner(
    { type: 'user', id: mode.type === 'edit' ? mode.user.id : 0 },
    isEdit,
  )

  // Seed the buffer the first time the card resolves. Adjusting state during
  // render (React's recommended pattern for syncing to changing data) avoids an
  // effect that would re-render twice; the `seededFrom` guard makes it run once.
  if (isEdit && profileQuery.data !== undefined && seededFrom !== profileQuery.data) {
    setSeededFrom(profileQuery.data)
    setProfileDraft(
      profileQuery.data ? cardToDraft(profileQuery.data) : emptyPersonalDataDraft(),
    )
  }

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'users',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.user.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateUserSchema(t, customFields.schema)
        : buildCreateUserSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useUserFormDefaults(mode, customFields.defaultValues)

  // EDIT: pre-known {id, label} for the role/relation pickers (AC-016) plus the
  // persisted competence pairs, so every control shows its labels immediately.
  const hydration = useUserFormHydration(mode)

  const form = useForm<UserFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // The user's display name is derived from the personal-data card (single source
  // of truth) — there is no `name` field. Used here only for the avatar initials.
  const profileName =
    profileDraft.type === 'company'
      ? (profileDraft.company_name ?? '')
      : [profileDraft.first_name, profileDraft.last_name]
          .filter(Boolean)
          .join(' ')

  // The personal-data card is mandatory: block the save until the required
  // identity fields (name + surname, or company name) are valid. The card form
  // shows the field-level messages inline; this also drives the Identity tab's
  // error indicator (spec 0015 AC-014), since the buffer lives outside RHF.
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

  const onSubmit = async (values: UserFormValues) => {
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
    }

    try {
      if (mode.type === 'edit') {
        const saved = await updateUser(
          mode.user.id,
          buildUpdatePayload(values, mode.user, profileDraft, personalDataFieldPermission),
        )
        toast.success(t('users.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createUser(
        buildCreatePayload(values, profileDraft, personalDataFieldPermission),
      )
      toast.success(t('users.form.created'))

      // The user exists now; upload the deferred avatar before handing off.
      // A failed avatar upload must not lose the created user — surface a toast
      // and proceed, returning the freshest resource we have.
      if (pendingAvatar) {
        try {
          const withAvatar = await uploadUserAvatar(created.id, pendingAvatar)
          onSuccess(withAvatar)
          return
        } catch {
          toast.error(t('avatar.avatarUploadError'))
        }
      }

      onSuccess(created)
    } catch (error) {
      const errorFields: Path<UserFormValues>[] = [
        ...SERVER_ERROR_FIELDS,
        ...(customFields.errorPaths as Path<UserFormValues>[]),
      ]
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('users.form.genericError'))
        return
      }
      const productLinesMessage = collectProductLinesServerError(error)
      if (productLinesMessage) {
        form.setError(PRODUCT_LINES_FIELD, { message: productLinesMessage })
      }
    }
  }

  // EDIT mode: avatar actions hit the backend immediately and refresh the
  // cached detail so the form (and any open detail view) reflects the change.
  const handleAvatarUpload = async (file: File) => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await uploadUserAvatar(mode.user.id, file)
    queryClient.setQueryData(['users', 'detail', mode.user.id], updated)
    onAvatarChange?.()
  }

  const handleAvatarRemove = async () => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await deleteUserAvatar(mode.user.id)
    queryClient.setQueryData(['users', 'detail', mode.user.id], updated)
    onAvatarChange?.()
  }

  return {
    form,
    isEdit,
    serverError,
    pendingAvatar,
    setPendingAvatar,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    revalidateSignal,
    blockedSection,
    // Role/relation picker hydration and the known competence pairs (AC-016).
    ...hydration,
    onSubmit,
    handleAvatarUpload,
    handleAvatarRemove,
    // Per-field gating for the personal-data section (spec 0008), adapted from
    // this resource's resolved `ResourcePermissions` and injected by prop.
    personalDataFieldPermission,
    // Metadata-gated (spec 0004): upload/remove-avatar actions, meaningful in
    // edit mode only (create mode defers the upload until the user exists).
    canUploadAvatar: canAction('upload_avatar'),
    canRemoveAvatar: canAction('delete_avatar'),
  }
}
