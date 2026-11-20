import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Euro } from 'lucide-react'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'
import { useWorkOrderInlineEdit } from '@/features/work-orders/use-work-order-inline-edit'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderTeamDialogProps {
  /** The work order to show; `null` keeps the dialog closed. */
  workOrderId: number | null
  onOpenChange: (open: boolean) => void
  /** After a saved change or a proforma request (the host refreshes its grid). */
  onMutated?: () => void
}

/**
 * "Responsabili e Partecipanti" of the grid row (spec 0405, legacy
 * `orders.responsabili-partecipanti`): the record's own Team block, edited in
 * place with the detail's rules, and the "Richiesta Proforma" button of the
 * legacy modal.
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
    <DialogContent className="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{t('workOrders.actions.team.label')}</DialogTitle>
        <DialogDescription>
          {t('workOrders.actions.team.description', { code: workOrder?.code ?? '' })}
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
        <DetailLoading />
      )}
    </DialogContent>
  )
}

interface WorkOrderTeamBodyProps {
  workOrder: WorkOrderDetailWithPermissions
  onClose: () => void
  onMutated?: () => void
}

function WorkOrderTeamBody({ workOrder, onClose, onMutated }: WorkOrderTeamBodyProps) {
  const { t } = useTranslation()
  const editor = useWorkOrderInlineEdit(workOrder, onMutated)
  const [proformaOpen, setProformaOpen] = useState(false)

  return (
    <>
      {/* Provider only (no DOM): the inline editors share the edit form. */}
      <Form {...editor.form}>
        <WorkOrderTeamSection
          supervisors={workOrder.supervisors}
          participants={workOrder.participants}
          form={editor}
          inline={editor.inline}
        />
      </Form>
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
