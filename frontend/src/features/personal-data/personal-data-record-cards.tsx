import { useState, type HTMLAttributes } from 'react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordCard, RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Mail, MapPin } from 'lucide-react'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardOwnerRef, cardToDraft } from '@/features/personal-data/drafts'
import type {
  OwnerRef,
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
 * can never diverge from what a form would show (spec 0020/0016).
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

/** Extra attributes on each card's box (e.g. the `data-*` anchor a refused save scrolls to). */
type CardBoxProps = HTMLAttributes<HTMLDivElement> & Record<`data-${string}`, string>

interface PersonalDataChildCardsProps {
  /** The contacts/addresses shown; `null` = no card at all, both blocks show the empty marker. */
  draft: PersonalDataDraft | null
  contactsTitle: string
  addressesTitle: string
  /** Enables the addresses' "site type" column, which only the anagrafiche use. */
  showSiteType?: boolean
  /** Read-only when absent. */
  fieldPermission?: PersonalDataFieldPermissionResolver
  /** When present, each change is written straight to this owner (immediate persistence). */
  persistence?: OwnerRef
  /** Receives every change of the buffer; absent = read-only. */
  onChange?: (patch: Pick<PersonalDataDraft, 'contacts'> | Pick<PersonalDataDraft, 'addresses'>) => void
  contactsBoxProps?: CardBoxProps
  addressesBoxProps?: CardBoxProps
}

/**
 * The two cards over a buffer the caller owns: read-only, buffered (a create
 * form keeps the changes until its Save) or persisted at once (`persistence`).
 */
export function PersonalDataChildCards({
  draft,
  contactsTitle,
  addressesTitle,
  showSiteType = false,
  fieldPermission,
  persistence,
  onChange,
  contactsBoxProps,
  addressesBoxProps,
}: PersonalDataChildCardsProps) {
  const permission = onChange && fieldPermission ? fieldPermission : READ_ONLY_FIELD_PERMISSION

  return (
    <>
      <div {...contactsBoxProps}>
        <RecordCard className="p-4">
          <RecordSection
            title={contactsTitle}
            icon={<Mail />}
            action={draft ? <Badge variant="secondary">{draft.contacts.length}</Badge> : null}
          >
            {draft ? (
              <ContactsManager
                value={draft.contacts}
                onChange={onChange ? (contacts) => onChange({ contacts }) : noopChange}
                fieldPermission={permission}
                showHeader={false}
                persistence={persistence}
              />
            ) : (
              <DetailEmpty />
            )}
          </RecordSection>
        </RecordCard>
      </div>

      <div {...addressesBoxProps}>
        <RecordCard className="p-4">
          <RecordSection
            title={addressesTitle}
            icon={<MapPin />}
            action={draft ? <Badge variant="secondary">{draft.addresses.length}</Badge> : null}
          >
            {draft ? (
              <AddressesManager
                value={draft.addresses}
                onChange={onChange ? (addresses) => onChange({ addresses }) : noopChange}
                fieldPermission={permission}
                showHeader={false}
                showSiteType={showSiteType}
                persistence={persistence}
              />
            ) : (
              <DetailEmpty />
            )}
          </RecordSection>
        </RecordCard>
      </div>
    </>
  )
}

/** In-place management of a persisted card's contacts/addresses. */
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
  showSiteType?: boolean
  editing?: PersonalDataCardsEditing
}

/**
 * The cards of a PERSISTED card: read-only by default; with `editing` each
 * add/edit/remove is written straight to the card (spec 0200 D-4).
 */
export function PersonalDataRecordCards({ card, editing, ...titles }: PersonalDataRecordCardsProps) {
  // The managers' buffer: re-seeded whenever the owner hands a new card, and
  // updated by the managers' own synced rows in between (adjust-on-prop-change).
  const [buffer, setBuffer] = useState(() => ({ card, draft: card ? cardToDraft(card) : null }))
  let draft = buffer.draft
  if (buffer.card !== card) {
    draft = card ? cardToDraft(card) : null
    setBuffer({ card, draft })
  }

  const persistence = editing && draft ? cardOwnerRef(draft) : undefined

  const handleChange = (patch: Partial<PersonalDataDraft>) => {
    setBuffer((current) => ({ ...current, draft: current.draft ? { ...current.draft, ...patch } : null }))
    editing?.onChanged()
  }

  return (
    <PersonalDataChildCards
      {...titles}
      draft={draft}
      fieldPermission={editing?.fieldPermission}
      persistence={persistence}
      onChange={persistence ? handleChange : undefined}
    />
  )
}
