import type { CostTypeFormValues, CostTypeLedgerFormValues } from '@/features/cost-types/cost-type-schema'
import type { CostTypeDetail, CostTypePayload, UpdateCostTypePayload } from '@/features/cost-types/types'

/** The fields a diff PATCH compares: any saved cost type, or a chart-of-accounts tree sub-account mapped onto them. */
export type CostTypeEditable = Pick<CostTypeDetail, 'name' | 'description' | 'nature' | 'color' | 'is_active'>
export type CostTypeLedgerEditable = CostTypeEditable & Pick<CostTypeDetail, 'code' | 'chart_account_id'>

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

/** Builds the create payload: every field the form owns (never `sort_order`). */
export function buildCreatePayload(values: CostTypeFormValues): CostTypePayload {
  return {
    name: values.name.trim(),
    description: descriptionValue(values.description),
    nature: values.nature,
    color: values.color,
    is_active: values.is_active,
  }
}

/** Builds a partial PATCH payload with only the fields that changed from the saved cost type. */
export function buildUpdatePayload(values: CostTypeFormValues, original: CostTypeEditable): UpdateCostTypePayload {
  const payload: UpdateCostTypePayload = {}

  if (values.name.trim() !== original.name) {
    payload.name = values.name.trim()
  }
  if (descriptionValue(values.description) !== original.description) {
    payload.description = descriptionValue(values.description)
  }
  if (values.nature !== original.nature) {
    payload.nature = values.nature
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}

/** Create payload of a cost type born as a ledger sub-account: the page's fields plus its account and code (spec 0348). */
export function buildLedgerCreatePayload(values: CostTypeLedgerFormValues): CostTypePayload {
  return {
    ...buildCreatePayload(values),
    chart_account_id: values.chart_account_id,
    code: values.code.trim(),
  }
}

/** Diff PATCH of a ledger sub-account: placing an unplaced cost type sends both `chart_account_id` and `code`. */
export function buildLedgerUpdatePayload(
  values: CostTypeLedgerFormValues,
  original: CostTypeLedgerEditable,
): UpdateCostTypePayload {
  const payload = buildUpdatePayload(values, original)
  const code = values.code.trim()

  if (values.chart_account_id !== original.chart_account_id) {
    payload.chart_account_id = values.chart_account_id
    payload.code = code
  } else if (code !== original.code) {
    payload.code = code
  }

  return payload
}
