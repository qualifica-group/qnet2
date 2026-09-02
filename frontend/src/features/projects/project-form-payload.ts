import type {
  CreateProjectPayload,
  ProjectDetail,
  ProjectProductLineInput,
  UpdateProjectPayload,
} from '@/features/projects/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/**
 * Builds the create payload: generic fields + valued custom fields. `code` is
 * included only when set (trimmed, non-empty) — an empty/absent value falls
 * back to server-side sequential generation (spec 0025 AC-010).
 */
export function buildCreatePayload(values: ProjectFormValues): CreateProjectPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const code = values.code.trim()
  return {
    ...(code ? { code } : {}),
    name: values.name,
    // Nullable/optional (spec 0039 D-3): the server falls back to the system "Nuovo" status when omitted.
    pipeline_status_id: values.pipeline_status_id,
    description: values.description,
    // Spec 0094: the server REPLACES the entire row collection on every
    // write. Always sent in full on create (validated non-empty by the
    // schema's `superRefine` before submit).
    product_lines: completeProductLines(values.product_lines),
    country_id: values.country_id,
    state_id: values.state_id,
    province_id: values.province_id,
    city_id: values.city_id,
    partner_id: values.partner_id,
    operational_site_id: values.operational_site_id,
    start_date: values.start_date || null,
    end_date: values.end_date || null,
    total_budget: values.total_budget,
    target_lead: values.target_lead,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original project (spec 0023). `code` is never sent: it is immutable after
 * create (spec 0025 AC-011).
 */
export function buildUpdatePayload(
  values: ProjectFormValues,
  original: ProjectDetail,
): UpdateProjectPayload {
  const payload: UpdateProjectPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.pipeline_status_id !== original.pipeline_status_id) {
    payload.pipeline_status_id = values.pipeline_status_id
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  // Spec 0094: the server replaces the entire row SET on every write —
  // diff as an unordered collection of pairs, never positionally (row order
  // in the form carries no meaning).
  const originalProductLines = original.product_lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category.id,
  }))
  const currentProductLines = completeProductLines(values.product_lines)
  if (!sameProductLines(currentProductLines, originalProductLines)) {
    payload.product_lines = currentProductLines
  }
  if (values.country_id !== original.country_id) {
    payload.country_id = values.country_id
  }
  if (values.state_id !== original.state_id) {
    payload.state_id = values.state_id
  }
  if (values.province_id !== original.province_id) {
    payload.province_id = values.province_id
  }
  if (values.city_id !== original.city_id) {
    payload.city_id = values.city_id
  }
  if (values.partner_id !== original.partner_id) {
    payload.partner_id = values.partner_id
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }
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

/**
 * Filters out any row still missing an id and casts the rest to the wire
 * shape. Defensive only: the schema's `superRefine` (spec 0094) already
 * blocks submit on an incomplete row — this exists because
 * `ProjectFormValues.product_lines` stays nullable-per-id at the type level
 * (each row is inline-editable).
 */
function completeProductLines(rows: ProjectFormValues['product_lines']): ProjectProductLineInput[] {
  return rows.filter(
    (row): row is ProjectProductLineInput =>
      row.business_function_id !== null && row.product_category_id !== null,
  )
}

/** Order-independent key of a product-line pair, for set comparison. */
function productLineKey(line: ProjectProductLineInput): string {
  return `${line.business_function_id}:${line.product_category_id}`
}

function sameProductLines(a: ProjectProductLineInput[], b: ProjectProductLineInput[]): boolean {
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
