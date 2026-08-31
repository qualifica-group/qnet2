import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { BadgeCheck, CalendarClock, Pencil, RotateCcw, Shuffle, XOctagon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { ContractValidateDialog } from '@/features/contracts/contract-validate-dialog'
import { ContractScheduleDialog } from '@/features/contracts/contract-schedule-dialog'
import { ContractTerminateDialog } from '@/features/contracts/contract-terminate-dialog'
import { ContractChangeStatusDialog } from '@/features/contracts/contract-change-status-dialog'
import { ContractEditDialog } from '@/features/contracts/contract-edit-dialog'
import { ContractReactivateDialog } from '@/features/contracts/contract-reactivate-dialog'
import { contractLifecycleActions, isContractClosed } from '@/features/contracts/contract-lifecycle'
import { useReactivateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/** Which action dialog (if any) is currently open. */
type OpenDialog = 'none' | 'validate' | 'schedule' | 'terminate' | 'edit' | 'reactivate' | 'change-status'

interface ContractActionsBarProps {
  contract: ContractDetailWithPermissions
  onChanged: (contract: ContractDetailWithPermissions) => void
}

/**
 * Every domain action gated on its own `permissions.actions` flag (AC-044):
 * absent entirely when the actor lacks the ability, never merely disabled.
 * On top of the ability, each one is gated on the contract's LIFECYCLE
 * (`contractLifecycleActions`, user directive 2026-08-31): "Valida"+"Disdici"
 * before the validation, "Programma"+"Disdici"+"Riapri" after it, only
 * "Riapri" once the contract is disdetto. "Programma" is rendered DISABLED
 * for now (same directive: the action will be repurposed).
 *
 * "Riapri contratto" is the one action a CLOSED contract keeps, on either
 * side of the closure (user directive 2026-08-31 rev.3): on that path it
 * opens a dialog asking for the destination status, while the suspended
 * path (D-3/AC-048) keeps its plain confirm — there the pre-suspension
 * status is restored server-side.
 *
 * This bar carries MUTATIONS only: reaching the Offerta or the Opportunità is
 * not an action but a link on the field that names them, in
 * `ContractDetailSections` (user directive 2026-08-31).
 *
 * Every button takes its look from `ACTION_BUTTON_VARIANT` keyed on the SAME
 * `type` the server's action catalog gives that key
 * (`ContractColumnCatalog::actions()`), so the grid's actions column and this
 * bar can never colour the same action differently (user directive
 * 2026-08-31): green for the positive closure (valida), red for the
 * negative one (disdici), neutral outline for everything else — riapertura
 * inclusa, che non e' un esito ma un ritorno in lavorazione (rev.3).
 */
export function ContractActionsBar({ contract, onChanged }: ContractActionsBarProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [openDialog, setOpenDialog] = useState<OpenDialog>('none')
  const lifecycle = contractLifecycleActions(contract)
  const closed = isContractClosed(contract)

  const reactivateMutation = useReactivateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.reactivateDialog.success'))
      onChanged({ ...contract, ...updated })
    },
  })

  const handleReactivate = async () => {
    const confirmed = await confirm({
      title: t('contracts.actions.reactivate'),
      description: t('contracts.actions.reactivateDialog.description'),
      confirmLabel: t('contracts.actions.reactivateDialog.confirm'),
    })
    if (!confirmed) {
      return
    }
    try {
      await reactivateMutation.mutateAsync({})
    } catch {
      toast.error(t('contracts.actions.reactivateDialog.genericError'))
    }
  }

  return (
    // Direttiva utente 2026-08-31: le azioni PRENDONO il posto della striscia
    // KPI, e con esso il suo vestito — la banda tinta (`border-y bg-muted/40`)
    // subito sotto l'header della record card, che e' anche l'unico spazio in
    // cui sei bottoni gated ci stanno davvero.
    <div className="flex flex-wrap items-center gap-2 border-y bg-muted/40 px-4 py-3">
      {lifecycle.validate && contract.permissions.actions.validate ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.success} size="sm" onClick={() => setOpenDialog('validate')}>
          <BadgeCheck aria-hidden="true" />
          {t('contracts.actions.validate')}
        </Button>
      ) : null}

      {lifecycle.schedule && contract.permissions.actions.schedule ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          disabled
          title={t('contracts.actions.scheduleUnavailable')}
          onClick={() => setOpenDialog('schedule')}
        >
          <CalendarClock aria-hidden="true" />
          {t('contracts.actions.schedule')}
        </Button>
      ) : null}

      {lifecycle.terminate && contract.permissions.actions.terminate ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.danger} size="sm" onClick={() => setOpenDialog('terminate')}>
          <XOctagon aria-hidden="true" />
          {t('contracts.actions.terminate')}
        </Button>
      ) : null}

      {lifecycle.reactivate && contract.permissions.actions.reactivate ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          onClick={() => (closed ? setOpenDialog('reactivate') : void handleReactivate())}
          disabled={reactivateMutation.isPending}
        >
          <RotateCcw aria-hidden="true" />
          {t('contracts.actions.reactivate')}
        </Button>
      ) : null}

      {lifecycle.edit && contract.permissions.resource.update ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.link} className="bg-card" size="sm" onClick={() => setOpenDialog('edit')}>
          <Pencil aria-hidden="true" />
          {t('contracts.actions.edit.title')}
        </Button>
      ) : null}

      {lifecycle.changeStatus && contract.permissions.actions.change_status ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.action} className="bg-card" size="sm" onClick={() => setOpenDialog('change-status')}>
          <Shuffle aria-hidden="true" />
          {t('contracts.actions.changeStatus')}
        </Button>
      ) : null}

      <ContractValidateDialog
        open={openDialog === 'validate'}
        onOpenChange={(open) => setOpenDialog(open ? 'validate' : 'none')}
        contract={contract}
        onValidated={(updated) => onChanged({ ...contract, ...updated })}
      />
      <ContractScheduleDialog
        open={openDialog === 'schedule'}
        onOpenChange={(open) => setOpenDialog(open ? 'schedule' : 'none')}
        contract={contract}
        onScheduled={(updated) => onChanged({ ...contract, ...updated })}
      />
      <ContractTerminateDialog
        open={openDialog === 'terminate'}
        onOpenChange={(open) => setOpenDialog(open ? 'terminate' : 'none')}
        contract={contract}
        onTerminated={(updated) => onChanged({ ...contract, ...updated })}
      />
      <ContractReactivateDialog
        open={openDialog === 'reactivate'}
        onOpenChange={(open) => setOpenDialog(open ? 'reactivate' : 'none')}
        contract={contract}
        onReactivated={(updated) => onChanged({ ...contract, ...updated })}
      />
      <ContractChangeStatusDialog
        open={openDialog === 'change-status'}
        onOpenChange={(open) => setOpenDialog(open ? 'change-status' : 'none')}
        contract={contract}
        onChanged={(updated) => onChanged({ ...contract, ...updated })}
      />
      <ContractEditDialog
        open={openDialog === 'edit'}
        onOpenChange={(open) => setOpenDialog(open ? 'edit' : 'none')}
        contract={contract}
        onUpdated={onChanged}
      />
    </div>
  )
}
