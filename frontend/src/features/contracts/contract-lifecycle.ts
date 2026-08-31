import type { ContractDetail, ContractStatusGroupValue } from '@/features/contracts/types'

/**
 * Which domain actions the contract's CURRENT status group admits (direttiva
 * utente 2026-08-31 rev.2):
 *
 * - Aperto | Pending    → "Modifica dati", "Modifica stato", "Valida", "Disdici"
 * - Chiuso positivo     → "Disdici" e "Programma", nient'altro
 * - Chiuso negativo     → "Riattiva", nient'altro
 *
 * Mirrors `App\Services\Contracts\ContractActionAvailability` verbatim: the
 * server is the authority (it gates `permissions.actions` with the same rule
 * and 422s the endpoints anyway), this is the UI half of the same
 * defense-in-depth pair — needed here because the bar must also decide the
 * affordances the server does not describe, such as the disabled "Programma".
 *
 * La SOSPENSIONE e' un asse ortogonale che la direttiva non cita e il flusso
 * BR-2/D-3 deve continuare a funzionare: un contratto sospeso sta sulla riga
 * `pending` "Sospeso", quindi tiene "Riattiva" e perde "Valida" (l'endpoint
 * lo rifiuta comunque).
 */
export interface ContractLifecycleActions {
  validate: boolean
  schedule: boolean
  terminate: boolean
  edit: boolean
  changeStatus: boolean
  reactivate: boolean
}

/** Groups a contract may sit on while still working, i.e. before either closure. */
const WORKING_GROUPS: ContractStatusGroupValue[] = ['open', 'pending']

/**
 * Ready-made `status_groups[]` for-select params, one per action picker
 * (hoisted at module level: an inline object literal would be a new
 * reference on every render — frontend.md §10).
 */
export const WORKING_GROUP_PARAMS: Record<string, ContractStatusGroupValue[]> = { status_groups: WORKING_GROUPS }
export const POSITIVE_GROUP_PARAMS: Record<string, ContractStatusGroupValue[]> = { status_groups: ['closed_won'] }
export const NEGATIVE_GROUP_PARAMS: Record<string, ContractStatusGroupValue[]> = { status_groups: ['closed_lost'] }

function isWorking(contract: ContractDetail): boolean {
  return WORKING_GROUPS.includes(contract.contract_status.group)
}

/** A closed contract (either side) can only be reopened through "Riattiva contratto". */
export function isContractClosedLost(contract: ContractDetail): boolean {
  return contract.contract_status.group === 'closed_lost'
}

export function contractLifecycleActions(contract: ContractDetail): ContractLifecycleActions {
  const working = isWorking(contract)
  const closedWon = contract.contract_status.group === 'closed_won'

  return {
    validate: working && !contract.is_suspended,
    schedule: closedWon,
    terminate: working || closedWon,
    edit: working,
    changeStatus: working,
    reactivate: isContractClosedLost(contract) || contract.is_suspended,
  }
}
