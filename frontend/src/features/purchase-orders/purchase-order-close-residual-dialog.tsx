import { useMemo, useState, type FormEvent } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import type { PurchaseOrderLine } from '@/features/purchase-orders/types'
import { useCloseResidual } from '@/features/purchase-orders/use-purchase-order-fulfillment'
import { fieldError, serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const REASON_MAX = 500

interface PurchaseOrderCloseResidualDialogProps {
  orderId: number
  line: PurchaseOrderLine
  onOpenChange: (open: boolean) => void
}

/**
 * "Chiudi residuo" (spec 0254 D-3): the missing goods will not arrive, so the
 * line counts as complete. The reason is required (max 500); a 409 (complete
 * or already closed line, over-invoiced) shows inside the dialog.
 */
export function PurchaseOrderCloseResidualDialog({ orderId, line, onOpenChange }: PurchaseOrderCloseResidualDialogProps) {
  const { t } = useTranslation()
  const close = useCloseResidual(orderId)
  const [formError, setFormError] = useState<string | null>(null)
  const schema = useMemo(
    () =>
      z.object({
        reason: z
          .string()
          .trim()
          .min(1, t('purchaseOrderActions.residual.errors.reasonRequired'))
          .max(REASON_MAX, t('purchaseOrderActions.residual.errors.reasonMax')),
      }),
    [t],
  )
  const form = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema), defaultValues: { reason: '' } })

  const save = form.handleSubmit(async ({ reason }) => {
    setFormError(null)
    try {
      await close.mutateAsync({ lineId: line.id, reason })
      onOpenChange(false)
    } catch (error) {
      const reasonError = fieldError(error, 'reason')
      if (reasonError) {
        form.setError('reason', { message: reasonError })
      } else {
        setFormError(serverMessage(error) ?? t('purchaseOrderActions.residual.closeError'))
      }
    }
  })

  /** The dialog lives inside the order form in the React tree: its submit must not reach the outer one. */
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.stopPropagation()
    return save(event)
  }

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{t('purchaseOrderActions.residual.closeTitle')}</DialogTitle>
          <DialogDescription>
            {t('purchaseOrderActions.residual.closeSubtitle', {
              position: line.position,
              description: line.description,
              quantity: Number(line.residual_quantity).toFixed(3),
            })}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form noValidate onSubmit={submit} className="flex flex-col gap-3">
            {formError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                {formError}
              </p>
            ) : null}
            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem className="gap-1">
                  <FormLabel className="text-xs" required>
                    {t('purchaseOrderActions.residual.reason')}
                  </FormLabel>
                  <FormControl>
                    <Textarea rows={3} className="min-h-0 text-xs" {...field} />
                  </FormControl>
                  <FormMessage className="text-xs" />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={close.isPending}>
                {t('purchaseOrderActions.residual.closeConfirm')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
