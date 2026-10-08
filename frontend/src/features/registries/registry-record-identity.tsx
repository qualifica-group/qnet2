import { useTranslation } from 'react-i18next'
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
import { REGISTRY_CARD_FIELD } from '@/features/registries/registry-record'

/** The buffered anagraphic card a record section edits: its draft, its gating and the refused-save signal. */
export interface RegistryCardBuffer {
  draft: PersonalDataDraft
  setDraft: (next: PersonalDataDraft) => void
  revalidateSignal: number
  fieldPermission: PersonalDataFieldPermissionResolver
}

interface RegistryIdentityRecordSectionProps {
  /** What the closed row shows: the persisted card on the detail (`null` only for one with none), the draft on create. */
  identity: PersonalDataIdentity | null
  buffer: RegistryCardBuffer
  inline: InlineEdit
}

/**
 * "Dati anagrafici" of the anagrafica record (spec 0200 D-4), the same on the
 * detail and on create: the fiscal identity rows as ONE in-place row opening
 * on the card form (`PersonalDataCardForm`) — the card is one buffered object,
 * saved whole, and it names the anagrafica (the header follows it). Its pencil
 * follows `personal_data.type`, the card's mandatory field.
 */
export function RegistryIdentityRecordSection({ identity, buffer, inline }: RegistryIdentityRecordSectionProps) {
  const { t } = useTranslation()
  const title = t('registries.form.sections.identity.title')

  return (
    <RecordSection title={title} icon={<IdCard />}>
      <RecordInlineField
        field={REGISTRY_CARD_FIELD}
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
