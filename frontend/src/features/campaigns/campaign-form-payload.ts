import type {
  CampaignDetail,
  CampaignProductLineInput,
  CreateCampaignPayload,
  UpdateCampaignPayload,
} from '@/features/campaigns/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import { GEO_LEVEL_FIELDS, GEO_LEVELS, type GeoFieldName } from '@/features/campaigns/campaign-geo'

/** The scalar BR-2 classification field, never sent when the campaign is linked to a project. `product_lines` (spec 0094) follows its own collection-shaped gating below. */
const DERIVED_FIELDS = ['pipeline_status_id'] as const

/**
 * BR-5 (spec 0027): only the geo levels NOT owned by the linked project are
 * sent — the backend rejects (`prohibited`, 422) any locked level, and does
 * not store it on this row. A standalone campaign never locks anything.
 */
function geoCreateFields(values: CampaignFormValues): Partial<Record<GeoFieldName, number | null>> {
  const locked = new Set(values.geo_locked_levels)
  const fields: Partial<Record<GeoFieldName, number | null>> = {}
  for (const level of GEO_LEVELS) {
    if (locked.has(level)) {
      continue
    }
    const fieldName = GEO_LEVEL_FIELDS[level]
    fields[fieldName] = values[fieldName]
  }
  return fields
}

/**
 * Filters out any row still missing an id and casts the rest to the wire
 * shape. Defensive only: the schema's `superRefine` (spec 0094) already
 * blocks a standalone submit on an incomplete row — this exists because
 * `CampaignFormValues.product_lines` stays nullable-per-id at the type level
 * (each row is inline-editable).
 */
function completeProductLines(rows: CampaignFormValues['product_lines']): CampaignProductLineInput[] {
  return rows.filter(
    (row): row is CampaignProductLineInput =>
      row.business_function_id !== null && row.product_category_id !== null,
  )
}

/** Order-independent key of a product-line pair, for set comparison. */
function productLineKey(line: CampaignProductLineInput): string {
  return `${line.business_function_id}:${line.product_category_id}`
}

function sameProductLines(a: CampaignProductLineInput[], b: CampaignProductLineInput[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const keysA = new Set(a.map(productLineKey))
  const keysB = new Set(b.map(productLineKey))
  if (keysA.size !== keysB.size) {
    return false
  }
  for (const key of keysA) {
    if (!keysB.has(key)) {
      return false
    }
  }
  return true
}

/**
 * Builds the create payload: generic fields + valued custom fields. `code` is
 * included only when set (trimmed, non-empty) — an empty/absent value falls
 * back to server-side sequential generation (spec 0025 AC-010). BR-2: when
 * `project_id` is set, `pipeline_status_id` and `product_lines` are omitted
 * entirely — the backend derives/forces them from the project and rejects an
 * explicit value (AC-022, `prohibited`) — regardless of what the (disabled,
 * read-only) form controls currently hold. BR-5 (spec 0027): the geo fields
 * follow their own, per-level lock instead (`geoCreateFields`).
 */
export function buildCreatePayload(values: CampaignFormValues): CreateCampaignPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const linked = values.project_id !== null
  const code = values.code.trim()

  return {
    ...(code ? { code } : {}),
    name: values.name,
    project_id: values.project_id,
    description: values.description,
    partner_id: values.partner_id,
    operational_site_id: values.operational_site_id,
    ...(linked
      ? {}
      : {
          // Nullable/optional (spec 0039 D-3): the server falls back to the system "Nuovo" status when omitted.
          pipeline_status_id: values.pipeline_status_id,
          // Validated non-empty by the schema's required-when-standalone superRefine before submit.
          product_lines: completeProductLines(values.product_lines),
        }),
    ...geoCreateFields(values),
    start_date: values.start_date || null,
    end_date: values.end_date || null,
    total_budget: values.total_budget,
    target_lead: values.target_lead,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * BR-5 per-level diff (spec 0027): a level currently locked is always
 * omitted (still owned by the project, unchanged on this row). A level that
 * WAS locked before this edit (`original.geo_locked_levels`) but is not
 * locked anymore is sent as-is regardless of value equality — `original`
 * exposed the project's EFFECTIVE value for it, a naive diff would wrongly
 * omit it (mirrors the BR-2 linked→standalone transition, generalized
 * per-level instead of all-or-nothing).
 */
function geoUpdateFields(
  values: CampaignFormValues,
  original: CampaignDetail,
): Partial<Record<GeoFieldName, number | null>> {
  const lockedNow = new Set(values.geo_locked_levels)
  const lockedBefore = new Set(original.geo_locked_levels)
  const fields: Partial<Record<GeoFieldName, number | null>> = {}
  for (const level of GEO_LEVELS) {
    if (lockedNow.has(level)) {
      continue
    }
    const fieldName = GEO_LEVEL_FIELDS[level]
    if (lockedBefore.has(level) || values[fieldName] !== original[fieldName]) {
      fields[fieldName] = values[fieldName]
    }
  }
  return fields
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original campaign (spec 0023). `code` is never sent: it is immutable after
 * create (spec 0025 AC-011). BR-2: `pipeline_status_id` and `product_lines`
 * are only ever included in the diff when the campaign is (or remains)
 * standalone — a transition to linked is carried entirely by the changed
 * `project_id`, and the backend zeroes/derives them server-side. BR-5: the
 * geo fields follow their own per-level diff (`geoUpdateFields`) instead.
 */
export function buildUpdatePayload(
  values: CampaignFormValues,
  original: CampaignDetail,
): UpdateCampaignPayload {
  const payload: UpdateCampaignPayload = {}
  const linked = values.project_id !== null

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.project_id !== original.project_id) {
    payload.project_id = values.project_id
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.partner_id !== original.partner_id) {
    payload.partner_id = values.partner_id
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }
  if (!linked) {
    // A linked→standalone transition (BR-2): the campaign's own derived
    // columns were actually NULL in DB while `original` exposed the
    // project's EFFECTIVE values (read-through) — a diff against them would
    // be misleading, so `pipeline_status_id`/`product_lines` are sent as-is,
    // never diffed against the (misleading) effective original.
    const wasLinked = original.project_id !== null
    for (const field of DERIVED_FIELDS) {
      if (wasLinked || values[field] !== original[field]) {
        payload[field] = values[field]
      }
    }
    const originalProductLines = original.product_lines.map((line) => ({
      business_function_id: line.business_function.id,
      product_category_id: line.product_category.id,
    }))
    const currentProductLines = completeProductLines(values.product_lines)
    if (wasLinked || !sameProductLines(currentProductLines, originalProductLines)) {
      payload.product_lines = currentProductLines
    }
  }
  Object.assign(payload, geoUpdateFields(values, original))
  const startDate = values.start_date || null
  if (startDate !== original.start_date) {
    payload.start_date = startDate
  }
  const endDate = values.end_date || null
  if (endDate !== original.end_date) {
    payload.end_date = endDate
  }
  const originalTotalBudget = original.total_budget === null ? null : Number(original.total_budget)
  if (values.total_budget !== originalTotalBudget) {
    payload.total_budget = values.total_budget
  }
  if (values.target_lead !== original.target_lead) {
    payload.target_lead = values.target_lead
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
