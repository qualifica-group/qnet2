import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Hourglass, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { fetchDocumentChecklist } from '@/features/document-checklists/api'
import { documentChecklistKeys } from '@/features/document-checklists/query-keys'
import { PENDING_DELIVERY_REFETCH_MS } from '@/features/document-requests/document-request-status'
import {
  actionErrorReason,
  useChecklistOpenRequestsCount,
  useRefreshChecklistOpenRequests,
} from '@/features/document-requests/use-document-request-rules'

interface ChecklistOpenRequestsDialogProps {
  checklistId: number
  onClose: () => void
}

/**
 * Asked right after a checklist is saved (spec 0432, D-1): the open requests keep
 * their rules unless the user chooses to refresh them. Nothing shows when there
 * is no open request; the caller mounts it again (new key) after every save.
 * "Aggiorna" waits until ArchivioSmart holds the corrected checklist.
 */
export function ChecklistOpenRequestsDialog({ checklistId, onClose }: ChecklistOpenRequestsDialogProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  // Without the permission the backend refuses the refresh: do not even ask.
  const count = useChecklistOpenRequestsCount(can('document-requests.update') ? checklistId : null)
  const open = (count.data ?? 0) > 0
  const checklist = useQuery({
    queryKey: documentChecklistKeys.detail(checklistId),
    queryFn: () => fetchDocumentChecklist(checklistId),
    enabled: open,
    refetchInterval: (query) => (query.state.data?.sync_status === 'pending' ? PENDING_DELIVERY_REFETCH_MS : false),
  })
  const refresh = useRefreshChecklistOpenRequests(checklistId)
  const syncStatus = checklist.data?.sync_status

  function refreshOpenRequests() {
    refresh.mutate(undefined, {
      onSuccess: (queued) => {
        toast.success(t('documentRequests.openRequests.queued', { count: queued }))
        onClose()
      },
      onError: (error) => toast.error(actionErrorReason(error) ?? t('documentRequests.openRequests.error')),
    })
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('documentRequests.openRequests.title')}</DialogTitle>
          <DialogDescription>{t('documentRequests.openRequests.description', { count: count.data ?? 0 })}</DialogDescription>
        </DialogHeader>
        {syncStatus === 'pending' ? (
          <p role="status" className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Hourglass className="size-3.5" aria-hidden="true" />
            {t('documentRequests.openRequests.waiting')}
          </p>
        ) : null}
        {syncStatus === 'failed' ? (
          <p role="alert" className="flex items-start gap-2 text-xs text-destructive">
            <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            {t('documentRequests.openRequests.syncFailed')}
          </p>
        ) : null}
        <DialogFooter>
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('documentRequests.openRequests.onlyNew')}
          </Button>
          <Button type="button" disabled={syncStatus !== 'synced' || refresh.isPending} onClick={refreshOpenRequests}>
            {t('documentRequests.openRequests.refresh')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
