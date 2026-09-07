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
  buildChangeContractStatusSchema,
  type ChangeContractStatusFormValues,
} from '@/features/contracts/contract-schema'
import { WORKING_GROUP_PARAMS } from '@/features/contracts/contract-lifecycle'
import { buildChangeStatusPayload, useChangeContractStatus } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['contract_status_id'] as const

interface ContractChangeStatusDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onChanged: (contract: ContractDetailWithPermissions) => void
}

/**
 * "Modifica stato" (direttiva 2026-08-31 rev.2): sposta un contratto ancora
 * in lavorazione su un altro stato dei gruppi Aperto/Pending. Non apre e non
 * chiude nulla — le chiusure restano appannaggio di "Valida"/"Disdici" — e
 * infatti il picker chiede al for-select solo quei due gruppi, mentre il
 * server ricontrolla sia il gruppo di destinazione sia quello di partenza.
 */
export function ContractChangeStatusDialog({
  open,
  onOpenChange,
  contract,
  onChanged,
}: ContractChangeStatusDialogProps) {
  const { t } = useTranslation()
  const schema = buildChangeContractStatusSchema(t)
  const defaultValues: ChangeContractStatusFormValues = { contract_status_id: contract.contract_status_id }

  const form = useForm<ChangeContractStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const changeStatusMutation = useChangeContractStatus({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.changeStatusDialog.success'))
      onChanged(updated)
      onOpenChange(false)
    },
  })

  const onSubmit = async (values: ChangeContractStatusFormValues) => {
    try {
      await changeStatusMutation.mutateAsync(buildChangeStatusPayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.changeStatusDialog.genericError'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(defaultValues)
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.changeStatus')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.changeStatusDialog.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="contract-change-status-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <RelationSelectField
              control={form.control}
              name="contract_status_id"
              metaKey="contract_status_id"
              label={t('contracts.actions.changeStatusDialog.status')}
              resource="contract-statuses"
              searchPlaceholder={t('contracts.actions.statusSearch')}
              params={WORKING_GROUP_PARAMS}
              selected={contract.contract_status}
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
          <Button type="submit" form="contract-change-status-form" disabled={changeStatusMutation.isPending}>
            {changeStatusMutation.isPending
              ? t('contracts.actions.changeStatusDialog.saving')
              : t('contracts.actions.changeStatusDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
