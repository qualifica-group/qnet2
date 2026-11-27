import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Euro } from 'lucide-react'
import { DetailError } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'
import { useWorkOrderTeamEditor } from '@/features/work-orders/use-work-order-team-editor'
import { WorkOrderTeamPanel } from '@/features/work-orders/work-order-team-panel'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderTeamDialogProps {
  /** The work order to show; `null` keeps the dialog closed. */
  workOrderId: number | null
  onOpenChange: (open: boolean) => void
  /** After a saved change or a proforma request (the host refreshes its grid). */
  onMutated?: () => void
}

/** Past this height the people scroll inside the popup; the dialog node itself must never clip (see `DialogContent`). */
const BODY_SCROLL_CLASS = '-mx-1 max-h-[70vh] overflow-y-auto px-1'

/**
 * "Responsabili e Partecipanti" (spec 0405, legacy
 * `orders.responsabili-partecipanti`), the one popup of the grid row and of
 * the detail's "..." menu: the commessa's team as a CRM team panel
 * (`WorkOrderTeamPanel`, each change saved at once) and the "Richiesta
 * Proforma" button of the legacy modal.
 */
export function WorkOrderTeamDialog({ workOrderId, onOpenChange, onMutated }: WorkOrderTeamDialogProps) {
  return (
    <Dialog open={workOrderId !== null} onOpenChange={onOpenChange}>
      {workOrderId !== null ? (
        <WorkOrderTeamContent workOrderId={workOrderId} onClose={() => onOpenChange(false)} onMutated={onMutated} />
      ) : null}
    </Dialog>
  )
}

interface WorkOrderTeamContentProps {
  workOrderId: number
  onClose: () => void
  onMutated?: () => void
}

function WorkOrderTeamContent({ workOrderId, onClose, onMutated }: WorkOrderTeamContentProps) {
  const { t } = useTranslation()
  const { data: workOrder, error, isError, refetch } = useQuery<WorkOrderDetailWithPermissions>({
    queryKey: workOrderDetailQueryKey(workOrderId),
    queryFn: () => fetchWorkOrder(workOrderId),
  })

  return (
    // `grid-cols-1` = minmax(0, 1fr): no content may widen the dialog past its max width.
    <DialogContent size="sm" className="grid-cols-1">
      <DialogHeader className="min-w-0">
        <DialogTitle>{t('workOrders.actions.team.label')}</DialogTitle>
        {/* The commessa it is about: the same popup opens from rows of any list. */}
        <DialogDescription className="line-clamp-2 break-words">
          {workOrder ? workOrderCaption(workOrder, t) : null}
        </DialogDescription>
      </DialogHeader>
      {isError ? (
        <DetailError
          error={error}
          message={t('workOrders.detail.loadError')}
          retryLabel={t('common.retry')}
          onRetry={() => void refetch()}
        />
      ) : workOrder ? (
        <ResourcePermissionsProvider permissions={workOrder.permissions}>
          <WorkOrderTeamBody workOrder={workOrder} onClose={onClose} onMutated={onMutated} />
        </ResourcePermissionsProvider>
      ) : (
        <TeamPanelSkeleton />
      )}
    </DialogContent>
  )
}

/** Code and title, the code once: a generated title already starts with it ("COM-20533 - ..."). */
function workOrderCaption(workOrder: WorkOrderDetailWithPermissions, t: TFunction): string {
  return workOrder.title.startsWith(workOrder.code)
    ? workOrder.title
    : t('workOrders.actions.team.description', { code: workOrder.code, title: workOrder.title })
}

/** Loading: the two groups' heading and rows, as they will stand. */
function TeamPanelSkeleton() {
  return (
    <div className="flex flex-col gap-4" aria-hidden="true">
      {[0, 1].map((group) => (
        <div key={group} className="flex flex-col gap-1.5">
          <Skeleton className="h-4 w-28" />
          <div className="flex flex-col gap-2 rounded-lg border bg-card p-2">
            {[0, 1].map((row) => (
              <div key={row} className="flex items-center gap-2">
                <Skeleton className="size-6 rounded-full" />
                <Skeleton className="h-3.5 w-40" />
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

interface WorkOrderTeamBodyProps {
  workOrder: WorkOrderDetailWithPermissions
  onClose: () => void
  onMutated?: () => void
}

function WorkOrderTeamBody({ workOrder, onClose, onMutated }: WorkOrderTeamBodyProps) {
  const { t } = useTranslation()
  const editor = useWorkOrderTeamEditor(workOrder, onMutated)
  const [proformaOpen, setProformaOpen] = useState(false)

  return (
    <>
      <div className={BODY_SCROLL_CLASS}>
        <WorkOrderTeamPanel editor={editor} />
      </div>
      <DialogFooter className="gap-2">
        {workOrder.permissions.actions.request_proforma ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="bg-card sm:mr-auto"
            onClick={() => setProformaOpen(true)}
          >
            <Euro aria-hidden="true" />
            {t('proformaRequests.detailAction')}
          </Button>
        ) : null}
        <Button type="button" variant="secondary" size="sm" onClick={onClose}>
          {t('common.close')}
        </Button>
      </DialogFooter>
      {proformaOpen ? (
        <ProformaRequestDialog
          workOrderId={workOrder.id}
          workOrderCode={workOrder.code}
          open
          onOpenChange={setProformaOpen}
          onSent={() => {
            setProformaOpen(false)
            onMutated?.()
          }}
        />
      ) : null}
    </>
  )
}
