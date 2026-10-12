import { IdCard } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import {
  PersonalDataIdentityRows,
  type PersonalDataIdentity,
} from '@/features/personal-data/personal-data-identity-rows'
import type { PersonalDataDraft, PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'

/**
 * Key of the anagraphic card's single in-place row (spec 0200 D-4). Not an RHF
 * path: the card is a buffered draft, so this only names the open editor.
 */
export const PERSONAL_DATA_CARD_FIELD = 'personal_data'

/** The buffered anagraphic card a record section edits: its draft, its gating and the refused-save signal. */
export interface PersonalDataCardBuffer {
  draft: PersonalDataDraft
  setDraft: (next: PersonalDataDraft) => void
  revalidateSignal: number
  fieldPermission: PersonalDataFieldPermissionResolver
}

interface PersonalDataIdentityRecordSectionProps {
  /** The section title, also the row's (assistive) label. */
  title: string
  /** What the closed row shows: the persisted card on a detail (`null` only for one with none), the draft on create. */
  identity: PersonalDataIdentity | null
  buffer: PersonalDataCardBuffer
  inline: InlineEdit
}

/**
 * "Dati anagrafici" of a record owning an anagraphic card (anagrafica,
 * referente), the same on the detail and on create: the fiscal identity rows
 * as ONE in-place row opening on the card form — the card is one buffered
 * object, saved whole, and it names the record (the header follows it). Its
 * pencil follows `personal_data.type`, the card's mandatory field.
 */
export function PersonalDataIdentityRecordSection({
  title,
  identity,
  buffer,
  inline,
}: PersonalDataIdentityRecordSectionProps) {
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
            revalidateSignal={buffer.revalidateSignal}
          />
        }
      >
        {identity ? <PersonalDataIdentityRows card={identity} /> : <DetailEmpty />}
      </RecordInlineField>
    </RecordSection>
  )
}
