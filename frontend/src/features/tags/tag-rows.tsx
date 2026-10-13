import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { TagFormValues } from '@/features/tags/use-tag-form'

interface TagSectionsProps {
  form: UseFormReturn<TagFormValues>
  inline: InlineEdit
  canEdit: boolean
  /** The values the closed custom field rows show: persisted on the detail, the draft's on a create. */
  customFieldValues: Record<string, CustomFieldValue>
}

/** The tag's sections, shared by the detail (saved one row at a time) and the create draft (kept until Salva). */
export function TagSections({ form, inline, canEdit, customFieldValues }: TagSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('tags.form.sections.identity.title')} full>
        <RecordFieldList>
          <TextInlineRow<TagFormValues> name="name" label={t('tags.form.name')} inline={inline} canEdit={canEdit} />
        </RecordFieldList>
      </RecordSection>
      <CustomFieldRecordSections resource="tags" values={customFieldValues} inline={inline} control={form.control} />
    </RecordSectionsGrid>
  )
}
