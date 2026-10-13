import { useEffect } from 'react'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchApproverOptions, purchaseRequestKeys } from '@/features/purchase-requests/api'
import type { RequestRefs } from '@/features/purchase-requests/purchase-request-refs'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import type { ApproverOptions, NamedRef } from '@/features/purchase-requests/types'

interface UsePurchaseRequestApproversArgs {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  refs: RequestRefs
}

const NO_APPROVERS: NamedRef[] = []

/**
 * Approver options of the RDA header (spec 0245): the allowed approvers for the current
 * requester and business function, kept in sync with the form value. A saved responsible
 * that is out of rule (D-3) stays untouched until requester or function change.
 */
export function usePurchaseRequestApprovers({ control, setValue, refs }: UsePurchaseRequestApproversArgs) {
  const requesterId = useWatch({ control, name: 'requester_id' })
  const businessFunctionId = useWatch({ control, name: 'business_function_id' })
  const managerId = useWatch({ control, name: 'function_manager_id' })

  const query = useQuery<ApproverOptions, AxiosError>({
    queryKey: purchaseRequestKeys.approverOptions(requesterId, businessFunctionId),
    queryFn: () => fetchApproverOptions(requesterId as number, businessFunctionId),
    enabled: requesterId !== null,
  })

  const saved = refs.function_manager_id
  const savedUntouched =
    saved !== null &&
    managerId === saved.id &&
    requesterId === (refs.requester_id?.id ?? null) &&
    businessFunctionId === (refs.business_function_id?.id ?? null)
  const approvers = query.data?.approvers ?? NO_APPROVERS
  const loaded = query.data !== undefined

  useEffect(() => {
    if (!loaded || savedUntouched || approvers.some((approver) => approver.id === managerId)) {
      return
    }
    if (approvers.length === 1) {
      setValue('function_manager_id', approvers[0].id, { shouldDirty: true, shouldValidate: true })
    } else if (managerId !== null) {
      setValue('function_manager_id', null, { shouldDirty: true })
    }
  }, [loaded, savedUntouched, approvers, managerId, setValue])

  const legacy = savedUntouched && !approvers.some((approver) => approver.id === saved.id) ? [saved] : NO_APPROVERS
  return {
    options: legacy.length > 0 ? [...approvers, ...legacy] : approvers,
    source: query.data?.source ?? null,
    hasRequester: requesterId !== null,
    isError: query.isError,
  }
}
