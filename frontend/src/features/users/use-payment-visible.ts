import { useAbilities } from '@/features/auth/use-abilities'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { MANAGE_COMPENSATION_PERMISSION } from '@/features/users/contract-history-types'

/**
 * Whether the actor may see the payment account (spec 0272 D-5): the field
 * metadata exposes it AND the actor holds `users.manageCompensation`. Without
 * both the section is not rendered at all, not even its labels.
 */
export function usePaymentVisible(): boolean {
  const { field } = useResourcePermissions()
  const { can } = useAbilities()
  return field('employment.iban').visible && can(MANAGE_COMPENSATION_PERMISSION)
}
