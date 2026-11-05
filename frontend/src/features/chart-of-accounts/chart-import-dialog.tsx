import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ChartImportSummary } from '@/features/chart-of-accounts/chart-import-summary'
import { useChartImport } from '@/features/chart-of-accounts/use-chart-import'

const ACCEPTED_FILES = '.xlsx,.csv'
const FILE_INPUT_ID = 'chart-import-file'

function ImportBody({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const importFlow = useChartImport(onClose)

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('chartOfAccounts.import.title')}</DialogTitle>
        <DialogDescription>{t('chartOfAccounts.import.description')}</DialogDescription>
      </DialogHeader>
      <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={FILE_INPUT_ID} className="text-xs">
            {t('chartOfAccounts.import.file')}
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
            {t('chartOfAccounts.import.previewing')}
          </p>
        ) : null}
        {importFlow.requestError ? (
          <p className="text-xs text-destructive" role="alert">
            {importFlow.requestError}
          </p>
        ) : null}
        {importFlow.result ? <ChartImportSummary result={importFlow.result} /> : null}
      </div>
      <DialogFooter>
        <Button variant="outline" size="sm" className="bg-card" onClick={onClose}>
          {t('chartOfAccounts.form.cancel')}
        </Button>
        <Button size="sm" disabled={!importFlow.canConfirm} onClick={importFlow.confirm}>
          {importFlow.isApplying ? t('chartOfAccounts.import.applying') : t('chartOfAccounts.import.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}

interface ChartImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** File -> automatic preview -> confirm. The body unmounts on close, so every opening starts clean. */
export function ChartImportDialog({ open, onOpenChange }: ChartImportDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="md" className="max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto]">
        <ImportBody onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}
