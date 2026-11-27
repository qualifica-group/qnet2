import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Check, CircleStop, Sparkles, X } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { DocumentRequestReviewDialog } from '@/features/document-requests/document-request-review-dialog'
import { hasManualDecision, itemReviewActions } from '@/features/document-requests/document-request-status'
import type {
  DocumentRequest,
  DocumentRequestItem,
  DocumentRequestReviewDecision,
} from '@/features/document-requests/types'
import {
  actionErrorReason,
  useRevalidateDocumentRequestItems,
  useStopDocumentRequestItemValidation,
} from '@/features/document-requests/use-document-request-rules'

/** Manual review (spec 0429), AI revalidation and stop of the validation (spec 0432) of one item. */
export function DocumentRequestItemActions({ request, item }: { request: DocumentRequest; item: DocumentRequestItem }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [decision, setDecision] = useState<DocumentRequestReviewDecision | null>(null)
  const revalidate = useRevalidateDocumentRequestItems(request.work_order_id, request.id)
  const stop = useStopDocumentRequestItemValidation(request.work_order_id, request.id)
  const allowed = itemReviewActions(request, item)
  const busy = revalidate.isPending || stop.isPending

  if (!allowed.approve && !allowed.reject && !allowed.revalidate && !allowed.stopValidation) {
    return null
  }

  async function revalidateItem() {
    const confirmed = await confirm({
      title: t('documentRequests.rules.revalidateTitle'),
      description: hasManualDecision(item)
        ? t('documentRequests.rules.revalidateManualDescription', { name: item.name })
        : t('documentRequests.rules.revalidateDescription', { name: item.name }),
      confirmLabel: t('documentRequests.rules.revalidate'),
      cancelLabel: t('documentRequests.rules.keep'),
      tone: hasManualDecision(item) ? 'warning' : undefined,
    })
    if (confirmed) {
      revalidate.mutate([Number(item.id)], {
        onSuccess: () => toast.success(t('documentRequests.rules.revalidateQueued')),
        onError: (error) => toast.error(actionErrorReason(error) ?? t('documentRequests.rules.revalidateError')),
      })
    }
  }

  async function stopValidation() {
    const confirmed = await confirm({
      title: t('documentRequests.rules.stopTitle'),
      description: t('documentRequests.rules.stopDescription', { name: item.name }),
      confirmLabel: t('documentRequests.rules.stop'),
      cancelLabel: t('documentRequests.rules.keep'),
    })
    if (confirmed) {
      stop.mutate(item.id, {
        onSuccess: () => toast.success(t('documentRequests.rules.stopQueued')),
        onError: (error) => toast.error(actionErrorReason(error) ?? t('documentRequests.rules.stopError')),
      })
    }
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {allowed.approve ? (
        <Button type="button" size="sm" variant="secondary" onClick={() => setDecision('approve')}>
          <Check aria-hidden="true" />
          {t('documentRequests.review.approve')}
        </Button>
      ) : null}
      {allowed.reject ? (
        <Button type="button" size="sm" variant="destructive" onClick={() => setDecision('reject')}>
          <X aria-hidden="true" />
          {t('documentRequests.review.reject')}
        </Button>
      ) : null}
      {allowed.revalidate ? (
        <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={() => void revalidateItem()}>
          <Sparkles aria-hidden="true" />
          {t('documentRequests.rules.revalidate')}
        </Button>
      ) : null}
      {allowed.stopValidation ? (
        <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={() => void stopValidation()}>
          <CircleStop aria-hidden="true" />
          {t('documentRequests.rules.stop')}
        </Button>
      ) : null}
      {decision ? (
        <DocumentRequestReviewDialog
          request={request}
          item={item}
          decision={decision}
          open
          onOpenChange={(open) => (open ? undefined : setDecision(null))}
        />
      ) : null}
    </div>
  )
}
