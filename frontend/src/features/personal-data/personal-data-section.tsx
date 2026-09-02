import { IdCard, MapPin, Phone } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { FormSection } from '@/components/form-section'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { cardOwnerRef } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

interface PersonalDataSectionProps {
  /** The buffered personal-data draft owned by the parent (always present). */
  value: PersonalDataDraft
  /** Emits the next draft. */
  onChange: (next: PersonalDataDraft) => void
  /**
   * Resolves gating for the card fields and the contacts/addresses sections
   * (spec 0008), forwarded as-is to every child. Optional: omitting it keeps
   * today's ungated behaviour (self-service profile, AC-013).
   */
  fieldPermission?: PersonalDataFieldPermissionResolver
  /**
   * Forwarded verbatim to the card: bumped by the owner form when a save is
   * refused, so the buffered card marks its own missing fields (see
   * `PersonalDataCardForm`).
   */
  revalidateSignal?: number
}

/**
 * Reusable, owner-agnostic personal-data section, aligned with the Users form
 * look: the card and the contacts/addresses managers each sit in their own
 * `FormSection` (icon + title + count badge), the editors opening in the shared
 * dialog. Controlled/buffered: the parent owns the draft tree; contacts/addresses
 * persist immediately when the card already exists (`cardOwnerRef`), otherwise
 * they stay buffered until the parent form is saved (ADR 0012). The card is
 * always active; a section whose gating is not visible is not rendered.
 */
export function PersonalDataSection({
  value,
  onChange,
  fieldPermission,
  revalidateSignal,
}: PersonalDataSectionProps) {
  const { t } = useTranslation()
  const persistence = cardOwnerRef(value)
  const contactsVisible = fieldPermission?.('personal_data.contacts').visible ?? true
  const addressesVisible = fieldPermission?.('personal_data.addresses').visible ?? true

  return (
    <section className="flex flex-col gap-4">
      <FormSection icon={IdCard} title={t('personalData.section.title')}>
        <PersonalDataCardForm
          value={value}
          onChange={onChange}
          fieldPermission={fieldPermission}
          revalidateSignal={revalidateSignal}
        />
      </FormSection>

      {contactsVisible && (
        <FormSection
          icon={Phone}
          title={t('personalData.contacts.title')}
          aside={<Badge variant="secondary">{value.contacts.length}</Badge>}
        >
          <ContactsManager
            value={value.contacts}
            onChange={(contacts) => onChange({ ...value, contacts })}
            fieldPermission={fieldPermission}
            showHeader={false}
            persistence={persistence}
          />
        </FormSection>
      )}

      {addressesVisible && (
        <FormSection
          icon={MapPin}
          title={t('personalData.addresses.title')}
          aside={<Badge variant="secondary">{value.addresses.length}</Badge>}
        >
          <AddressesManager
            value={value.addresses}
            onChange={(addresses) => onChange({ ...value, addresses })}
            fieldPermission={fieldPermission}
            showHeader={false}
            persistence={persistence}
          />
        </FormSection>
      )}
    </section>
  )
}
