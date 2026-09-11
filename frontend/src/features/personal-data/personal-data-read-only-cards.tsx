import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordCard, RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Mail, MapPin } from 'lucide-react'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardToDraft } from '@/features/personal-data/drafts'
import type { PersonalDataCard, PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'

/**
 * The anagraphic card's contacts and addresses as two read-only `RecordCard`s,
 * for the record screens of every owner that has one (anagrafica, referente,
 * and whoever comes next).
 *
 * It exists to kill a real duplication: `registry-detail.tsx` and
 * `referent-detail.tsx` each carried their own copy of the read-only
 * permission resolver, the no-op change handler and the two manager mounts —
 * three chances for the two screens to drift apart on something neither of
 * them owns.
 *
 * The managers themselves are reused UNCHANGED, in read-only mode, so what the
 * record shows can never diverge from what the edit form would show (spec
 * 0020/0016). They are the same components in both places, not a second
 * rendering of the same data.
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

interface PersonalDataReadOnlyCardsProps {
  /** `null` for the pathological owner with no card yet: both blocks then show the empty marker. */
  card: PersonalDataCard | null
  contactsTitle: string
  addressesTitle: string
  /** Enables the addresses' "site type" column, which only the anagrafiche use. */
  showSiteType?: boolean
}

export function PersonalDataReadOnlyCards({
  card,
  contactsTitle,
  addressesTitle,
  showSiteType = false,
}: PersonalDataReadOnlyCardsProps) {
  const draft = card ? cardToDraft(card) : null

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
              onChange={noopChange}
              fieldPermission={READ_ONLY_FIELD_PERMISSION}
              showHeader={false}
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
              onChange={noopChange}
              fieldPermission={READ_ONLY_FIELD_PERMISSION}
              showHeader={false}
              showSiteType={showSiteType}
            />
          ) : (
            <DetailEmpty />
          )}
        </RecordSection>
      </RecordCard>
    </>
  )
}
