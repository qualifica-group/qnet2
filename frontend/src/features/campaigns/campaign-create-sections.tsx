import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CampaignRecordValues } from '@/features/campaigns/campaign-record'
import {
  CampaignClassificationRecordSection,
  CampaignGeographyRecordSection,
  CampaignIdentityRecordSection,
  CampaignPlanningRecordSection,
  CampaignProjectRecordSection,
} from '@/features/campaigns/campaign-record-sections'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

interface CampaignCreateSectionsProps {
  /** The draft as the closed rows read it (`useCampaignDraftValues`, shared with the header). */
  values: CampaignRecordValues
  form: UseFormReturn<CampaignFormValues>
  draft: InlineEdit
}

/**
 * The create form as a replica of the campaign detail (spec 0221 D-2): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked
 * and opening on the same component the detail edits in place. Nothing is
 * saved per row: the header's Salva validates and creates the whole draft.
 */
export function CampaignCreateSections({ values, form, draft }: CampaignCreateSectionsProps) {
  const sectionProps = { values, form, inline: draft }

  return (
    <RecordSectionsGrid>
      <CampaignIdentityRecordSection {...sectionProps} />
      <CampaignProjectRecordSection {...sectionProps} />
      <CampaignClassificationRecordSection {...sectionProps} />
      <CampaignGeographyRecordSection {...sectionProps} />
      <CampaignPlanningRecordSection {...sectionProps} />
      <CustomFieldRecordSections resource="campaigns" values={values.custom_fields} inline={draft} control={form.control} />
    </RecordSectionsGrid>
  )
}
