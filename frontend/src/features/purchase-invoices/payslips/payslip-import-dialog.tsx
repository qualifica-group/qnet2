import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PayslipImportSummary } from '@/features/purchase-invoices/payslips/payslip-import-summary'
import { usePayslipImport } from '@/features/purchase-invoices/payslips/use-payslip-import'

const ACCEPTED_FILES = '.csv,.txt,.xlsx'
const FILE_INPUT_ID = 'payslip-import-file'
const DATE_INPUT_ID = 'payslip-import-payment-date'

interface ImportBodyProps {
  onClose: () => void
  onApplied: () => void
}

function ImportBody({ onClose, onApplied }: ImportBodyProps) {
  const { t } = useTranslation()
  const importFlow = usePayslipImport(() => {
    onApplied()
    onClose()
  })
  const text = (key: string) => t(`purchaseInvoiceViews.payslipImport.${key}`)

  return (
    <>
      <DialogHeader>
        <DialogTitle>{text('title')}</DialogTitle>
        <DialogDescription>{text('description')}</DialogDescription>
      </DialogHeader>
      <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={FILE_INPUT_ID} className="text-xs">{text('file')}</Label>
            <Input
              id={FILE_INPUT_ID}
              type="file"
              accept={ACCEPTED_FILES}
              className="h-8 bg-card text-xs"
              onChange={(event: ChangeEvent<HTMLInputElement>) => importFlow.selectFile(event.target.files?.[0] ?? null)}
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={DATE_INPUT_ID} className="text-xs">{text('paymentDate')}</Label>
            <Input
              id={DATE_INPUT_ID}
              type="date"
              required
              className="h-8 bg-card text-xs"
              value={importFlow.paymentDate}
              onChange={(event: ChangeEvent<HTMLInputElement>) => importFlow.selectPaymentDate(event.target.value)}
            />
          </div>
        </div>
        {importFlow.isPreviewing ? (
          <p className="text-xs text-muted-foreground" role="status">{text('previewing')}</p>
        ) : null}
        {importFlow.requestError ? (
          <p className="text-xs text-destructive" role="alert">{importFlow.requestError}</p>
        ) : null}
        {importFlow.result ? <PayslipImportSummary result={importFlow.result} /> : null}
      </div>
      <DialogFooter>
        <Button variant="outline" size="sm" className="bg-card" onClick={onClose}>{text('cancel')}</Button>
        <Button size="sm" disabled={!importFlow.canConfirm} onClick={importFlow.confirm}>
          {importFlow.isApplying ? text('applying') : text('confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}

interface PayslipImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Called after a successful apply, before the dialog closes (refreshes the grid). */
  onApplied: () => void
}

/** File + payment date -> automatic preview -> confirm. The body unmounts on close, so every opening starts clean. */
export function PayslipImportDialog({ open, onOpenChange, onApplied }: PayslipImportDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="md" className="max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto]">
        <ImportBody onClose={() => onOpenChange(false)} onApplied={onApplied} />
      </DialogContent>
    </Dialog>
  )
}
