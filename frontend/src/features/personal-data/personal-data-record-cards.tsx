import { useState } from 'react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordCard, RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Mail, MapPin } from 'lucide-react'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardOwnerRef, cardToDraft } from '@/features/personal-data/drafts'
import type {
  PersonalDataCard,
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

/**
 * The anagraphic card's contacts and addresses as two `RecordCard`s, for the
 * record screens of every owner that has one (anagrafica, referente, and
 * whoever comes next) — one component, so the screens cannot drift apart on
 * something none of them owns.
 *
 * The managers are the SAME components the forms use, so what the record shows
 * can never diverge from what a form would show (spec 0020/0016). Read-only by
 * default; with `editing` they write each add/edit/remove straight to the card
 * (immediate persistence), the in-place record of spec 0200 D-4.
 */

/** Visible, never editable: no add/edit/remove affordance reaches the DOM. */
const READ_ONLY_FIELD_PERMISSION: PersonalDataFieldPermissionResolver = () => ({
  visible: true,
  editable: false,
  required: false,
  disabled: false,
  readonly: true,
})

/** The read-only managers never call it. */
function noopChange(): void {}

/** In-place management of the card's contacts/addresses. */
export interface PersonalDataCardsEditing {
  /** The owner's `personal_data.contacts`/`personal_data.addresses` gating. */
  fieldPermission: PersonalDataFieldPermissionResolver
  /** Called after each persisted change, so the owner refreshes its record. */
  onChanged: () => void
}

interface PersonalDataRecordCardsProps {
  /** `null` for the pathological owner with no card yet: both blocks then show the empty marker (never editable). */
  card: PersonalDataCard | null
  contactsTitle: string
  addressesTitle: string
  /** Enables the addresses' "site type" column, which only the anagrafiche use. */
  showSiteType?: boolean
  editing?: PersonalDataCardsEditing
}

export function PersonalDataRecordCards({
  card,
  contactsTitle,
  addressesTitle,
  showSiteType = false,
  editing,
}: PersonalDataRecordCardsProps) {
  // The managers' buffer: re-seeded whenever the owner hands a new card, and
  // updated by the managers' own synced rows in between (adjust-on-prop-change).
  const [buffer, setBuffer] = useState(() => ({ card, draft: card ? cardToDraft(card) : null }))
  let draft = buffer.draft
  if (buffer.card !== card) {
    draft = card ? cardToDraft(card) : null
    setBuffer({ card, draft })
  }

  const fieldPermission = editing?.fieldPermission ?? READ_ONLY_FIELD_PERMISSION
  const persistence = editing && draft ? cardOwnerRef(draft) : undefined

  const update = (patch: Partial<PersonalDataDraft>) => {
    setBuffer((current) => ({ ...current, draft: current.draft ? { ...current.draft, ...patch } : null }))
    editing?.onChanged()
  }

  return (
    <>
      <RecordCard className="p-4">
        <RecordSection
          title={contactsTitle}
          icon={<Mail />}
          action={draft ? <Badge variant="secondary">{draft.contacts.length}</Badge> : null}
        >
          {draft ? (
            <ContactsManager
              value={draft.contacts}
              onChange={persistence ? (contacts) => update({ contacts }) : noopChange}
              fieldPermission={persistence ? fieldPermission : READ_ONLY_FIELD_PERMISSION}
              showHeader={false}
              persistence={persistence}
            />
          ) : (
            <DetailEmpty />
          )}
        </RecordSection>
      </RecordCard>

      <RecordCard className="p-4">
        <RecordSection
          title={addressesTitle}
          icon={<MapPin />}
          action={draft ? <Badge variant="secondary">{draft.addresses.length}</Badge> : null}
        >
          {draft ? (
            <AddressesManager
              value={draft.addresses}
              onChange={persistence ? (addresses) => update({ addresses }) : noopChange}
              fieldPermission={persistence ? fieldPermission : READ_ONLY_FIELD_PERMISSION}
              showHeader={false}
              showSiteType={showSiteType}
              persistence={persistence}
            />
          ) : (
            <DetailEmpty />
          )}
        </RecordSection>
      </RecordCard>
    </>
  )
}
