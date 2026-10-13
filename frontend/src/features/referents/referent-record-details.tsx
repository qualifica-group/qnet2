import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { enumLabelOf } from '@/features/config/enum-label'
import {
  ReferentContactScopeField,
  ReferentNotesField,
  ReferentTypeField,
  ReferentUserField,
} from '@/features/referents/referent-fields'
import type { ReferentRecordSectionProps } from '@/features/referents/referent-record'

/**
 * "Dettagli referente" of the referente record (spec 0216 D-2), the same on
 * the detail and on create, every row editing in place on its own control.
 * Type and ambit are also the header's subtitle and pill: the rows are where
 * they change. The linked user's row is absent when its field permission hides
 * it (`RecordInlineField` gates on `user_id`).
 */
export function ReferentDetailsRecordSection({ values, form, inline }: ReferentRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('referents.form.sections.details.title')} icon={<Info />}>
      <RecordFieldList>
        <RecordInlineField
          field="referent_type_id"
          label={t('referents.form.referentType')}
          inline={inline}
          editor={<ReferentTypeField control={control} selected={values.referent_type} />}
        >
          {values.referent_type?.name ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="contact_scope"
          label={t('referents.form.contactScope')}
          inline={inline}
          editor={<ReferentContactScopeField control={control} />}
        >
          {enumLabelOf('referent_contact_scope', values.contact_scope)}
        </RecordInlineField>
        <RecordInlineField
          field="user_id"
          label={t('referents.form.linkedUser')}
          inline={inline}
          editor={<ReferentUserField control={control} selected={values.user} />}
        >
          {values.user?.name ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="notes"
          label={t('referents.form.notes')}
          inline={inline}
          editor={<ReferentNotesField control={control} />}
        >
          {values.notes && values.notes.trim() !== '' ? (
            <span className="whitespace-pre-line">{values.notes}</span>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
