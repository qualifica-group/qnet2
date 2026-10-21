import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const CONTRACT_TYPES_DOMAIN = 'contract-types'

/** A contract type of the catalogue (spec 0290). */
export interface ContractType {
  id: number
  name: string
  is_active: boolean
  sort_order: number
  created_at: string
  updated_at: string
}

/** A `ContractType` carrying the authorization metadata the detail's provider reads. */
export interface ContractTypeDetail extends ContractType {
  permissions: ResourcePermissions
}

/** POST/PUT body of the catalogue. */
export interface ContractTypePayload {
  name: string
  is_active?: boolean
  sort_order?: number
}

export type UpdateContractTypePayload = Partial<ContractTypePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type ContractTypeFormMode = { type: 'create' } | { type: 'edit'; contractType: ContractTypeDetail }
