import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ReferentTypeFormValues } from '@/features/referent-types/use-referent-type-form'

interface ReferentTypeSectionsProps {
  form: UseFormReturn<ReferentTypeFormValues>
  inline: InlineEdit
  canEdit: boolean
  /** The values the closed custom field rows show: persisted on the detail, the draft's on a create. */
  customFieldValues: Record<string, CustomFieldValue>
}

/** The referent type's sections, shared by the detail (saved one row at a time) and the create draft (kept until Salva). */
export function ReferentTypeSections({ form, inline, canEdit, customFieldValues }: ReferentTypeSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('referentTypes.form.sections.identity.title')} full>
        <RecordFieldList>
          <TextInlineRow<ReferentTypeFormValues> name="name" label={t('referentTypes.form.name')} inline={inline} canEdit={canEdit} />
        </RecordFieldList>
      </RecordSection>
      <CustomFieldRecordSections resource="referent-types" values={customFieldValues} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
