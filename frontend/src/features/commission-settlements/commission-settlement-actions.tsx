import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Ban, CalendarCheck, CircleCheck, FileCheck2, FileX2, RefreshCw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CommissionSettlementCancelDialog } from '@/features/commission-settlements/commission-settlement-cancel-dialog'
import { SettlementApprovalDialog } from '@/features/commission-settlements/settlement-approval-dialog'
import { SettlementDocumentDialog } from '@/features/commission-settlements/settlement-document-dialog'
import type { SettlementDetailActions } from '@/features/commission-settlements/use-settlement-detail-actions'
import type { SettlementDetail } from '@/features/commission-settlements/types'

interface CommissionSettlementActionsProps {
  settlement: SettlementDetail
  actions: SettlementDetailActions
  /** Called after a dialog saved so the caller refreshes its grid. */
  onChanged: () => void
}

/**
 * Action buttons of a settlement, in the record's identity band (a new
 * adjustment lives on the movements section), each rendered only when the server's
 * `abilities` allow it (the UI hides, the backend authorizes). Emetti and
 * Elimina ask for confirmation; Annulla asks for the mandatory reason; Registra
 * documento and Approva (spec 0267) open their own dialog, Rimuovi documento confirms.
 */
export function CommissionSettlementActions({ settlement, actions, onChanged }: CommissionSettlementActionsProps) {
  const { t } = useTranslation()
  const { abilities } = settlement
  const [cancelOpen, setCancelOpen] = useState(false)
  const [documentOpen, setDocumentOpen] = useState(false)
  const [approvalOpen, setApprovalOpen] = useState(false)

  return (
    <>
      <div className="flex flex-wrap items-center justify-end gap-1.5">
        {abilities.recalculate ? (
          <Button variant="secondary" size="sm" disabled={actions.isBusy} onClick={actions.recalculate}>
            <RefreshCw className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.recalculate')}
          </Button>
        ) : null}
        {abilities.issue ? (
          <Button size="sm" disabled={actions.isBusy} onClick={actions.issue}>
            <CalendarCheck className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.issue')}
          </Button>
        ) : null}
        {abilities.receive_document ? (
          <Button size="sm" disabled={actions.isBusy} onClick={() => setDocumentOpen(true)}>
            <FileCheck2 className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.receiveDocument')}
          </Button>
        ) : null}
        {abilities.approve ? (
          <Button size="sm" disabled={actions.isBusy} onClick={() => setApprovalOpen(true)}>
            <CircleCheck className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.approve')}
          </Button>
        ) : null}
        {abilities.remove_document ? (
          <Button variant="outline" size="sm" className="bg-card" disabled={actions.isBusy} onClick={actions.removeDocument}>
            <FileX2 className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.removeDocument')}
          </Button>
        ) : null}
        {abilities.cancel ? (
          <Button variant="outline" size="sm" className="bg-card" disabled={actions.isBusy} onClick={() => setCancelOpen(true)}>
            <Ban className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.cancel')}
          </Button>
        ) : null}
        {abilities.delete ? (
          <Button variant="destructive" size="sm" disabled={actions.isBusy} onClick={actions.remove}>
            <Trash2 className="size-3.5" aria-hidden="true" />
            {t('commissionSettlements.actions.delete')}
          </Button>
        ) : null}
      </div>
      <SettlementDocumentDialog
        open={documentOpen}
        settlement={settlement}
        onChanged={onChanged}
        onClose={() => setDocumentOpen(false)}
      />
      <SettlementApprovalDialog
        open={approvalOpen}
        settlement={settlement}
        onChanged={onChanged}
        onClose={() => setApprovalOpen(false)}
      />
      <CommissionSettlementCancelDialog
        open={cancelOpen}
        isPending={actions.isCancelling}
        onClose={() => setCancelOpen(false)}
        onConfirm={(reason) => actions.cancel(reason, () => setCancelOpen(false))}
      />
    </>
  )
}
