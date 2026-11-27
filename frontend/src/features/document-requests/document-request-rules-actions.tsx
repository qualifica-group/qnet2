import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ListRestart, Sparkles } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { itemsToRevalidate, requestActions } from '@/features/document-requests/document-request-status'
import type { DocumentRequest } from '@/features/document-requests/types'
import {
  actionErrorReason,
  useRefreshDocumentRequestRules,
  useRevalidateDocumentRequestItems,
} from '@/features/document-requests/use-document-request-rules'

/** "Aggiorna le regole dalla checklist" on an open request, with confirmation (spec 0432 AC-009). */
export function DocumentRequestRefreshRulesButton({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const refresh = useRefreshDocumentRequestRules(request.work_order_id, request.id)

  if (!requestActions(request).refreshRules) {
    return null
  }

  async function refreshRules() {
    const confirmed = await confirm({
      title: t('documentRequests.rules.refreshTitle'),
      description: t('documentRequests.rules.refreshDescription', { name: request.checklist.name }),
      confirmLabel: t('documentRequests.rules.refresh'),
      cancelLabel: t('documentRequests.rules.keep'),
    })
    if (confirmed) {
      refresh.mutate(undefined, {
        onSuccess: () => toast.success(t('documentRequests.rules.refreshQueued')),
        onError: (error) => toast.error(actionErrorReason(error) ?? t('documentRequests.rules.refreshError')),
      })
    }
  }

  return (
    <Button type="button" size="sm" variant="secondary" disabled={refresh.isPending} onClick={() => void refreshRules()}>
      <ListRestart aria-hidden="true" />
      {t('documentRequests.rules.refresh')}
    </Button>
  )
}

/** After a refresh: the changed items with a rejected file or one to review, revalidated together after one confirmation. */
export function DocumentRequestRevalidateNotice({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const revalidate = useRevalidateDocumentRequestItems(request.work_order_id, request.id)
  const items = itemsToRevalidate(request)

  if (items.length === 0) {
    return null
  }

  async function revalidateAll() {
    const confirmed = await confirm({
      title: t('documentRequests.rules.revalidateAllTitle'),
      description: t('documentRequests.rules.revalidateAllDescription', {
        count: items.length,
        names: items.map((item) => item.name).join(', '),
      }),
      confirmLabel: t('documentRequests.rules.revalidateAll'),
      cancelLabel: t('documentRequests.rules.keep'),
    })
    if (confirmed) {
      revalidate.mutate(
        items.map((item) => Number(item.id)),
        {
          onSuccess: () => toast.success(t('documentRequests.rules.revalidateQueued')),
          onError: (error) => toast.error(actionErrorReason(error) ?? t('documentRequests.rules.revalidateError')),
        },
      )
    }
  }

  return (
    <div
      role="status"
      className="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-xs"
    >
      <span className="min-w-0 flex-1">{t('documentRequests.rules.changedItems', { count: items.length })}</span>
      <Button type="button" size="sm" disabled={revalidate.isPending} onClick={() => void revalidateAll()}>
        <Sparkles aria-hidden="true" />
        {t('documentRequests.rules.revalidateAll')}
      </Button>
    </div>
  )
}
