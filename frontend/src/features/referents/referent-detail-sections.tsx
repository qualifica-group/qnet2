import { useTranslation } from 'react-i18next'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PersonalDataIdentityRecordSection } from '@/features/personal-data/personal-data-identity-record-section'
import { persistedReferentValues } from '@/features/referents/referent-record'
import { ReferentDetailsRecordSection } from '@/features/referents/referent-record-details'
import type { ReferentDetailEditor } from '@/features/referents/use-referent-inline-edit'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

interface ReferentDetailSectionsProps {
  referent: ReferentDetailWithPermissions
  editor: ReferentDetailEditor
}

/**
 * The referente record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0216): the fiscal identity, the referente's own
 * details, then the custom fields ("campi flessibili") grouped as the form
 * groups them. The create form renders the same sections in the same order
 * (`ReferentCreateSections`).
 */
export function ReferentDetailSections({ referent, editor }: ReferentDetailSectionsProps) {
  const { t } = useTranslation()
  const { form, inline, card } = editor
  const values = persistedReferentValues(referent)

  return (
    <RecordSectionsGrid>
      <PersonalDataIdentityRecordSection
        title={t('referents.form.sections.identity.title')}
        identity={referent.personal_data}
        buffer={card}
        inline={inline}
      />
      <ReferentDetailsRecordSection values={values} form={form} inline={inline} />
      <CustomFieldRecordSections
        resource="referents"
        values={values.custom_fields}
        inline={inline}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
