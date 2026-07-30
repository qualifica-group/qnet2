import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { PaymentMethodFormMode } from '@/features/payment-methods/types'

/** Metadata-loading state driving what `PaymentMethodForm` renders. */
export type PaymentMethodFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.paymentMethod.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/payment-methods`)
 * once. `code.editable` differs between the two (D-3): true only in create.
 */
export function usePaymentMethodFormMeta(mode: PaymentMethodFormMode): PaymentMethodFormMetaState {
  const metaQuery = useResourceMeta('payment-methods', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.paymentMethod.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
