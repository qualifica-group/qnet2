import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PayrollImportSummary } from '@/features/payroll-costs/payroll-import-summary'
import { usePayrollImport } from '@/features/payroll-costs/use-payroll-import'

const ACCEPTED_FILES = '.csv,.txt,.xlsx'
const FILE_INPUT_ID = 'payroll-import-file'

interface ImportBodyProps {
  onClose: () => void
  onApplied: () => void
}

function ImportBody({ onClose, onApplied }: ImportBodyProps) {
  const { t } = useTranslation()
  const importFlow = usePayrollImport(() => {
    onApplied()
    onClose()
  })

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('payrollCosts.import.title')}</DialogTitle>
        <DialogDescription>{t('payrollCosts.import.description')}</DialogDescription>
      </DialogHeader>
      <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={FILE_INPUT_ID} className="text-xs">
            {t('payrollCosts.import.file')}
          </Label>
          <Input
            id={FILE_INPUT_ID}
            type="file"
            accept={ACCEPTED_FILES}
            className="h-8 bg-card text-xs"
            onChange={(event: ChangeEvent<HTMLInputElement>) => importFlow.selectFile(event.target.files?.[0] ?? null)}
          />
        </div>
        {importFlow.isPreviewing ? (
          <p className="text-xs text-muted-foreground" role="status">
            {t('payrollCosts.import.previewing')}
          </p>
        ) : null}
        {importFlow.requestError ? (
          <p className="text-xs text-destructive" role="alert">
            {importFlow.requestError}
          </p>
        ) : null}
        {importFlow.result ? <PayrollImportSummary result={importFlow.result} /> : null}
      </div>
      <DialogFooter>
        <Button variant="outline" size="sm" className="bg-card" onClick={onClose}>
          {t('payrollCosts.import.cancel')}
        </Button>
        <Button size="sm" disabled={!importFlow.canConfirm} onClick={importFlow.confirm}>
          {importFlow.isApplying ? t('payrollCosts.import.applying') : t('payrollCosts.import.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}

interface PayrollImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Called after a successful apply, before the dialog closes (refreshes the grid). */
  onApplied: () => void
}

/** File -> automatic preview -> confirm. The body unmounts on close, so every opening starts clean. */
export function PayrollImportDialog({ open, onOpenChange, onApplied }: PayrollImportDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="md" className="max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto]">
        <ImportBody onClose={() => onOpenChange(false)} onApplied={onApplied} />
      </DialogContent>
    </Dialog>
  )
}
