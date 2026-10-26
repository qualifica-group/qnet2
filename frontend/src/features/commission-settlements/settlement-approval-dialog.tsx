import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { PurchaseInvoiceEditorInstallments } from '@/features/purchase-invoices/purchase-invoice-editor-installments'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { ForSelectFormField } from '@/features/commission-settlements/for-select-form-field'
import { SettlementApprovalSupplier } from '@/features/commission-settlements/settlement-approval-supplier'
import type { ApprovalDraft, SettlementDetail } from '@/features/commission-settlements/types'
import { useApprovalDraft, useSettlementApproval } from '@/features/commission-settlements/use-settlement-approval'

interface ApprovalFormProps {
  settlement: SettlementDetail
  draft: ApprovalDraft
  onChanged: () => void
  onClose: () => void
}

function ApprovalForm({ settlement, draft, onChanged, onClose }: ApprovalFormProps) {
  const { t } = useTranslation()
  const { form, submit, preview, serverError, isPending } = useSettlementApproval(settlement, draft, onChanged, onClose)
  const placeholder = t('commissionSettlements.approvalDialog.select')

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <p className="text-xs text-muted-foreground">{draft.line_description}</p>
        <SettlementApprovalSupplier draft={draft} control={form.control} />
        <div className="grid gap-3 sm:grid-cols-2">
          <ForSelectFormField
            control={form.control}
            name="payment_method_id"
            resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
            label={t('commissionSettlements.approvalDialog.paymentMethod')}
            placeholder={placeholder}
            required
            quickCreate
          />
          <ForSelectFormField
            control={form.control}
            name="cost_type_id"
            resource={COST_TYPES_FOR_SELECT_RESOURCE}
            label={t('commissionSettlements.approvalDialog.costType')}
            placeholder={placeholder}
            required
            quickCreate
          />
          <ForSelectFormField
            control={form.control}
            name="vat_rate_id"
            resource={VAT_RATES_FOR_SELECT_RESOURCE}
            label={t('commissionSettlements.approvalDialog.vatRate')}
            placeholder={placeholder}
            required
            quickCreate
          />
          <ForSelectFormField
            control={form.control}
            name="financial_account_id"
            resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
            label={t('commissionSettlements.approvalDialog.financialAccount')}
            placeholder={placeholder}
            quickCreate
          />
        </div>
        <PurchaseInvoiceEditorInstallments preview={preview} />
        {serverError ? (
          <p role="alert" className="text-xs text-destructive">
            {serverError}
          </p>
        ) : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t('commissionSettlements.approvalDialog.confirm')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface SettlementApprovalDialogProps {
  open: boolean
  settlement: SettlementDetail
  onChanged: () => void
  onClose: () => void
}

/** "Approva": addresses the passive invoice to the agent's supplier registry and creates it with its installments. */
export function SettlementApprovalDialog({ open, settlement, onChanged, onClose }: SettlementApprovalDialogProps) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useApprovalDraft(settlement.id, open)

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('commissionSettlements.approvalDialog.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('commissionSettlements.approvalDialog.description')}</DialogDescription>
        </DialogHeader>
        {isError ? (
          <div role="alert" className="flex flex-col gap-2 p-4 text-xs text-destructive">
            {t('commissionSettlements.approvalDialog.loadError')}
            <Button type="button" variant="outline" size="sm" className="w-fit bg-card" onClick={() => void refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : open && data ? (
          <ApprovalForm settlement={settlement} draft={data} onChanged={onChanged} onClose={onClose} />
        ) : (
          <p className="p-4 text-xs text-muted-foreground">{t('commissionSettlements.approvalDialog.loading')}</p>
        )}
      </DialogContent>
    </Dialog>
  )
}
