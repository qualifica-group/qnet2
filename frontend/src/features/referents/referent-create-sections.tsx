import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import {
  PersonalDataIdentityRecordSection,
  type PersonalDataCardBuffer,
} from '@/features/personal-data/personal-data-identity-record-section'
import { anagraphicSectionProps } from '@/features/personal-data/use-reveal-blocked-section'
import { ReferentDetailsRecordSection } from '@/features/referents/referent-record-details'
import { useReferentDraftValues } from '@/features/referents/use-referent-draft-values'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

interface ReferentCreateSectionsProps {
  form: UseFormReturn<ReferentFormValues>
  draft: InlineEdit
  card: PersonalDataCardBuffer
}

/**
 * The create form as a replica of the referente detail (spec 0216 D-3): the
 * detail's sections, rows, labels and order — the anagraphic card included —
 * every row CLOSED until clicked and opening on the same component the detail
 * edits in place. Nothing is saved per row: the header's Salva validates and
 * creates the whole draft.
 */
export function ReferentCreateSections({ form, draft, card }: ReferentCreateSectionsProps) {
  const { t } = useTranslation()
  const values = useReferentDraftValues(form.control)

  return (
    <RecordSectionsGrid>
      {/* The grid item itself, so a refused save can scroll it into view. */}
      <div {...anagraphicSectionProps('card')} className="flex min-w-0 flex-col">
        <PersonalDataIdentityRecordSection
          title={t('referents.form.sections.identity.title')}
          identity={card.draft}
          buffer={card}
          inline={draft}
        />
      </div>
      <ReferentDetailsRecordSection values={values} form={form} inline={draft} />
      <CustomFieldRecordSections
        resource="referents"
        values={values.custom_fields}
        inline={draft}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
