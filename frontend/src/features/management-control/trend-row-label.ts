import type { TFunction } from 'i18next'
import { rowLabel } from '@/features/management-control/row-label'
import { STRUCTURE_KEY } from '@/features/management-control/trend-types'

/** Name of a function row of the trend: the server sends `label: null` for "structure" and "unattributed", the UI translates them. */
export function trendRowLabel(t: TFunction, row: { key: string; label: string | null }): string {
  return row.key === STRUCTURE_KEY ? t('managementControl.trend.structure') : rowLabel(t, row)
}
