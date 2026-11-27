import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Bell, CheckCheck, History, Pencil, RefreshCw, Ban } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import type { DocumentRequestAction } from '@/features/document-requests/api'
import { DocumentRequestEditDialog } from '@/features/document-requests/document-request-edit-dialog'
import { DocumentRequestRefreshRulesButton } from '@/features/document-requests/document-request-rules-actions'
import { requestActions } from '@/features/document-requests/document-request-status'
import { DocumentRequestSyncLogsDialog } from '@/features/document-requests/document-request-sync-logs-dialog'
import type { DocumentRequest } from '@/features/document-requests/types'
import { useDocumentRequestAction } from '@/features/document-requests/use-document-requests'

/** Actions of one request: shown by permission and by the remote state allowed by spec 0428. */
export function DocumentRequestActions({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const run = useDocumentRequestAction(request.work_order_id, request.id)
  const [editing, setEditing] = useState(false)
  const [showingLogs, setShowingLogs] = useState(false)
  const allowed = requestActions(request)

  function execute(action: DocumentRequestAction) {
    run.mutate(action, {
      onSuccess: () => toast.success(t(`documentRequests.actions.${action}Queued`)),
      onError: (error) => {
        // The 422 on `status` carries the reason (e.g. already completed): show it as is.
        const reason = error.response?.data?.errors?.status?.[0] ?? error.response?.data?.message
        toast.error(reason ?? t(`documentRequests.actions.${action}Error`))
      },
    })
  }

  async function cancelRequest() {
    const confirmed = await confirm({
      title: t('documentRequests.cancel.title'),
      description: t('documentRequests.cancel.description', { name: request.checklist.name }),
      confirmLabel: t('documentRequests.cancel.confirm'),
      cancelLabel: t('documentRequests.cancel.keep'),
      tone: 'destructive',
    })
    if (confirmed) {
      execute('cancel')
    }
  }

  async function completeRequest() {
    const confirmed = await confirm({
      title: t('documentRequests.complete.title'),
      description: t('documentRequests.complete.description', { name: request.checklist.name }),
      confirmLabel: t('documentRequests.complete.confirm'),
      cancelLabel: t('documentRequests.complete.keep'),
    })
    if (confirmed) {
      execute('complete')
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      {allowed.edit ? (
        <Button type="button" size="sm" variant="secondary" onClick={() => setEditing(true)}>
          <Pencil aria-hidden="true" />
          {t('documentRequests.actions.edit')}
        </Button>
      ) : null}
      {allowed.remind ? (
        <Button type="button" size="sm" variant="secondary" disabled={run.isPending} onClick={() => execute('remind')}>
          <Bell aria-hidden="true" />
          {t('documentRequests.actions.remind')}
        </Button>
      ) : null}
      {allowed.refresh ? (
        <Button type="button" size="sm" variant="secondary" disabled={run.isPending} onClick={() => execute('refresh')}>
          <RefreshCw aria-hidden="true" />
          {t('documentRequests.actions.refresh')}
        </Button>
      ) : null}
      <DocumentRequestRefreshRulesButton request={request} />
      {allowed.complete ? (
        <Button type="button" size="sm" variant="secondary" disabled={run.isPending} onClick={() => void completeRequest()}>
          <CheckCheck aria-hidden="true" />
          {t('documentRequests.actions.complete')}
        </Button>
      ) : null}
      {allowed.cancel ? (
        <Button type="button" size="sm" variant="destructive" disabled={run.isPending} onClick={() => void cancelRequest()}>
          <Ban aria-hidden="true" />
          {t('documentRequests.actions.cancel')}
        </Button>
      ) : null}
      <Button type="button" size="sm" variant="secondary" onClick={() => setShowingLogs(true)}>
        <History aria-hidden="true" />
        {t('documentRequests.actions.logs')}
      </Button>
      {editing ? <DocumentRequestEditDialog request={request} open onOpenChange={setEditing} /> : null}
      {showingLogs ? <DocumentRequestSyncLogsDialog requestId={request.id} open onOpenChange={setShowingLogs} /> : null}
    </div>
  )
}
