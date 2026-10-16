import type { ScopeFilters } from '@/features/management-control/types'

/* Spec 0271: GET /api/management-control/billing. */

export const BILLING_GROUP_BYS = ['business_function', 'company', 'commercial'] as const
export type BillingGroupBy = (typeof BILLING_GROUP_BYS)[number]

/** Age of a work order since its start, in the order of the contract. */
export const BACKLOG_AGE_BUCKETS = ['0_3', '3_6', '6_12', 'over_12'] as const
export type BacklogAgeBucket = (typeof BACKLOG_AGE_BUCKETS)[number]

/** Age of an open proforma in days, in the order of the contract. */
export const PROFORMA_AGE_BUCKETS = ['0_30', '31_60', '61_90', 'over_90'] as const
export type ProformaAgeBucket = (typeof PROFORMA_AGE_BUCKETS)[number]

export type ProformaKind = 'consultancy' | 'institution'

export interface BacklogRow {
  key: string
  label: string | null
  work_orders_count: number
  contract: number
  invoiced: number
  to_invoice: number
}

export interface BacklogAgeRow {
  bucket: BacklogAgeBucket
  work_orders_count: number
  to_invoice: number
}

export interface ProformaAgeRow {
  bucket: ProformaAgeBucket
  documents: number
  amount: number
}

export interface ProformaKindRow {
  kind: ProformaKind | null
  issued: number
  converted: number
  open: number
  average_days_to_invoice: number | null
}

export interface DocumentsAmount {
  documents: number
  amount: number
}

export interface Billing {
  applied: {
    date_from: string | null
    date_to: string | null
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
    group_by: BillingGroupBy
  }
  /** Date of the snapshot of the backlog, `YYYY-MM-DD`. */
  today: string
  backlog: {
    work_orders_count: number
    contract: number
    invoiced: number
    /** Negative for an over-invoiced work order. */
    to_invoice: number
    by_dimension: BacklogRow[]
    by_age: BacklogAgeRow[]
  }
  proforma: {
    issued: DocumentsAmount
    converted: DocumentsAmount & { average_days_to_invoice: number | null }
    open: DocumentsAmount
    open_by_age: ProformaAgeRow[]
    average_days_invoice_to_collection: number | null
    by_kind: ProformaKindRow[]
  }
}

/** Filter state of the billing page: period (proforma cycle only), scope and grouping of the backlog. */
export interface BillingFilters extends ScopeFilters {
  groupBy: BillingGroupBy
}

/** Request query parameters of the billing endpoint, named exactly as the contract. */
export interface BillingParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
  group_by: BillingGroupBy
}
