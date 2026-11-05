import { dayOf } from '@/features/absences/calendar/calendar-month'
import {
  normalizeAmount,
  parseSubAccountValue,
  subAccountValue,
  type TreasuryMovementFormValues,
} from '@/features/treasury-movements/treasury-movement-schema'
import type { TreasuryMovement, TreasuryMovementPayload } from '@/features/treasury-movements/types'

/** Blank form: an income dated today. */
export function emptyTreasuryMovementValues(): TreasuryMovementFormValues {
  return {
    type: 'income',
    direction: '',
    movement_date: dayOf(new Date()),
    financial_account_id: null,
    counterpart_account_id: null,
    amount: '',
    description: '',
    sub_account: '',
    registry_id: null,
    work_order_id: null,
  }
}

export function treasuryMovementToValues(movement: TreasuryMovement): TreasuryMovementFormValues {
  return {
    type: movement.type,
    direction: movement.direction ?? '',
    movement_date: movement.movement_date,
    financial_account_id: movement.account.id,
    counterpart_account_id: movement.counterpart_account?.id ?? null,
    amount: movement.amount,
    description: movement.description,
    sub_account: subAccountValue(movement.sub_account.kind, movement.sub_account.id),
    registry_id: movement.registry?.id ?? null,
    work_order_id: movement.work_order?.id ?? null,
  }
}

const SUB_ACCOUNT_FIELD = {
  cost: 'cost_type_id',
  revenue: 'revenue_type_id',
  financial: 'treasury_type_id',
} as const

/**
 * Validated form values -> request body. Exactly one sub-account id is set and the other two are sent as null:
 * on update the backend merges the body over the saved row, so a kind change must clear the old id.
 */
export function toTreasuryMovementPayload(values: TreasuryMovementFormValues): TreasuryMovementPayload | null {
  const subAccount = parseSubAccountValue(values.sub_account)
  if (subAccount === null || values.financial_account_id === null) {
    return null
  }
  return {
    type: values.type,
    direction: values.type === 'adjustment' && values.direction !== '' ? values.direction : null,
    movement_date: values.movement_date,
    financial_account_id: values.financial_account_id,
    counterpart_account_id: values.type === 'transfer' ? values.counterpart_account_id : null,
    amount: normalizeAmount(values.amount),
    description: values.description.trim(),
    cost_type_id: null,
    revenue_type_id: null,
    treasury_type_id: null,
    [SUB_ACCOUNT_FIELD[subAccount.kind]]: subAccount.id,
    registry_id: values.registry_id,
    work_order_id: values.work_order_id,
  }
}
