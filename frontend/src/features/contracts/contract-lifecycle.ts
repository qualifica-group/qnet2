import type { ContractDetail } from '@/features/contracts/types'

/**
 * Which domain actions the contract's CURRENT state admits (user directive
 * 2026-08-31), independently of the actor's abilities:
 *
 * - non validato → "Valida" e "Disdici"
 * - validato     → "Programma" e "Disdici" (mai più "Valida")
 * - disdetto     → solo "Riattiva contratto"
 *
 * Mirrors `App\Services\Contracts\ContractActionAvailability` verbatim: the
 * server is the authority (it gates `permissions.actions` with the same rule
 * and 422s the endpoints anyway), this is the UI half of the same
 * defense-in-depth pair — needed here because the bar must also decide the
 * two affordances the server does not describe, the disabled "Programma" and
 * "Modifica dati".
 */
export interface ContractLifecycleActions {
  validate: boolean
  schedule: boolean
  terminate: boolean
  edit: boolean
  reactivate: boolean
}

/** The disdetto contract takes the "Riattiva" dialog (it must pick a destination status), the suspended one the plain confirm. */
export function isContractTerminated(contract: ContractDetail): boolean {
  return contract.terminated_at !== null
}

/** Validating lands the contract on the `closed_won` "Validato" row, so the stamp and the group normally agree. */
function isValidated(contract: ContractDetail): boolean {
  return contract.validated_at !== null || contract.contract_status.group === 'closed_won'
}

export function contractLifecycleActions(contract: ContractDetail): ContractLifecycleActions {
  const terminated = isContractTerminated(contract)

  return {
    // A suspended contract has no working status to validate into: the
    // endpoint always 422s on it.
    validate: !terminated && !isValidated(contract) && !contract.is_suspended,
    schedule: !terminated && isValidated(contract),
    terminate: !terminated,
    edit: !terminated,
    // The only way out of the disdetta, and the pre-existing way out of a
    // suspension (BR-2/D-3).
    reactivate: terminated || contract.is_suspended,
  }
}
