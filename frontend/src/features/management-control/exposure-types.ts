import type { CostNature, ScopeFilters } from '@/features/management-control/types'

/* Spec 0269: GET /api/management-control/exposure. */

export const EXPOSURE_DOCUMENT_TYPES = ['all', 'proforma', 'invoice'] as const
export type ExposureDocumentType = (typeof EXPOSURE_DOCUMENT_TYPES)[number]

export const EXPOSURE_GROUP_BYS = ['company', 'company_site', 'operational_site'] as const
export type ExposureGroupBy = (typeof EXPOSURE_GROUP_BYS)[number]

export const EXPOSURE_TOPS = [5, 10, 25] as const
export type ExposureTop = (typeof EXPOSURE_TOPS)[number]

/** Aging buckets in the order of the contract: overdue first (worst to mildest), then not yet due. */
export const AGING_BUCKETS = [
  'overdue_over_90',
  'overdue_61_90',
  'overdue_31_60',
  'overdue_1_30',
  'due_0_30',
  'due_31_60',
  'due_over_60',
] as const
export type AgingBucket = (typeof AGING_BUCKETS)[number]

export const PAYMENT_STATUSES = ['paid', 'not_due', 'overdue', 'seriously_overdue'] as const
export type PaymentStatusKey = (typeof PAYMENT_STATUSES)[number]

export interface RegistryRef {
  id: number
  name: string
}

export interface ExposureCostType {
  id: number
  name: string
  color: string | null
  nature: CostNature | null
}

export interface StatusCell {
  documents: number
  open: number
}

export interface ExposureTopRow {
  registry: RegistryRef
  open: number
  overdue: number
  worst_bucket: AgingBucket
}

export interface ExposureCostTypeRow {
  cost_type: ExposureCostType | null
  open: number
  overdue: number
}

export interface ExposureSide {
  open_total: number
  overdue_total: number
  not_due_total: number
  aging: Record<AgingBucket, number>
  invoiced_in_period: number
  settled_in_period: number
  average_days: number | null
  by_status: Record<PaymentStatusKey, StatusCell>
  top: ExposureTopRow[]
}

export interface ExposurePayableSide extends ExposureSide {
  by_cost_type: ExposureCostTypeRow[]
}

export interface CommittedTopRow {
  registry: RegistryRef
  committed: number
  orders: number
}

export interface ExposureCommitted {
  total: number
  with_work_order: number
  without_work_order: number
  by_supplier: CommittedTopRow[]
  by_cost_type: ExposureCostTypeRow[]
}

export interface ExposureDimensionRow {
  key: string
  label: string | null
  receivable_open: number
  payable_open: number
  committed: number
  net: number
}

export interface ExposureNet {
  receivable_open: number
  payable_open: number
  committed_total: number
  net: number
  by_dimension: ExposureDimensionRow[]
}

export interface ConcentrationRow {
  registry: RegistryRef
  invoiced: number
  invoiced_share: number
  overdue: number
  overdue_share: number
}

export interface ExposureConcentration {
  invoiced_total: number
  overdue_total: number
  top_invoiced_share: number
  top_overdue_share: number
  rows: ConcentrationRow[]
}

export interface Exposure {
  applied: {
    date_from: string | null
    date_to: string | null
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
    document_type: string
    group_by: string
    top: number
  }
  today: string
  receivable: ExposureSide
  payable: ExposurePayableSide
  committed: ExposureCommitted
  exposure: ExposureNet
  concentration: ExposureConcentration
}

/** Filter state of the exposure page (UI side; mapped to the request by `toExposureParams`). */
export interface ExposureFilters extends ScopeFilters {
  documentType: ExposureDocumentType
  groupBy: ExposureGroupBy
  top: ExposureTop
}

/** Request query parameters of the exposure, named exactly as the contract. */
export interface ExposureParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
  document_type: ExposureDocumentType
  group_by: ExposureGroupBy
  top: ExposureTop
}
