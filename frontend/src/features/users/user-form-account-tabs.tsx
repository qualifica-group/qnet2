import { IdCard, KeyRound, MapPin, Phone, ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { AvatarUpload } from '@/components/avatar-upload'
import { FormSection } from '@/components/form-section'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { FormControl, FormDescription } from '@/components/ui/form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { toRelationFieldRefs } from '@/components/form/relation-field-ref'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserFormValues } from '@/features/users/use-user-form'

/**
 * Content of the account-identity tabs (Identity, Credentials, Access, Contacts,
 * Addresses): the fields that predate the employment profile (spec 0015),
 * unchanged in behaviour and simply relocated under the new tab strip. The
 * employment tabs (Profile/Contract/Contract data) live in the sibling
 * `user-form-employment-tabs.tsx`.
 */

interface IdentityTabContentProps {
  mode: UserFormMode
  profileName: string
  isLoading: boolean
  isError: boolean
  onRetry: () => void
  profileDraft: PersonalDataDraft
  setProfileDraft: (next: PersonalDataDraft) => void
  /** Bumped by the owner form when a save is refused, forwarded to the card. */
  revalidateSignal: number
  personalDataFieldPermission: PersonalDataFieldPermissionResolver
  setPendingAvatar: (file: File | null) => void
  handleAvatarUpload: (file: File) => Promise<void>
  handleAvatarRemove: () => Promise<void>
  canUploadAvatar: boolean
  canRemoveAvatar: boolean
}

export function IdentityTabContent({
  mode,
  profileName,
  isLoading,
  isError,
  onRetry,
  profileDraft,
  setProfileDraft,
  revalidateSignal,
  personalDataFieldPermission,
  setPendingAvatar,
  handleAvatarUpload,
  handleAvatarRemove,
  canUploadAvatar,
  canRemoveAvatar,
}: IdentityTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={IdCard}
      title={t('users.form.sections.identity.title')}
      description={t('users.form.sections.identity.description')}
    >
      {mode.type === 'edit' ? (
        <AvatarUpload
          mode="immediate"
          label={t('users.form.avatarLabel')}
          name={profileName}
          avatarUrl={mode.user.avatar_url}
          onUpload={handleAvatarUpload}
          onRemove={handleAvatarRemove}
          canUpload={canUploadAvatar}
          canRemove={canRemoveAvatar}
        />
      ) : (
        <AvatarUpload
          mode="deferred"
          label={t('users.form.avatarLabel')}
          name={profileName}
          onFileSelected={setPendingAvatar}
        />
      )}

      {isLoading ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Skeleton className="h-9" />
            <Skeleton className="h-9" />
          </div>
          <Skeleton className="h-9" />
        </div>
      ) : isError ? (
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm text-destructive" role="alert">
            {t('personalData.section.loadError')}
          </p>
          <Button type="button" variant="outline" size="sm" onClick={onRetry}>
            {t('common.retry')}
          </Button>
        </div>
      ) : (
        <PersonalDataCardForm
          value={profileDraft}
          onChange={setProfileDraft}
          fieldPermission={personalDataFieldPermission}
          revalidateSignal={revalidateSignal}
        />
      )}
    </FormSection>
  )
}

interface CredentialsTabContentProps {
  control: Control<UserFormValues>
  isEdit: boolean
}

export function CredentialsTabContent({ control, isEdit }: CredentialsTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={KeyRound}
      title={t('users.form.sections.credentials.title')}
      description={t('users.form.sections.credentials.description')}
    >
      <MetaField control={control} name="email" metaKey="email" label={t('users.form.email')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="email"
              autoComplete="email"
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="password"
        metaKey="password"
        label={t(isEdit ? 'users.form.newPassword' : 'users.form.password')}
        description={
          isEdit ? <FormDescription>{t('users.form.passwordEditHint')}</FormDescription> : undefined
        }
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="password"
              autoComplete="new-password"
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="password_confirmation"
        metaKey="password"
        label={t('users.form.confirmPassword')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="password"
              autoComplete="new-password"
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}

interface AccessTabContentProps {
  control: Control<UserFormValues>
  selectedRoleItems: ForSelectItem[]
}

export function AccessTabContent({ control, selectedRoleItems }: AccessTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={ShieldCheck}
      title={t('users.form.sections.access.title')}
      description={t('users.form.sections.access.description')}
    >
      <RelationMultiSelectField
        control={control}
        name="roles"
        metaKey="roles"
        label={t('users.form.roles')}
        resource={ROLES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('users.form.rolesSearch')}
        selected={toRelationFieldRefs(selectedRoleItems)}
        placeholder={t('users.form.rolesPlaceholder')}
        emptyLabel={t('users.form.rolesEmpty')}
        errorLabel={t('users.form.rolesError')}
        removeLabel={t('users.form.rolesRemove')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="is_active"
        metaKey="is_active"
        label={t('users.form.is_active')}
        description={<FormDescription>{t('users.form.isActiveHint')}</FormDescription>}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}

interface ContactsTabContentProps {
  profileDraft: PersonalDataDraft
  setProfileDraft: (next: PersonalDataDraft) => void
  personalDataFieldPermission: PersonalDataFieldPermissionResolver
  /** Forwarded to the shared manager's quick-create fields (create mode only). */
  createMode: boolean
}

export function ContactsTabContent({
  profileDraft,
  setProfileDraft,
  personalDataFieldPermission,
  createMode,
}: ContactsTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Phone}
      title={t('users.form.sections.contacts.title')}
      description={t('users.form.sections.contacts.description')}
      aside={<Badge variant="secondary">{profileDraft.contacts.length}</Badge>}
    >
      <ContactsManager
        value={profileDraft.contacts}
        onChange={(contacts) => setProfileDraft({ ...profileDraft, contacts })}
        fieldPermission={personalDataFieldPermission}
        showHeader={false}
        persistence={cardOwnerRef(profileDraft)}
        createMode={createMode}
      />
    </FormSection>
  )
}

export function AddressesTabContent({
  profileDraft,
  setProfileDraft,
  personalDataFieldPermission,
  createMode,
}: ContactsTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={MapPin}
      title={t('users.form.sections.addresses.title')}
      description={t('users.form.sections.addresses.description')}
      aside={<Badge variant="secondary">{profileDraft.addresses.length}</Badge>}
    >
      <AddressesManager
        value={profileDraft.addresses}
        onChange={(addresses) => setProfileDraft({ ...profileDraft, addresses })}
        fieldPermission={personalDataFieldPermission}
        showHeader={false}
        persistence={cardOwnerRef(profileDraft)}
        createMode={createMode}
      />
    </FormSection>
  )
}
