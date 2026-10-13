import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { LeadCascades } from '@/features/leads/use-lead-cascades'
import type {
  LeadCampaignRef,
  LeadDetail,
  LeadOperationalSiteRef,
  LeadProductOfInterest,
  LeadRegistryRef,
  LeadRelationRef,
} from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** What the record's rows show: the persisted lead on the detail, the draft (with resolved labels) on create. */
export interface LeadRecordValues {
  registry: LeadRegistryRef | null
  campaign: LeadCampaignRef | null
  operational_site: LeadOperationalSiteRef | null
  source: LeadRelationRef | null
  operator: LeadRelationRef | null
  notes: string | null
  extra_fields: Record<string, string> | null
  products_of_interest: LeadProductOfInterest[]
}

/** Everything a record section edits with: the shown values, the form, the single open row and the cascades. */
export interface LeadRecordSectionProps {
  values: LeadRecordValues
  form: UseFormReturn<LeadFormValues>
  inline: InlineEdit
  cascades: LeadCascades
}

/** Stable empty default: a missing key on an older payload reads the same as `[]`. */
const NO_PRODUCTS: LeadProductOfInterest[] = []

/** The persisted lead as the record's rows read it. */
export function persistedLeadValues(lead: LeadDetail): LeadRecordValues {
  return {
    registry: lead.registry,
    campaign: lead.campaign,
    operational_site: lead.operational_site,
    source: lead.source,
    operator: lead.operator,
    notes: lead.notes,
    extra_fields: lead.extra_fields,
    products_of_interest: lead.products_of_interest ?? NO_PRODUCTS,
  }
}

/** The campaign as the record names it: "CODE — Name" (the name alone while a draft's code resolves). */
export function campaignLabel(campaign: LeadCampaignRef): string {
  return campaign.code ? `${campaign.code} — ${campaign.name}` : campaign.name
}
