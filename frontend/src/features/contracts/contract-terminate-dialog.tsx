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
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildTerminateContractSchema,
  terminateContractDefaultValues,
  type TerminateContractFormValues,
} from '@/features/contracts/contract-schema'
import { buildTerminatePayload, useTerminateContract } from '@/features/contracts/use-contract-mutations'
import { NEGATIVE_GROUP_PARAMS } from '@/features/contracts/contract-lifecycle'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['terminated_at', 'termination_reason', 'contract_status_id'] as const

interface ContractTerminateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onTerminated: (contract: ContractDetailWithPermissions) => void
}

/**
 * "Disdici contratto" (BR-4, AC-014/015/016/017): date and motivation are
 * mandatory, the destination status optional (empty = the system "Disdetto"
 * row) and restricted to the `closed_lost` group on BOTH ends — the picker
 * asks the for-select for that group alone, the server re-checks it.
 */
export function ContractTerminateDialog({
  open,
  onOpenChange,
  contract,
  onTerminated,
}: ContractTerminateDialogProps) {
  const { t } = useTranslation()
  const schema = buildTerminateContractSchema(t)

  const form = useForm<TerminateContractFormValues>({
    resolver: zodResolver(schema),
    defaultValues: terminateContractDefaultValues(),
  })

  const terminateMutation = useTerminateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.terminateDialog.success'))
      onTerminated(updated)
      onOpenChange(false)
      form.reset(terminateContractDefaultValues())
    },
  })

  const onSubmit = async (values: TerminateContractFormValues) => {
    try {
      await terminateMutation.mutateAsync(buildTerminatePayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.terminateDialog.genericError'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(terminateContractDefaultValues())
        }
        onOpenChange(next)
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.terminate')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.terminateDialog.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="contract-terminate-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="terminated_at"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('contracts.actions.terminateDialog.date')}</FormLabel>
                  <FormControl>
                    <Input type="date" value={field.value} onChange={field.onChange} onBlur={field.onBlur} name={field.name} ref={field.ref} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="termination_reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('contracts.actions.terminateDialog.reason')}</FormLabel>
                  <FormControl>
                    <Textarea rows={4} value={field.value} onChange={field.onChange} onBlur={field.onBlur} name={field.name} ref={field.ref} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <RelationSelectField
              control={form.control}
              name="contract_status_id"
              metaKey="contract_status_id"
              label={t('contracts.actions.terminateDialog.status')}
              resource="contract-statuses"
              searchPlaceholder={t('contracts.actions.statusSearch')}
              // Only negative-closure statuses (directive 2026-08-31 rev.2):
              // the server enforces the same group, this narrows the picker.
              params={NEGATIVE_GROUP_PARAMS}
              selected={null}
              required={false}
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
          <Button
            type="submit"
            form="contract-terminate-form"
            variant="destructive"
            disabled={terminateMutation.isPending}
          >
            {terminateMutation.isPending ? t('contracts.actions.terminateDialog.saving') : t('contracts.actions.terminateDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
