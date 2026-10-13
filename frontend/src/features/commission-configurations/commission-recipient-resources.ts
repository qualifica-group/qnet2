import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { CommissionRecipientType } from './types'

/**
 * Recipient-picker resource per `recipient_type` (spec 0090 D-4, emenda 0089
 * D-8): a referent, a user or a registry, whichever the operator picked
 * (or the role's derived default). Kept in sync manually since the enum
 * lives server-side; the three types are exhaustive
 * (`COMMISSION_RECIPIENT_TYPES`).
 */
export const RECIPIENT_RESOURCE_BY_TYPE: Record<CommissionRecipientType, string> = {
  referent: REFERENTS_FOR_SELECT_RESOURCE,
  user: USERS_FOR_SELECT_RESOURCE,
  registry: REGISTRIES_FOR_SELECT_RESOURCE,
}
