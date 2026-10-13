import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { IdCard, MapPin, Phone } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardOwnerRef, cardToDraft } from '@/features/personal-data/drafts'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import {
  PERSONAL_DATA_CARD_FIELD,
  type PersonalDataCardBuffer,
} from '@/features/personal-data/personal-data-identity-record-section'
import {
  PersonalDataIdentityRows,
  type PersonalDataIdentity,
} from '@/features/personal-data/personal-data-identity-rows'
import type {
  OwnerRef,
  PersonalDataCard,
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

/** A company site owns at most one address (spec 0020, backend-capped). */
const MAX_ADDRESSES = 1

interface CompanySiteIdentitySectionProps {
  /** What the closed row shows: the persisted card on a detail (`null` only for one with none), the draft on create. */
  identity: PersonalDataIdentity | null
  buffer: PersonalDataCardBuffer
  inline: InlineEdit
}

/**
 * "Dati anagrafici" of a company site: the fiscal identity as ONE in-place row
 * opening on the card form, locked to `company` (a site is never a natural
 * person) — the twin of `PersonalDataIdentityRecordSection`, which lets the
 * type be switched.
 */
export function CompanySiteIdentitySection({ identity, buffer, inline }: CompanySiteIdentitySectionProps) {
  const { t } = useTranslation()
  const title = t('companySites.form.sections.identity.title')

  return (
    <RecordSection title={title} icon={<IdCard />}>
      <RecordInlineField
        field={PERSONAL_DATA_CARD_FIELD}
        metaKey="personal_data.type"
        label={title}
        inline={inline}
        layout="block"
        editor={
          <PersonalDataCardForm
            value={buffer.draft}
            onChange={buffer.setDraft}
            fieldPermission={buffer.fieldPermission}
            lockType="company"
            revalidateSignal={buffer.revalidateSignal}
          />
        }
      >
        {identity ? <PersonalDataIdentityRows card={identity} /> : <DetailEmpty />}
      </RecordInlineField>
    </RecordSection>
  )
}

interface CompanySiteChildSectionsProps {
  draft: PersonalDataDraft
  onChange: (patch: Partial<PersonalDataDraft>) => void
  fieldPermission: PersonalDataFieldPermissionResolver
  /** A persisted card writes each change straight to its owner (immediate persistence). */
  persistence?: OwnerRef
  /** A create form's buffer: the managers lay out their quick fields ready to fill. */
  createMode?: boolean
}

/** The contacts and the single address over a buffer the caller owns, each section gated by its field permission. */
function CompanySiteChildSections({
  draft,
  onChange,
  fieldPermission,
  persistence,
  createMode = false,
}: CompanySiteChildSectionsProps) {
  const { t } = useTranslation()

  return (
    <>
      {fieldPermission('personal_data.contacts').visible ? (
        <RecordSection
          title={t('companySites.form.sections.contacts.title')}
          icon={<Phone />}
          action={<Badge variant="secondary">{draft.contacts.length}</Badge>}
        >
          <ContactsManager
            value={draft.contacts}
            onChange={(contacts) => onChange({ contacts })}
            fieldPermission={fieldPermission}
            showHeader={false}
            persistence={persistence}
            createMode={createMode}
          />
        </RecordSection>
      ) : null}

      {fieldPermission('personal_data.addresses').visible ? (
        <RecordSection title={t('companySites.form.sections.address.title')} icon={<MapPin />}>
          <AddressesManager
            value={draft.addresses}
            onChange={(addresses) => onChange({ addresses })}
            fieldPermission={fieldPermission}
            showHeader={false}
            persistence={persistence}
            showSiteType
            maxItems={MAX_ADDRESSES}
            createMode={createMode}
          />
        </RecordSection>
      ) : null}
    </>
  )
}

/** The create form's contacts and address: buffered until Salva. */
export function CompanySiteDraftChildSections({
  draft,
  setDraft,
  fieldPermission,
}: {
  draft: PersonalDataDraft
  setDraft: (next: PersonalDataDraft) => void
  fieldPermission: PersonalDataFieldPermissionResolver
}) {
  return (
    <CompanySiteChildSections
      draft={draft}
      onChange={(patch) => setDraft({ ...draft, ...patch })}
      fieldPermission={fieldPermission}
      createMode
    />
  )
}

interface CompanySitePersistedChildSectionsProps {
  card: PersonalDataCard | null
  fieldPermission: PersonalDataFieldPermissionResolver
  /** Called after each persisted change, so the owner refreshes its record. */
  onChanged: () => void
}

/**
 * The persisted card's contacts and address, inside the record card of the
 * detail: each add/edit/remove is written straight to the card. The managers'
 * buffer is re-seeded whenever the owner hands a new card, and updated by their
 * own synced rows in between (adjust-on-prop-change).
 */
export function CompanySitePersistedChildSections({
  card,
  fieldPermission,
  onChanged,
}: CompanySitePersistedChildSectionsProps) {
  const [buffer, setBuffer] = useState(() => ({ card, draft: card ? cardToDraft(card) : null }))
  let draft = buffer.draft
  if (buffer.card !== card) {
    draft = card ? cardToDraft(card) : null
    setBuffer({ card, draft })
  }

  if (!draft) {
    return null
  }

  const persistence = cardOwnerRef(draft)

  return (
    <CompanySiteChildSections
      draft={draft}
      onChange={(patch) => {
        setBuffer((current) => ({ ...current, draft: current.draft ? { ...current.draft, ...patch } : null }))
        onChanged()
      }}
      fieldPermission={fieldPermission}
      persistence={persistence}
    />
  )
}
