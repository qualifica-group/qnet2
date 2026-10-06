import { useTranslation } from 'react-i18next'
import { IdCard } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { PersonalDataIdentityRows } from '@/features/personal-data/personal-data-identity-rows'
import type {
  PersonalDataCard,
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'
import { REGISTRY_CARD_FIELD } from '@/features/registries/registry-record'

/** The buffered anagraphic card a record section edits: its draft, its gating and the refused-save signal. */
export interface RegistryCardBuffer {
  draft: PersonalDataDraft
  setDraft: (next: PersonalDataDraft) => void
  revalidateSignal: number
  fieldPermission: PersonalDataFieldPermissionResolver
}

interface RegistryIdentityRecordSectionProps {
  /** The persisted card, `null` only for the pathological anagrafica with none. */
  card: PersonalDataCard | null
  buffer: RegistryCardBuffer
  inline: InlineEdit
}

/**
 * "Dati anagrafici" of the anagrafica record (spec 0200 D-4): the fiscal
 * identity rows as ONE in-place row opening on the card form the create uses
 * (`PersonalDataCardForm`) — the card is one buffered object, saved whole, and
 * it names the anagrafica (the header follows the save). Its pencil follows
 * `personal_data.type`, the card's mandatory field.
 */
export function RegistryIdentityRecordSection({ card, buffer, inline }: RegistryIdentityRecordSectionProps) {
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
        {card ? <PersonalDataIdentityRows card={card} /> : <DetailEmpty />}
      </RecordInlineField>
    </RecordSection>
  )
}

/**
 * The create form's "Dati anagrafici": the card form always open (spec 0200
 * D-5) — it names the anagrafica being created, as the Lead of origin opens
 * the Opportunita' create.
 */
export function RegistryIdentityCreateSection({ buffer }: { buffer: RegistryCardBuffer }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('registries.form.sections.identity.title')} icon={<IdCard />}>
      <PersonalDataCardForm
        value={buffer.draft}
        onChange={buffer.setDraft}
        fieldPermission={buffer.fieldPermission}
        revalidateSignal={buffer.revalidateSignal}
      />
    </RecordSection>
  )
}
