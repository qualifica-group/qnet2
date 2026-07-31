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
  buildValidateContractSchema,
  validateContractDefaultValues,
  type ValidateContractFormValues,
} from '@/features/contracts/contract-schema'
import { buildValidatePayload, useValidateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['validated_at', 'contract_status_id'] as const

interface ContractValidateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onValidated: (contract: ContractDetail) => void
}

/** "Valida contratto" (BR-3, AC-009/010/011/017): date defaults to today (never future), destination status optional. */
export function ContractValidateDialog({
  open,
  onOpenChange,
  contract,
  onValidated,
}: ContractValidateDialogProps) {
  const { t } = useTranslation()
  const schema = buildValidateContractSchema(t)

  const form = useForm<ValidateContractFormValues>({
    resolver: zodResolver(schema),
    defaultValues: validateContractDefaultValues(),
  })

  const validateMutation = useValidateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.validateDialog.success'))
      onValidated(updated)
      onOpenChange(false)
      form.reset(validateContractDefaultValues())
    },
  })

  const onSubmit = async (values: ValidateContractFormValues) => {
    try {
      await validateMutation.mutateAsync(buildValidatePayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.validateDialog.genericError'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(validateContractDefaultValues())
        }
        onOpenChange(next)
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.validate')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.validateDialog.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="contract-validate-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="validated_at"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('contracts.actions.validateDialog.date')}</FormLabel>
                  <FormControl>
                    <Input type="date" value={field.value} onChange={field.onChange} onBlur={field.onBlur} name={field.name} ref={field.ref} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <RelationSelectField
              control={form.control}
              name="contract_status_id"
              metaKey="contract_status_id"
              label={t('contracts.actions.validateDialog.status')}
              resource="contract-statuses"
              searchPlaceholder={t('contracts.actions.statusSearch')}
              selected={contract.contract_status}
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
          <Button type="submit" form="contract-validate-form" disabled={validateMutation.isPending}>
            {validateMutation.isPending ? t('contracts.actions.validateDialog.saving') : t('contracts.actions.validateDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
