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
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildScheduleContractSchema,
  scheduleContractDefaultValues,
  type ScheduleContractFormValues,
} from '@/features/contracts/contract-schema'
import { buildSchedulePayload, useScheduleContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['expiry_date', 'renewal_date', 'contract_status_id'] as const

interface ContractScheduleDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onScheduled: (contract: ContractDetailWithPermissions) => void
}

/** "Programma contratto": expiry date + destination status are mandatory (D-2), renewal date optional and never after expiry. */
export function ContractScheduleDialog({
  open,
  onOpenChange,
  contract,
  onScheduled,
}: ContractScheduleDialogProps) {
  const { t } = useTranslation()
  const schema = buildScheduleContractSchema(t)

  const form = useForm<ScheduleContractFormValues>({
    resolver: zodResolver(schema),
    defaultValues: scheduleContractDefaultValues(),
  })

  const scheduleMutation = useScheduleContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.scheduleDialog.success'))
      onScheduled(updated)
      onOpenChange(false)
      form.reset(scheduleContractDefaultValues())
    },
  })

  const onSubmit = async (values: ScheduleContractFormValues) => {
    try {
      await scheduleMutation.mutateAsync(buildSchedulePayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.scheduleDialog.genericError'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(scheduleContractDefaultValues())
        }
        onOpenChange(next)
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.schedule')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.scheduleDialog.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="contract-schedule-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="expiry_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('contracts.actions.scheduleDialog.expiryDate')}</FormLabel>
                  <FormControl>
                    <Input type="date" value={field.value} onChange={field.onChange} onBlur={field.onBlur} name={field.name} ref={field.ref} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="renewal_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('contracts.actions.scheduleDialog.renewalDate')}</FormLabel>
                  <FormControl>
                    <Input
                      type="date"
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <RelationSelectField
              control={form.control}
              name="contract_status_id"
              metaKey="contract_status_id"
              label={t('contracts.actions.scheduleDialog.status')}
              resource="contract-statuses"
              searchPlaceholder={t('contracts.actions.statusSearch')}
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
          <Button type="submit" form="contract-schedule-form" disabled={scheduleMutation.isPending}>
            {scheduleMutation.isPending ? t('contracts.actions.scheduleDialog.saving') : t('contracts.actions.scheduleDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
