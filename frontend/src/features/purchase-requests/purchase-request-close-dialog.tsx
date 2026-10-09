import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { closePurchaseRequest, fetchClosure, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

const REASON_MAX = 2000

interface PurchaseRequestCloseDialogProps {
  requestId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  onClosed: (request: PurchaseRequest) => void
}

/**
 * Closes an RDA by hand. Reads the closure state first: when lines are not
 * yet terminal the closure is forced and the reason becomes mandatory.
 */
export function PurchaseRequestCloseDialog({ requestId, open, onOpenChange, onClosed }: PurchaseRequestCloseDialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [reason, setReason] = useState('')
  const closure = useQuery({
    queryKey: purchaseRequestKeys.closure(requestId),
    queryFn: () => fetchClosure(requestId),
    enabled: open,
    staleTime: 0,
  })
  const isForced = closure.data?.is_forced ?? false
  const trimmed = reason.trim()

  const mutation = useMutation({
    mutationFn: () => closePurchaseRequest(requestId, trimmed === '' ? undefined : trimmed),
    onSuccess: (closed) => {
      queryClient.setQueryData(purchaseRequestKeys.detail(requestId), closed)
      void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
      toast.success(t('purchaseRequests.close.done'))
      onOpenChange(false)
      onClosed(closed)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseRequests.close.error')),
  })

  const canConfirm = closure.isSuccess && !mutation.isPending && (!isForced || trimmed !== '')

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{t('purchaseRequests.close.title')}</DialogTitle>
          <DialogDescription>{t('purchaseRequests.close.description')}</DialogDescription>
        </DialogHeader>
        {closure.isPending ? (
          <Skeleton className="h-16 w-full" />
        ) : closure.isError ? (
          <p role="alert" className="text-sm text-destructive">
            {t('purchaseRequests.close.loadError')}
          </p>
        ) : (
          <div className="flex flex-col gap-2">
            {isForced ? (
              <p role="status" className="rounded-md border border-destructive/30 bg-destructive/5 px-2.5 py-1.5 text-xs text-destructive">
                {t('purchaseRequests.close.forcedWarning', { count: closure.data.non_terminal_count })}
              </p>
            ) : null}
            <Label htmlFor="purchase-request-close-reason" className="text-xs">
              {isForced ? t('purchaseRequests.close.reasonRequired') : t('purchaseRequests.close.reason')}
            </Label>
            <Textarea
              id="purchase-request-close-reason"
              className="min-h-16 text-sm"
              maxLength={REASON_MAX}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </div>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!canConfirm} onClick={() => mutation.mutate()}>
            {t('purchaseRequests.close.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
