import type { TFunction } from 'i18next'
import { UNATTRIBUTED_KEY } from '@/features/management-control/types'

/**
 * Display name of a function or segment row. The server sends `label: null` for
 * "unattributed" and for work order types, so the UI translates them (spec 0268 D-19).
 */
export function rowLabel(t: TFunction, row: { key: string; label: string | null }, isWorkOrderType = false): string {
  if (row.key === UNATTRIBUTED_KEY) {
    return t('managementControl.unattributed')
  }
  if (isWorkOrderType) {
    return t(`enums.work_order_type.${row.key}`)
  }
  return row.label ?? row.key
}
