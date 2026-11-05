import type { ChartAccountFormValues, RevenueTypeFormValues } from '@/features/chart-of-accounts/chart-account-schema'
import type {
  ChartAccountLevel,
  CreateChartAccountPayload,
  RevenueTypePayload,
  UpdateChartAccountPayload,
  UpdateRevenueTypePayload,
} from '@/features/chart-of-accounts/types'

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

interface SavedChartAccount {
  code: string
  name: string
  description: string | null
  is_active: boolean
  kind?: string
  parent_id?: number
}

export function buildCreateChartAccountPayload(
  level: ChartAccountLevel,
  values: ChartAccountFormValues,
): CreateChartAccountPayload {
  const common = {
    level,
    code: values.code,
    name: values.name,
    description: descriptionValue(values.description),
    is_active: values.is_active,
  }
  return level === 'group' ? { ...common, kind: values.kind } : { ...common, parent_id: values.parent_id }
}

/** Diff PATCH: only the changed fields; `level` is never sent. */
export function buildUpdateChartAccountPayload(
  level: ChartAccountLevel,
  values: ChartAccountFormValues,
  original: SavedChartAccount,
): UpdateChartAccountPayload {
  const payload: UpdateChartAccountPayload = {}
  if (values.code !== original.code) payload.code = values.code
  if (values.name !== original.name) payload.name = values.name
  if (descriptionValue(values.description) !== original.description) payload.description = descriptionValue(values.description)
  if (values.is_active !== original.is_active) payload.is_active = values.is_active
  if (level === 'group' && values.kind !== original.kind) payload.kind = values.kind
  if (level === 'account' && values.parent_id !== original.parent_id) payload.parent_id = values.parent_id
  return payload
}

export function buildRevenueTypePayload(values: RevenueTypeFormValues): RevenueTypePayload {
  return {
    chart_account_id: values.chart_account_id,
    code: values.code,
    name: values.name,
    description: descriptionValue(values.description),
    is_active: values.is_active,
    ...(values.is_institution_commission_default === true ? { is_institution_commission_default: true } : {}),
  }
}

export function buildUpdateRevenueTypePayload(
  values: RevenueTypeFormValues,
  original: SavedChartAccount & { chart_account_id?: number; is_institution_commission_default?: boolean },
): UpdateRevenueTypePayload {
  const payload: UpdateRevenueTypePayload = {}
  if (values.code !== original.code) payload.code = values.code
  if (values.name !== original.name) payload.name = values.name
  if (descriptionValue(values.description) !== original.description) payload.description = descriptionValue(values.description)
  if (values.is_active !== original.is_active) payload.is_active = values.is_active
  if (values.chart_account_id !== original.chart_account_id) payload.chart_account_id = values.chart_account_id
  if ((values.is_institution_commission_default ?? false) !== (original.is_institution_commission_default ?? false)) {
    payload.is_institution_commission_default = values.is_institution_commission_default ?? false
  }
  return payload
}
