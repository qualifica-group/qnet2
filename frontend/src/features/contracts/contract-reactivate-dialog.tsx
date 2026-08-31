import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
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
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildReactivateContractSchema,
  reactivateContractDefaultValues,
  type ReactivateContractFormValues,
} from '@/features/contracts/contract-schema'
import { buildReactivatePayload, useReactivateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['contract_status_id'] as const

interface ContractReactivateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onReactivated: (contract: ContractDetail) => void
}

/**
 * "Riattiva contratto" sul percorso DISDETTO (direttiva utente 2026-08-31):
 * la disdetta viene annullata per intero (data, motivazione, autore) e il
 * contratto riparte dallo stato scelto qui — obbligatorio, perche' nessuna
 * colonna ha mai memorizzato quello precedente. Il picker offre la stessa
 * lista di stati attivi delle altre azioni (il for-select non ha filtro per
 * gruppo) e il server rifiuta un gruppo `closed_lost`.
 *
 * Il percorso SOSPESO non passa di qui: resta il confirm inline della barra
 * azioni, che non chiede nulla (BR-2/D-3).
 */
export function ContractReactivateDialog({
  open,
  onOpenChange,
  contract,
  onReactivated,
}: ContractReactivateDialogProps) {
  const { t } = useTranslation()
  const schema = buildReactivateContractSchema(t)

  const form = useForm<ReactivateContractFormValues>({
    resolver: zodResolver(schema),
    defaultValues: reactivateContractDefaultValues(),
  })

  const reactivateMutation = useReactivateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.reactivateDialog.success'))
      onReactivated(updated)
      onOpenChange(false)
      form.reset(reactivateContractDefaultValues())
    },
  })

  const onSubmit = async (values: ReactivateContractFormValues) => {
    try {
      await reactivateMutation.mutateAsync(buildReactivatePayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.reactivateDialog.genericError'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(reactivateContractDefaultValues())
        }
        onOpenChange(next)
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.reactivate')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.reactivateDialog.terminatedDescription')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="contract-reactivate-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <RelationSelectField
              control={form.control}
              name="contract_status_id"
              metaKey="contract_status_id"
              label={t('contracts.actions.reactivateDialog.status')}
              resource="contract-statuses"
              searchPlaceholder={t('contracts.actions.statusSearch')}
              // Nothing to preselect: the current status is "Disdetto", the
              // very one the action is leaving.
              selected={null}
              required
              placeholder={t('contracts.actions.statusPlaceholder')}
              emptyLabel={t('contracts.actions.statusEmpty')}
              errorLabel={t('contracts.actions.statusError')}
              clearLabel={t('common.clear')}
              retryLabel={t('common.retry')}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="contract-reactivate-form" disabled={reactivateMutation.isPending}>
            {reactivateMutation.isPending
              ? t('contracts.actions.reactivateDialog.saving')
              : t('contracts.actions.reactivateDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
