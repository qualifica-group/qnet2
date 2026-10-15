import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Ban, CalendarCheck, RefreshCw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CommissionSettlementCancelDialog } from '@/features/commission-settlements/commission-settlement-cancel-dialog'
import type { SettlementDetailActions } from '@/features/commission-settlements/use-settlement-detail-actions'
import type { SettlementAbilities } from '@/features/commission-settlements/types'

interface CommissionSettlementActionsProps {
  abilities: SettlementAbilities
  actions: SettlementDetailActions
}

/**
 * Action buttons of a settlement, in the record's identity band (a new
 * adjustment lives on the movements section), each rendered only when the server's
 * `abilities` allow it (the UI hides, the backend authorizes). Emetti and
 * Elimina ask for confirmation; Annulla asks for the mandatory reason.
 */
export function CommissionSettlementActions({ abilities, actions }: CommissionSettlementActionsProps) {
  const { t } = useTranslation()
  const [cancelOpen, setCancelOpen] = useState(false)

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
      <CommissionSettlementCancelDialog
        open={cancelOpen}
        isPending={actions.isCancelling}
        onClose={() => setCancelOpen(false)}
        onConfirm={(reason) => actions.cancel(reason, () => setCancelOpen(false))}
      />
    </>
  )
}
