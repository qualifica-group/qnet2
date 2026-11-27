import { isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import { blankToNull, managerSlotsFromRefs, sameIdSet, sameManagerSlots } from '@/lib/utils'
import type {
  CreateStandaloneProjectWorkOrderPayload,
  CreateWorkOrderPayload,
  StandaloneWorkOrderReferencesPayload,
  UpdateWorkOrderPayload,
  WorkOrderDetail,
} from '@/features/work-orders/types'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import { submittedCode } from '@/lib/sequential-code'

/**
 * Builds the create payload. `code` is included only when typed by the user
 * (`submittedCode`): blank or still the `next-code` suggestion, it is omitted
 * and the server assigns the next free sequential code at save time (D-1). The forced
 * closure is never part of the form: it is the detail's action
 * (`useWorkOrderClosure`, user directive 2026-10-06).
 */
export function buildCreatePayload(values: WorkOrderFormValues, suggestedCode?: string): CreateWorkOrderPayload {
  return {
    ...submittedCode(values.code, suggestedCode),
    quote_id: values.quote_id as number,
    // Spec 0215 D-2: a blank title travels as `null` (the server derives it).
    title: blankToNull(values.title),
    type: values.type,
    start_date: values.start_date,
    supervisor_ids: values.supervisor_ids,
    participant_slots: values.participant_slots,
    callback_date: values.callback_date,
    description: values.description,
    quote_line_ids: values.quote_line_ids,
    // Spec 0098: la mappa viaggia sempre alla create — non c'e' nulla di
    // persistito da conservare, quindi il merge sparso lato server non serve
    // (mirrors `quotes`' `buildCreatePayload`).
    attribute_values: values.attribute_values,
    // Spec 0124 D-9: opzionale, sempre inviato (anche `null`) — mirrors
    // `callback_date`'s own "optional in the type, always present in the
    // payload" pattern.
    task_template_id: values.task_template_id,
  }
}

/**
 * Builds the POST /project-work-orders payload (spec 0431): the create
 * payload without offer, lines and type, plus the own references. The schema
 * has already required client, company, title and complete rows.
 */
export function buildStandaloneCreatePayload(
  values: WorkOrderFormValues,
  suggestedCode?: string,
): CreateStandaloneProjectWorkOrderPayload {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars -- dropped on purpose: the endpoint takes neither
  const { quote_id, quote_line_ids, type, ...base } = buildCreatePayload(values, suggestedCode)

  return {
    ...base,
    title: values.title.trim(),
    ...references(values),
  }
}

/** The own references as the server takes them; incomplete rows never travel. */
function references(values: WorkOrderFormValues): StandaloneWorkOrderReferencesPayload {
  return {
    registry_id: values.registry_id as number,
    company_id: values.company_id as number,
    company_site_id: values.company_site_id,
    operational_site_id: values.operational_site_id,
    product_lines: values.product_lines
      .filter((row) => row.product_category_id !== null)
      .map((row) => ({ product_category_id: row.product_category_id as number })),
    product_ids: values.product_ids,
  }
}

/**
 * Spec 0431: the changed own references of a work order born without an
 * offer; never any on another kind (the server refuses them there).
 */
function changedReferences(
  values: WorkOrderFormValues,
  original: WorkOrderDetail,
): Partial<StandaloneWorkOrderReferencesPayload> {
  if (original.is_standalone !== true) {
    return {}
  }

  const next = references(values)
  const changed: Partial<StandaloneWorkOrderReferencesPayload> = {}
  if (next.registry_id !== (original.registry?.id ?? null)) {
    changed.registry_id = next.registry_id
  }
  if (next.company_id !== (original.company?.id ?? null)) {
    changed.company_id = next.company_id
  }
  if (next.company_site_id !== (original.company_site?.id ?? null)) {
    changed.company_site_id = next.company_site_id
  }
  if (next.operational_site_id !== (original.operational_site?.id ?? null)) {
    changed.operational_site_id = next.operational_site_id
  }
  const originalCategoryIds = (original.product_lines ?? []).map((line) => line.product_category.id)
  if (!sameIdSet(next.product_lines.map((line) => line.product_category_id), originalCategoryIds)) {
    changed.product_lines = next.product_lines
  }
  if (!sameIdSet(next.product_ids, (original.products ?? []).map((product) => product.id))) {
    changed.product_ids = next.product_ids
  }

  return changed
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original work order (AC-077). `code`/`quote_id` are NEVER
 * included (D-1/D-5): both are immutable after create for every role, and
 * the backend 422s on their mere presence, even when the value is unchanged.
 * `quote_line_ids` is sent only when the selected set differs from the
 * persisted one (AC-026: an untouched selection must not resend a no-op sync);
 * `supervisor_ids` and `participant_slots` follow the same rule (spec 0096,
 * AC-024/AC-073) — the latter compared POSITIONALLY, since a reorder is a
 * real change.
 */
export function buildUpdatePayload(
  values: WorkOrderFormValues,
  original: WorkOrderDetail,
): UpdateWorkOrderPayload {
  const payload: UpdateWorkOrderPayload = {}

  // Spec 0215 D-2: a cleared title goes back to the automatic one (`null`).
  const title = blankToNull(values.title)
  if (title !== original.title) {
    payload.title = title
  }
  if (values.type !== original.type) {
    payload.type = values.type
  }
  if (values.start_date !== original.start_date) {
    payload.start_date = values.start_date
  }
  if (values.callback_date !== original.callback_date) {
    payload.callback_date = values.callback_date
  }
  if (!sameIdSet(values.supervisor_ids, original.supervisors.map((supervisor) => supervisor.id))) {
    payload.supervisor_ids = values.supervisor_ids
  }
  // Positional comparison, not a set one: moving a partecipante between slots
  // IS a change, and `sameIdSet` would call it a no-op.
  if (!sameManagerSlots(values.participant_slots, managerSlotsFromRefs(original.participants))) {
    payload.participant_slots = values.participant_slots
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  const originalLineIds = original.quote_lines.map((line) => line.id)
  if (!sameIdSet(values.quote_line_ids, originalLineIds)) {
    payload.quote_line_ids = values.quote_line_ids
  }

  // Spec 0098: la mappa viaggia solo se un valore e' cambiato — il server fa
  // un merge SPARSO, quindi mandarla identica sarebbe un no-op costoso; i
  // `code` sono quelli VIVI (le righe possono aver cambiato categoria in
  // questo stesso form), non quelli persistiti (mirrors `quotes`' `buildUpdatePayload`).
  const originalAttributeValues = seedAttributeValues(
    original.applicable_attributes,
    original.attribute_values,
  )
  const attributeValuesChanged = Object.keys(values.attribute_values).some(
    (code) =>
      !isEqualCustomFieldValue(
        values.attribute_values[code] ?? null,
        originalAttributeValues[code] ?? null,
      ),
  )
  if (attributeValuesChanged) {
    payload.attribute_values = values.attribute_values
  }

  return { ...payload, ...changedReferences(values, original) }
}
