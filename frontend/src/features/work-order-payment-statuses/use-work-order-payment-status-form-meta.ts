import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { WorkOrderPaymentStatusFormMode } from '@/features/work-order-payment-statuses/types'

/** Metadata-loading state driving what `WorkOrderPaymentStatusForm` renders. */
export type WorkOrderPaymentStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.workOrderPaymentStatus.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/work-order-payment-statuses`) once.
 */
export function useWorkOrderPaymentStatusFormMeta(mode: WorkOrderPaymentStatusFormMode): WorkOrderPaymentStatusFormMetaState {
  const metaQuery = useResourceMeta('work-order-payment-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.workOrderPaymentStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
