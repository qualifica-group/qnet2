import { useTranslation } from 'react-i18next'
import { Building2, IdCard, MapPin, Phone } from 'lucide-react'
import { type Control } from 'react-hook-form'
import { AvatarUpload } from '@/components/avatar-upload'
import { FormSection } from '@/components/form-section'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

/** A company site owns at most one address (spec 0020, backend-capped). */
const MAX_ADDRESSES = 1

interface ProfileTabContentProps {
  mode: CompanySiteFormMode
  control: Control<CompanySiteFormValues>
  siteName: string
  /** The buffered anagraphic card (always a company). */
  profileDraft: PersonalDataDraft
  /** Emits the next anagraphic draft. */
  setProfileDraft: (next: PersonalDataDraft) => void
  /** Bumped by the owner form when a save is refused, forwarded to the card. */
  revalidateSignal: number
  /** Gating for the shared card/contacts/address components (spec 0008). */
  personalDataFieldPermission: PersonalDataFieldPermissionResolver
  onLogoFileSelected: (file: File | null) => void
  onLogoUpload: (file: File) => Promise<void>
  onLogoRemove: () => Promise<void>
  canUploadLogo: boolean
  canRemoveLogo: boolean
}

/**
 * Profile blocks of the company-site form: the site's own `name` scalar +
 * `notes` and its logo, then the conventional anagraphic toolkit — the
 * personal-data card (locked to `company`, never a natural person), the
 * contacts manager and a single address. Contacts/addresses open in the
 * shared dialog and persist immediately once the card exists (`cardOwnerRef`),
 * otherwise they stay buffered until the form is saved (mirrors the Registries
 * module).
 */
export function ProfileTabContent({
  mode,
  control,
  siteName,
  profileDraft,
  setProfileDraft,
  revalidateSignal,
  personalDataFieldPermission,
  onLogoFileSelected,
  onLogoUpload,
  onLogoRemove,
  canUploadLogo,
  canRemoveLogo,
}: ProfileTabContentProps) {
  const { t } = useTranslation()
  const persistence = cardOwnerRef(profileDraft)
  const contactsVisible = personalDataFieldPermission('personal_data.contacts').visible
  const addressesVisible = personalDataFieldPermission('personal_data.addresses').visible

  return (
    <>
      <FormSection
        icon={Building2}
        title={t('companySites.form.sections.general.title')}
        description={t('companySites.form.sections.general.description')}
      >
        {mode.type === 'edit' ? (
          <AvatarUpload
            mode="immediate"
            label={t('companySites.form.logoLabel')}
            name={siteName}
            avatarUrl={mode.companySite.logo_url}
            onUpload={onLogoUpload}
            onRemove={onLogoRemove}
            canUpload={canUploadLogo}
            canRemove={canRemoveLogo}
          />
        ) : (
          <AvatarUpload
            mode="deferred"
            label={t('companySites.form.logoLabel')}
            name={siteName}
            onFileSelected={onLogoFileSelected}
          />
        )}

        <MetaField control={control} name="name" metaKey="name" label={t('companySites.form.name')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="notes" metaKey="notes" label={t('companySites.form.notes')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Textarea disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>
      </FormSection>

      <FormSection
        icon={IdCard}
        title={t('companySites.form.sections.identity.title')}
        description={t('companySites.form.sections.identity.description')}
      >
        <PersonalDataCardForm
          value={profileDraft}
          onChange={setProfileDraft}
          fieldPermission={personalDataFieldPermission}
          lockType="company"
          revalidateSignal={revalidateSignal}
        />
      </FormSection>

      {contactsVisible && (
        <FormSection
          icon={Phone}
          title={t('companySites.form.sections.contacts.title')}
          description={t('companySites.form.sections.contacts.description')}
          aside={<Badge variant="secondary">{profileDraft.contacts.length}</Badge>}
        >
          <ContactsManager
            value={profileDraft.contacts}
            onChange={(contacts) => setProfileDraft({ ...profileDraft, contacts })}
            fieldPermission={personalDataFieldPermission}
            showHeader={false}
            persistence={persistence}
            createMode={mode.type === 'create'}
          />
        </FormSection>
      )}

      {addressesVisible && (
        <FormSection
          icon={MapPin}
          title={t('companySites.form.sections.address.title')}
          description={t('companySites.form.sections.address.description')}
        >
          <AddressesManager
            value={profileDraft.addresses}
            onChange={(addresses) => setProfileDraft({ ...profileDraft, addresses })}
            fieldPermission={personalDataFieldPermission}
            showHeader={false}
            persistence={persistence}
            showSiteType
            maxItems={MAX_ADDRESSES}
            createMode={mode.type === 'create'}
          />
        </FormSection>
      )}
    </>
  )
}
