import type { UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ProductLine } from '@/features/product-lines/types'
import type { PipelineStatusRef } from '@/features/projects/types'
import type { CampaignGeoLevel } from '@/features/campaigns/campaign-geo'
import type {
  CampaignDetail,
  CampaignOperationalSiteRef,
  CampaignRelationRef,
} from '@/features/campaigns/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

/** Field-permission keys of the geo cascade, edited as one unit (spec 0027 BR-4/BR-5). */
export const CAMPAIGN_GEO_FIELDS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/**
 * Every field a Project pick writes (spec 0221 D-3, AC-042/BR-5): the row opens
 * only when all of them are editable.
 */
export const PROJECT_CASCADE_FIELDS = [
  'project_id',
  'partner_id',
  'operational_site_id',
  'start_date',
  'pipeline_status_id',
  'product_lines',
  ...CAMPAIGN_GEO_FIELDS,
] as const

/**
 * The fields each editor shows besides its own (spec 0221 D-4): a rule
 * failing on one of them already has a message on screen.
 */
export const EDITOR_EXTRA_FIELDS: Readonly<Record<string, readonly string[]>> = {
  project_id: ['pipeline_status_id', 'product_lines', ...CAMPAIGN_GEO_FIELDS],
  country_id: CAMPAIGN_GEO_FIELDS,
}

/** What the record's rows show: the persisted campaign on the detail, the draft (with resolved labels) on create. */
export interface CampaignRecordValues {
  code: string
  name: string
  description: string | null
  /** The linked project as the row names it (`"{code} — {name}"`). */
  project: { id: number; label: string } | null
  partner: CampaignRelationRef | null
  operational_site: CampaignOperationalSiteRef | null
  source: CampaignRelationRef | null
  pipeline_status: PipelineStatusRef | RelationFieldRef | null
  /** The persisted rows (detail); `null` on a create draft, named by `product_line_labels` instead. */
  product_lines: ProductLine[] | null
  /** A create draft's rows as "Root > Category" (empty on the detail). */
  product_line_labels: string[]
  country: CampaignRelationRef | null
  state: CampaignRelationRef | null
  province: CampaignRelationRef | null
  city: CampaignRelationRef | null
  /** The levels the linked project fills (BR-5): read-only on this campaign. */
  geo_locked_levels: CampaignGeoLevel[]
  start_date: string | null
  end_date: string | null
  total_budget: string | number | null
  target_lead: number | null
  custom_fields: Record<string, CustomFieldValue>
}

/** Everything a record section edits with: the shown values, the form and the single open row. */
export interface CampaignRecordSectionProps {
  values: CampaignRecordValues
  form: UseFormReturn<CampaignFormValues>
  inline: InlineEdit
}

/** Matches `ProjectForSelectResource`'s label, the one the Project picker shows. */
export function projectLabel(project: { code: string; name: string }): string {
  return `${project.code} — ${project.name}`
}

/** The persisted campaign as the record's rows read it. */
export function persistedCampaignValues(campaign: CampaignDetail): CampaignRecordValues {
  return {
    code: campaign.code,
    name: campaign.name,
    description: campaign.description,
    project: campaign.project ? { id: campaign.project.id, label: projectLabel(campaign.project) } : null,
    partner: campaign.partner,
    operational_site: campaign.operational_site,
    source: campaign.source,
    pipeline_status: campaign.pipeline_status,
    product_lines: campaign.product_lines,
    product_line_labels: [],
    country: campaign.country,
    state: campaign.state,
    province: campaign.province,
    city: campaign.city,
    geo_locked_levels: campaign.geo_locked_levels,
    start_date: campaign.start_date,
    end_date: campaign.end_date,
    total_budget: campaign.total_budget,
    target_lead: campaign.target_lead,
    custom_fields: campaign.custom_fields ?? {},
  }
}
