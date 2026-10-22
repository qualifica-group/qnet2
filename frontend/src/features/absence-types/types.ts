import type { ResourcePermissions } from '@/features/authorization/types'
import type { AbsenceType, AbsenceTypeColor } from '@/features/absences/types'

export type { AbsenceType, AbsenceTypeOption } from '@/features/absences/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const ABSENCE_TYPES_DOMAIN = 'absence-types'

/** An `AbsenceType` carrying the authorization metadata the detail's provider reads. */
export interface AbsenceTypeDetail extends AbsenceType {
  permissions: ResourcePermissions
}

/** POST/PUT body of the catalogue. */
export interface AbsenceTypePayload {
  name: string
  deducts_balance: boolean
  requires_approval: boolean
  is_active?: boolean
  sort_order?: number
  allows_full_day: boolean
  allows_half_day: boolean
  allows_hourly: boolean
  min_notice_days: number | null
  color: AbsenceTypeColor
  default_annual_days: number | null
}

export type UpdateAbsenceTypePayload = Partial<AbsenceTypePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type AbsenceTypeFormMode = { type: 'create' } | { type: 'edit'; absenceType: AbsenceTypeDetail }
