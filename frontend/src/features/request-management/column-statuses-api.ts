import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

/** The status-based statistics columns (spec 0391 D-2), in the backend enum order. */
export const STATUS_COLUMN_KEYS = [
  'unhandled_new_contacts',
  'current_potentials',
  'associati',
  'trattative_concluse',
  'invio_presa_in_carico',
] as const

export type StatusColumnKey = (typeof STATUS_COLUMN_KEYS)[number]

/** One configurable column: its "(nel periodo selezionato)" twin, if any, and the chosen status ids. */
export interface StatusColumnConfig {
  key: StatusColumnKey
  twin_key: string | null
  status_ids: number[]
}

export interface ColumnStatusOption {
  id: number
  /** Domain value, rendered as-is. */
  name: string
  group: string
  color: string | null
}

/** A status set: a workflow, or the "Stati predefiniti" (`id` null) of requests without one. */
export interface ColumnStatusWorkflow {
  id: number | null
  name: string
  is_active: boolean
  /** Names of the categories in the workflow's criteria. */
  categories: string[]
  statuses: ColumnStatusOption[]
}

export interface ColumnStatusesConfig {
  columns: StatusColumnConfig[]
  workflows: ColumnStatusWorkflow[]
}

export type ColumnStatusesSelection = Record<StatusColumnKey, number[]>

/** `GET {basePath}/report/column-statuses`. */
export async function fetchColumnStatuses(basePath: string): Promise<ColumnStatusesConfig> {
  const { data } = await apiClient.get<ApiResponse<ColumnStatusesConfig>>(`${basePath}/report/column-statuses`)
  return data.data
}

/** `PUT {basePath}/report/column-statuses`: replaces the choice of every column. */
export async function saveColumnStatuses(
  basePath: string,
  columns: ColumnStatusesSelection,
): Promise<ColumnStatusesConfig> {
  const { data } = await apiClient.put<ApiResponse<ColumnStatusesConfig>>(`${basePath}/report/column-statuses`, {
    columns,
  })
  return data.data
}
