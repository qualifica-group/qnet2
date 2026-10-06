import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type {
  OpportunityLeadRef,
  OpportunityManagerRef,
  OpportunityProductOfInterest,
} from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** What the record's rows show: the persisted opportunity on the detail, the draft (with resolved labels) on create. */
export interface OpportunityRecordValues {
  name: string
  start_date: string | null
  expected_close_date: string | null
  estimated_value: string | number | null
  success_probability: number | null
  general_notes: string | null
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  /** Read-only origin; `null` on a create, whose lead has its own open picker. */
  lead: OpportunityLeadRef | null
  source: RelationFieldRef | null
  supervisor: RelationFieldRef | null
  managers: OpportunityManagerRef[]
  /** The G.A. denominations (spec 0080): the record's own resolution, live from the rows on a create. */
  manager_labels: Record<string, string> | undefined
  products_of_interest: OpportunityProductOfInterest[]
  rewards: RewardAssignmentRef[]
}

/** Everything a record section edits with: the form, the single open row and the BR-2 locks. */
export interface OpportunityRecordSectionProps {
  values: OpportunityRecordValues
  form: UseFormReturn<OpportunityFormValues>
  inline: InlineEdit
  /** BR-2: keys derived from a linked Lead, never editable (empty outside that flow). */
  lockedFields: ReadonlySet<string>
}

/**
 * Whether every field an editor's cascade writes is editable too (spec 0195
 * D-6c applied to Opportunita'): otherwise the editor must not open, or its
 * save would carry a write the server refuses.
 */
export function useCascadeEditable(fields: readonly string[]): boolean {
  const { field } = useResourcePermissions()
  return fields.every((name) => {
    const permission = field(name)
    return permission.editable && !permission.disabled
  })
}
