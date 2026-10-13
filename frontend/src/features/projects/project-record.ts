import type { UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ProductLine } from '@/features/product-lines/types'
import type {
  PipelineStatusRef,
  ProjectDetail,
  ProjectOperationalSiteRef,
  ProjectRelationRef,
} from '@/features/projects/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/** Field-permission keys of the geo cascade, edited as one unit (spec 0027 BR-4). */
export const PROJECT_GEO_FIELDS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/** What the record's rows show: the persisted project on the detail, the draft (with resolved labels) on create. */
export interface ProjectRecordValues {
  code: string
  name: string
  description: string | null
  pipeline_status: PipelineStatusRef | RelationFieldRef | null
  partner: ProjectRelationRef | null
  operational_site: ProjectOperationalSiteRef | null
  /** The persisted rows (detail); `null` on a create draft, named by `product_line_labels` instead. */
  product_lines: ProductLine[] | null
  /** A create draft's rows as "Root > Category" (empty on the detail). */
  product_line_labels: string[]
  country: ProjectRelationRef | null
  state: ProjectRelationRef | null
  province: ProjectRelationRef | null
  city: ProjectRelationRef | null
  start_date: string | null
  end_date: string | null
  total_budget: string | number | null
  target_lead: number | null
  custom_fields: Record<string, CustomFieldValue>
}

/** Everything a record section edits with: the shown values, the form and the single open row. */
export interface ProjectRecordSectionProps {
  values: ProjectRecordValues
  form: UseFormReturn<ProjectFormValues>
  inline: InlineEdit
}

/** The persisted project as the record's rows read it. */
export function persistedProjectValues(project: ProjectDetail): ProjectRecordValues {
  return {
    code: project.code,
    name: project.name,
    description: project.description,
    pipeline_status: project.pipeline_status,
    partner: project.partner,
    operational_site: project.operational_site,
    product_lines: project.product_lines,
    product_line_labels: [],
    country: project.country,
    state: project.state,
    province: project.province,
    city: project.city,
    start_date: project.start_date,
    end_date: project.end_date,
    total_budget: project.total_budget,
    target_lead: project.target_lead,
    custom_fields: project.custom_fields ?? {},
  }
}
