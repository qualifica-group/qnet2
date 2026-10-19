import type { ResourcePermissions } from '@/features/authorization/types'
import type { AbsenceType } from '@/features/absences/types'

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
}

export type UpdateAbsenceTypePayload = Partial<AbsenceTypePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type AbsenceTypeFormMode = { type: 'create' } | { type: 'edit'; absenceType: AbsenceTypeDetail }
