import { useCallback, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  createOutboundEmailDraft,
  outboundEmailQueryKey,
  outboundEmailsListQueryKey,
} from '@/features/outbound-emails/api'
import type { EmailOwnerRef, OutboundEmail } from '@/features/outbound-emails/types'
import { PURCHASE_ORDERS_DOMAIN } from '@/features/purchase-orders/types'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

export interface PurchaseOrderComposerTarget {
  owner: EmailOwnerRef
  emailId: number
}

const ownerOf = (orderId: number): EmailOwnerRef => ({ type: PURCHASE_ORDERS_DOMAIN, id: orderId })

/**
 * "Send to supplier": creates the email draft on `/purchase-orders/{id}/emails`
 * with the order PDF attached (`attach_pdf`, as invoices) and exposes the
 * target the caller mounts `OutboundEmailComposerDialog` on; the supplier's
 * email is the default recipient (`prefillDefaultTo`).
 */
export function usePurchaseOrderEmail() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [target, setTarget] = useState<PurchaseOrderComposerTarget | null>(null)

  const start = useMutation<OutboundEmail, unknown, number>({
    mutationFn: (orderId) => createOutboundEmailDraft(ownerOf(orderId), { attach_pdf: true }),
    onSuccess: (email, orderId) => {
      const owner = ownerOf(orderId)
      queryClient.setQueryData(outboundEmailQueryKey(owner, email.id), email)
      void queryClient.invalidateQueries({ queryKey: outboundEmailsListQueryKey(owner) })
      setTarget({ owner, emailId: email.id })
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.send.error')),
  })

  const { mutate } = start
  const startEmail = useCallback((orderId: number) => mutate(orderId), [mutate])
  const closeComposer = useCallback(() => setTarget(null), [])

  return { startEmail, isStarting: start.isPending, target, closeComposer }
}
