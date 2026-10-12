import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { persistedCampaignValues } from '@/features/campaigns/campaign-record'
import {
  CampaignClassificationRecordSection,
  CampaignGeographyRecordSection,
  CampaignIdentityRecordSection,
  CampaignPlanningRecordSection,
  CampaignProjectRecordSection,
} from '@/features/campaigns/campaign-record-sections'
import type { CampaignDetailEditor } from '@/features/campaigns/use-campaign-inline-edit'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

interface CampaignDetailSectionsProps {
  campaign: CampaignDetailData
  editor: CampaignDetailEditor
}

/**
 * The campaign record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0221): identity, the project link (Partner, Sede and
 * Fonte live there, rendered as links), classification, geography, planning
 * and the custom fields ("campi flessibili"). The 3 classification values and
 * the 4 geo levels are the EFFECTIVE ones (BR-2/BR-5, resolved server-side).
 * The create form renders the same sections in the same order.
 */
export function CampaignDetailSections({ campaign, editor }: CampaignDetailSectionsProps) {
  const { form, inline } = editor
  const sectionProps = { values: persistedCampaignValues(campaign), form, inline }

  return (
    <RecordSectionsGrid>
      <CampaignIdentityRecordSection {...sectionProps} />
      <CampaignProjectRecordSection {...sectionProps} />
      <CampaignClassificationRecordSection {...sectionProps} />
      <CampaignGeographyRecordSection {...sectionProps} />
      <CampaignPlanningRecordSection {...sectionProps} />
      <CustomFieldRecordSections
        resource="campaigns"
        values={sectionProps.values.custom_fields}
        inline={inline}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
