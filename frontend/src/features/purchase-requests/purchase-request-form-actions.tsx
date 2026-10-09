import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { History, Lock, Send, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { PurchaseRequestCloseDialog } from '@/features/purchase-requests/purchase-request-close-dialog'
import { useNotifyManager, usePurchaseRequestDelete } from '@/features/purchase-requests/use-purchase-request-actions'
import { PURCHASE_REQUESTS_DOMAIN, type PurchaseRequest } from '@/features/purchase-requests/types'

interface PurchaseRequestFormActionsProps {
  request: PurchaseRequest
  onClosed: (request: PurchaseRequest) => void
  onDeleted: () => void
}

/** Record-level actions of a saved RDA: send to the manager, close, delete, activity. */
export function PurchaseRequestFormActions({ request, onClosed, onDeleted }: PurchaseRequestFormActionsProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const removeRequest = usePurchaseRequestDelete()
  const notify = useNotifyManager(request.id)
  const [closeOpen, setCloseOpen] = useState(false)
  const [activityOpen, setActivityOpen] = useState(false)
  const { abilities } = request
  const isOpen = request.status === 'open'

  const handleDelete = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('purchaseRequests.delete.title'),
      description: t('purchaseRequests.delete.description', { subject: request.subject }),
      confirmLabel: t('purchaseRequests.delete.confirm'),
    })
    if (confirmed && (await removeRequest(request.id))) {
      onDeleted()
    }
  }

  return (
    <>
      {isOpen && abilities.notify_manager ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="bg-card"
          disabled={notify.isPending}
          onClick={() => notify.mutate()}
        >
          <Send aria-hidden="true" />
          {t('purchaseRequests.actions.notifyManager')}
        </Button>
      ) : null}
      {isOpen && abilities.close ? (
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => setCloseOpen(true)}>
          <Lock aria-hidden="true" />
          {t('purchaseRequests.actions.close')}
        </Button>
      ) : null}
      {abilities.view_activity ? (
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => setActivityOpen(true)}>
          <History aria-hidden="true" />
          {t('purchaseRequests.actions.activity')}
        </Button>
      ) : null}
      {abilities.delete ? (
        <Button type="button" variant="outline" size="sm" className="bg-card text-destructive" onClick={() => void handleDelete()}>
          <Trash2 aria-hidden="true" />
          {t('purchaseRequests.actions.delete')}
        </Button>
      ) : null}
      <PurchaseRequestCloseDialog
        requestId={request.id}
        open={closeOpen}
        onOpenChange={setCloseOpen}
        onClosed={onClosed}
      />
      <ResourceActivityDialog
        resource={PURCHASE_REQUESTS_DOMAIN}
        row={activityOpen ? { id: request.id, actions: [] } : null}
        onOpenChange={setActivityOpen}
      />
    </>
  )
}
