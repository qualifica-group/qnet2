import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  contractDetailQueryKey,
  reactivateContract,
  scheduleContract,
  terminateContract,
  updateContract,
  validateContract,
} from '@/features/contracts/api'
import type {
  ContractDetail,
  ScheduleContractPayload,
  TerminateContractPayload,
  UpdateContractPayload,
  ValidateContractPayload,
} from '@/features/contracts/types'
import type {
  EditContractFormValues,
  ScheduleContractFormValues,
  TerminateContractFormValues,
  ValidateContractFormValues,
} from '@/features/contracts/contract-schema'

/** Omits an optional key from the payload when the form left it at `null` ("no change"). */
function omitIfNull<T extends string, V>(key: T, value: V | null): Partial<Record<T, V>> {
  return value === null ? {} : ({ [key]: value } as Record<T, V>)
}

/** `contract_status_id: null` means "do not change the status" — never sent (BR-3). */
export function buildValidatePayload(values: ValidateContractFormValues): ValidateContractPayload {
  return {
    validated_at: values.validated_at,
    ...omitIfNull('contract_status_id', values.contract_status_id),
  }
}

/** `contract_status_id` is validated non-null by the schema before this runs. */
export function buildSchedulePayload(values: ScheduleContractFormValues): ScheduleContractPayload {
  return {
    expiry_date: values.expiry_date,
    renewal_date: values.renewal_date,
    contract_status_id: values.contract_status_id as number,
  }
}

/** `contract_status_id: null` falls back server-side to the system `terminated` row (BR-4). */
export function buildTerminatePayload(values: TerminateContractFormValues): TerminateContractPayload {
  return {
    terminated_at: values.terminated_at,
    termination_reason: values.termination_reason,
    ...omitIfNull('contract_status_id', values.contract_status_id),
  }
}

/** `contract_status_id` is validated non-null by the schema before this runs (the FK is NOT NULL). */
export function buildEditPayload(values: EditContractFormValues): UpdateContractPayload {
  return {
    contract_status_id: values.contract_status_id as number,
    renewal_date: values.renewal_date,
    expiry_date: values.expiry_date,
    payment_notes: values.payment_notes,
    comments: values.comments,
  }
}

interface ContractMutationOptions {
  contractId: number
  onSuccess?: (contract: ContractDetail) => void
}

/**
 * Every contract mutation shares the same shape: call the action endpoint,
 * seed the fresh detail into the query cache (so the next open of the panel
 * — and any already-mounted view — reflects it immediately) and let the
 * caller close its own dialog/toast. Mirrors `useRewardStatusForm`'s
 * `queryClient.setQueryData` after a successful write.
 */
function useContractMutation<TPayload>(
  { contractId, onSuccess }: ContractMutationOptions,
  mutationFn: (id: number, payload: TPayload) => Promise<ContractDetail>,
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: TPayload) => mutationFn(contractId, payload),
    onSuccess: (contract) => {
      queryClient.setQueryData(contractDetailQueryKey(contractId), (previous: unknown) =>
        previous && typeof previous === 'object' ? { ...previous, ...contract } : previous,
      )
      onSuccess?.(contract)
    },
  })
}

export function useUpdateContract(options: ContractMutationOptions) {
  return useContractMutation(options, updateContract)
}

export function useValidateContract(options: ContractMutationOptions) {
  return useContractMutation(options, validateContract)
}

export function useScheduleContract(options: ContractMutationOptions) {
  return useContractMutation(options, scheduleContract)
}

export function useTerminateContract(options: ContractMutationOptions) {
  return useContractMutation(options, terminateContract)
}

/** No payload: `reactivateContract` only takes the id. */
export function useReactivateContract({ contractId, onSuccess }: ContractMutationOptions) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => reactivateContract(contractId),
    onSuccess: (contract) => {
      queryClient.setQueryData(contractDetailQueryKey(contractId), (previous: unknown) =>
        previous && typeof previous === 'object' ? { ...previous, ...contract } : previous,
      )
      onSuccess?.(contract)
    },
  })
}
