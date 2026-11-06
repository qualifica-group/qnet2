import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
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
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { buildRenewalDefaults } from '@/features/contracts/contract-renewal-defaults'
import { buildRenewContractSchema, type RenewContractFormValues } from '@/features/contracts/contract-renew-schema'
import { buildRenewPayload, useRenewContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetail, ContractDetailWithPermissions } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = ['renewal_date', 'expiry_date'] as const

interface ContractRenewDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetail
  onRenewed: (contract: ContractDetailWithPermissions) => void
}

/**
 * "Rinnova" (spec 0358): opens the next period of the contract. The form is
 * mounted only while the dialog is open, so the dates (D-13) are recomputed
 * from the contract as it is each time the user opens it.
 */
export function ContractRenewDialog({ open, onOpenChange, contract, onRenewed }: ContractRenewDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.renew')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.renewDialog.description')}</DialogDescription>
        </DialogHeader>
        {open ? <RenewForm contract={contract} onClose={() => onOpenChange(false)} onRenewed={onRenewed} /> : null}
      </DialogContent>
    </Dialog>
  )
}

interface RenewFormProps {
  contract: ContractDetail
  onClose: () => void
  onRenewed: (contract: ContractDetailWithPermissions) => void
}

function RenewForm({ contract, onClose, onRenewed }: RenewFormProps) {
  const { t, i18n } = useTranslation()

  const form = useForm<RenewContractFormValues>({
    resolver: zodResolver(buildRenewContractSchema(t)),
    defaultValues: buildRenewalDefaults(contract),
  })

  const renewMutation = useRenewContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.renewDialog.success'))
      onRenewed(updated)
      onClose()
    },
  })

  const onSubmit = async (values: RenewContractFormValues) => {
    try {
      await renewMutation.mutateAsync(buildRenewPayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(apiErrorMessage(error, t('contracts.actions.renewDialog.genericError'), i18n))
      }
    }
  }

  return (
    <>
      <Form {...form}>
        <form
          id="contract-renew-form"
          className="flex flex-col gap-4"
          onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
        >
          <FormField
            control={form.control}
            name="expiry_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('contracts.actions.renewDialog.expiryDate')}</FormLabel>
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
                <FormLabel>{t('contracts.actions.renewDialog.renewalDate')}</FormLabel>
                <FormControl>
                  <Input type="date" value={field.value} onChange={field.onChange} onBlur={field.onBlur} name={field.name} ref={field.ref} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </form>
      </Form>

      <DialogFooter>
        <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" form="contract-renew-form" disabled={renewMutation.isPending}>
          {renewMutation.isPending ? t('contracts.actions.renewDialog.saving') : t('contracts.actions.renewDialog.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}
