import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { SourceFormValues } from '@/features/sources/use-source-form'

interface SourceSectionsProps {
  form: UseFormReturn<SourceFormValues>
  inline: InlineEdit
  canEdit: boolean
  /** The values the closed custom field rows show: persisted on the detail, the draft's on a create. */
  customFieldValues: Record<string, CustomFieldValue>
}

/** The source's sections, shared by the detail (saved one row at a time) and the create draft (kept until Salva). */
export function SourceSections({ form, inline, canEdit, customFieldValues }: SourceSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('sources.form.sections.identity.title')} full>
        <RecordFieldList>
          <TextInlineRow<SourceFormValues> name="name" label={t('sources.form.name')} inline={inline} canEdit={canEdit} />
        </RecordFieldList>
      </RecordSection>
      <CustomFieldRecordSections resource="sources" values={customFieldValues} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
