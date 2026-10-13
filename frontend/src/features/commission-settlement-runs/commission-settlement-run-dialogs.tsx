import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { commissionSettlementRunKeys, fetchCommissionSettlementRun } from '@/features/commission-settlement-runs/api'
import { CommissionSettlementRunForm } from '@/features/commission-settlement-runs/commission-settlement-run-form'
import { CommissionSettlementRunResultView } from '@/features/commission-settlement-runs/commission-settlement-run-result'
import type { CommissionSettlementRunResult } from '@/features/commission-settlement-runs/types'

interface NewRunDialogProps {
  open: boolean
  onClose: () => void
  /** Called once the run has been executed, so the caller refreshes its grid. */
  onLaunched: () => void
}

/** "Nuovo lancio": the form first, then the outcome of the run in the same dialog. */
export function CommissionSettlementRunDialog({ open, onClose, onLaunched }: NewRunDialogProps) {
  const { t } = useTranslation()
  const [result, setResult] = useState<CommissionSettlementRunResult | null>(null)

  const handleCreated = (created: CommissionSettlementRunResult) => {
    setResult(created)
    onLaunched()
  }

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setResult(null)
      onClose()
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent size="lg" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">
            {result ? t('commissionSettlementRuns.result.title') : t('commissionSettlementRuns.form.title')}
          </DialogTitle>
          <DialogDescription className="text-xs">
            {result ? t('commissionSettlementRuns.result.description') : t('commissionSettlementRuns.form.description')}
          </DialogDescription>
        </DialogHeader>
        {result ? (
          <>
            <CommissionSettlementRunResultView result={result} />
            <div className="flex justify-end border-t px-4 py-3">
              <Button size="sm" onClick={() => handleOpenChange(false)}>
                {t('common.close')}
              </Button>
            </div>
          </>
        ) : (
          <CommissionSettlementRunForm onCreated={handleCreated} onCancel={() => handleOpenChange(false)} />
        )}
      </DialogContent>
    </Dialog>
  )
}

interface RunDetailDialogProps {
  runId: number | null
  onClose: () => void
}

function RunDetailBody({ runId }: { runId: number }) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useQuery({
    queryKey: commissionSettlementRunKeys.detail(runId),
    queryFn: () => fetchCommissionSettlementRun(runId),
  })

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('commissionSettlementRuns.detail.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!data) {
    return (
      <div className="flex flex-col gap-3 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }
  return <CommissionSettlementRunResultView result={data} />
}

/** Opening a run row shows the same outcome view, loaded from GET /commission-settlement-runs/{id}. */
export function CommissionSettlementRunDetailDialog({ runId, onClose }: RunDetailDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={runId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="lg" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('commissionSettlementRuns.result.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('commissionSettlementRuns.result.description')}</DialogDescription>
        </DialogHeader>
        {runId !== null ? <RunDetailBody runId={runId} /> : null}
      </DialogContent>
    </Dialog>
  )
}
