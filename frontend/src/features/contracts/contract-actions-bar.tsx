import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { BadgeCheck, CalendarClock, FileText, Handshake, Pencil, RotateCcw, XOctagon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Can } from '@/features/auth/can'
import { ContractValidateDialog } from '@/features/contracts/contract-validate-dialog'
import { ContractScheduleDialog } from '@/features/contracts/contract-schedule-dialog'
import { ContractTerminateDialog } from '@/features/contracts/contract-terminate-dialog'
import { ContractEditDialog } from '@/features/contracts/contract-edit-dialog'
import { useReactivateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/** Which action dialog (if any) is currently open. */
type OpenDialog = 'none' | 'validate' | 'schedule' | 'terminate' | 'edit'

interface ContractActionsBarProps {
  contract: ContractDetailWithPermissions
  onChanged: (contract: ContractDetailWithPermissions) => void
}

/**
 * Every domain action gated on its own `permissions.actions` flag (AC-044):
 * absent entirely when the actor lacks the ability, never merely disabled.
 * "Riattiva contratto" additionally requires `is_suspended` (D-3/AC-048).
 * "Visualizza preventivo"/"Apri opportunità" are plain gated links, not
 * mutations.
 */
export function ContractActionsBar({ contract, onChanged }: ContractActionsBarProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [openDialog, setOpenDialog] = useState<OpenDialog>('none')

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
      await reactivateMutation.mutateAsync()
    } catch {
      toast.error(t('contracts.actions.reactivateDialog.genericError'))
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2 border-b px-6 py-3">
      {contract.permissions.actions.validate ? (
        <Button type="button" variant="secondary" size="sm" onClick={() => setOpenDialog('validate')}>
          <BadgeCheck aria-hidden="true" />
          {t('contracts.actions.validate')}
        </Button>
      ) : null}

      {contract.permissions.actions.schedule ? (
        <Button type="button" variant="secondary" size="sm" onClick={() => setOpenDialog('schedule')}>
          <CalendarClock aria-hidden="true" />
          {t('contracts.actions.schedule')}
        </Button>
      ) : null}

      {contract.permissions.actions.terminate ? (
        <Button type="button" variant="secondary" size="sm" onClick={() => setOpenDialog('terminate')}>
          <XOctagon aria-hidden="true" />
          {t('contracts.actions.terminate')}
        </Button>
      ) : null}

      {contract.is_suspended && contract.permissions.actions.reactivate ? (
        <Button type="button" variant="secondary" size="sm" onClick={() => void handleReactivate()} disabled={reactivateMutation.isPending}>
          <RotateCcw aria-hidden="true" />
          {t('contracts.actions.reactivate')}
        </Button>
      ) : null}

      {contract.permissions.resource.update ? (
        <Button type="button" variant="outline" className="bg-card" size="sm" onClick={() => setOpenDialog('edit')}>
          <Pencil aria-hidden="true" />
          {t('contracts.actions.edit.title')}
        </Button>
      ) : null}

      <Can permission="quotes.view">
        <Button type="button" variant="outline" className="bg-card" size="sm" asChild>
          <Link to={`/quotes/${contract.quote_id}`}>
            <FileText aria-hidden="true" />
            {t('contracts.actions.viewQuote')}
          </Link>
        </Button>
      </Can>

      {contract.opportunity ? (
        <Can permission="opportunities.view">
          <Button type="button" variant="outline" className="bg-card" size="sm" asChild>
            <Link to={`/opportunities/${contract.opportunity.id}`}>
              <Handshake aria-hidden="true" />
              {t('contracts.actions.openOpportunity')}
            </Link>
          </Button>
        </Can>
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
      <ContractEditDialog
        open={openDialog === 'edit'}
        onOpenChange={(open) => setOpenDialog(open ? 'edit' : 'none')}
        contract={contract}
        onUpdated={onChanged}
      />
    </div>
  )
}
