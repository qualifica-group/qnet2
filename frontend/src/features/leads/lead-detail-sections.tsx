import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { persistedLeadValues } from '@/features/leads/lead-record'
import {
  LeadContactRecordSection,
  LeadDetailsRecordSection,
  LeadExtraFieldsRecordSection,
  LeadNotesRow,
  LeadProductsRecordSection,
} from '@/features/leads/lead-record-sections'
import type { LeadDetailEditor } from '@/features/leads/use-lead-inline-edit'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

interface LeadDetailSectionsProps {
  lead: LeadDetailWithPermissions
  editor: LeadDetailEditor
}

/**
 * The lead record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0217): the note callout in front, the anagrafica
 * and campaign, the details, then the products of interest and the imported
 * data across the full width. The create form renders the same sections in
 * the same order (`LeadCreateSections`).
 */
export function LeadDetailSections({ lead, editor }: LeadDetailSectionsProps) {
  const { form, inline, cascades } = editor
  const sectionProps = { values: persistedLeadValues(lead), form, inline, cascades }

  return (
    <RecordSectionsGrid>
      <LeadNotesRow {...sectionProps} className={FULL_WIDTH_SECTION_CLASS} />
      <LeadContactRecordSection {...sectionProps} />
      <LeadDetailsRecordSection {...sectionProps} />
      <LeadProductsRecordSection {...sectionProps} />
      <LeadExtraFieldsRecordSection {...sectionProps} />
    </RecordSectionsGrid>
  )
}
