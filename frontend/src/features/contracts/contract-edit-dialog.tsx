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
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildEditContractSchema,
  type EditContractFormValues,
} from '@/features/contracts/contract-schema'
import { buildEditPayload, useUpdateContract } from '@/features/contracts/use-contract-mutations'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

const SERVER_ERROR_FIELDS = [
  'renewal_date',
  'expiry_date',
  'payment_notes',
  'comments',
] as const

interface ContractEditDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contract: ContractDetailWithPermissions
  onUpdated: (contract: ContractDetailWithPermissions) => void
}

/**
 * "Modifica dati" (PATCH `/contracts/{id}`, AC-039): the only dialog whose
 * fields go through the per-field authorization matrix (`MetaField`), since
 * it maps 1:1 onto `ContractResource.permissions.fields` — unlike the three
 * action endpoints, which have no field-permission ceiling of their own.
 *
 * The contract status is DELIBERATELY absent (user directive 2026-08-31):
 * the state is driven by the domain actions ("Valida" lands on "Validato",
 * "Disdici" on "Disdetto"), never edited by hand from here.
 */
export function ContractEditDialog({ open, onOpenChange, contract, onUpdated }: ContractEditDialogProps) {
  const { t } = useTranslation()
  const schema = buildEditContractSchema()

  const defaultValues: EditContractFormValues = {
    renewal_date: contract.renewal_date,
    expiry_date: contract.expiry_date,
    payment_notes: contract.payment_notes,
    comments: contract.comments,
  }

  const form = useForm<EditContractFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const updateMutation = useUpdateContract({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.actions.edit.success'))
      onUpdated({ ...contract, ...updated })
      onOpenChange(false)
    },
  })

  const onSubmit = async (values: EditContractFormValues) => {
    try {
      await updateMutation.mutateAsync(buildEditPayload(values))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(t('contracts.actions.edit.genericError'))
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
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('contracts.actions.edit.title')}</DialogTitle>
          <DialogDescription>{t('contracts.actions.edit.description')}</DialogDescription>
        </DialogHeader>

        <ResourcePermissionsProvider permissions={contract.permissions}>
          <Form {...form}>
            <form
              id="contract-edit-form"
              className="flex flex-col gap-4"
              onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            >
              <MetaField
                control={form.control}
                name="expiry_date"
                metaKey="expiry_date"
                label={t('contracts.actions.edit.expiryDate')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="date"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="renewal_date"
                metaKey="renewal_date"
                label={t('contracts.actions.edit.renewalDate')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="date"
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="payment_notes"
                metaKey="payment_notes"
                label={t('contracts.actions.edit.paymentNotes')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      rows={3}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField control={form.control} name="comments" metaKey="comments" label={t('contracts.actions.edit.comments')}>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      rows={3}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>
            </form>
          </Form>
        </ResourcePermissionsProvider>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="contract-edit-form" disabled={updateMutation.isPending}>
            {updateMutation.isPending ? t('contracts.actions.edit.saving') : t('contracts.actions.edit.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
