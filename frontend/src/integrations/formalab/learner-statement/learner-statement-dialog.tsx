import { useTranslation } from 'react-i18next'
import { FileSpreadsheet, RefreshCw, Users } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { serverMessage } from '@/integrations/formalab/error-message'
import { CourseInfo } from '@/integrations/formalab/learner-statement/course-info'
import { LearnersTable } from '@/integrations/formalab/learner-statement/learners-table'
import { PartnersPanel } from '@/integrations/formalab/learner-statement/partners-panel'
import { ProgressStrip } from '@/integrations/formalab/learner-statement/progress-strip'
import { SummaryPanel } from '@/integrations/formalab/learner-statement/summary-panel'
import { useLearnerStatement } from '@/integrations/formalab/learner-statement/use-learner-statement'

interface LearnerStatementDialogProps {
  workOrderId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * "Prospetto Contabilità Discenti" (spec 0427). The content mounts only while
 * open, so nothing is read from Formalab until the user asks for it.
 */
export function LearnerStatementDialog({ workOrderId, open, onOpenChange }: LearnerStatementDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="xl" className="xl:max-w-7xl">
        {open ? <LearnerStatementContent workOrderId={workOrderId} /> : null}
      </DialogContent>
    </Dialog>
  )
}

function LearnerStatementContent({ workOrderId }: { workOrderId: number }) {
  const { t } = useTranslation('formalab')
  const { t: tc } = useTranslation()
  const controller = useLearnerStatement(workOrderId)
  const { query, refresh, commission, supplier, exportFile } = controller
  const statement = query.data
  const saving = commission.isPending || supplier.isPending

  return (
    <>
      <DialogHeader className="pr-8">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <DialogTitle>{t('learnerStatement.title')}</DialogTitle>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="bg-card"
            onClick={() => refresh.mutate()}
            disabled={refresh.isPending || query.isPending}
          >
            <RefreshCw className={refresh.isPending ? 'size-3.5 animate-spin' : 'size-3.5'} aria-hidden="true" />
            {t('learnerStatement.refresh')}
          </Button>
        </div>
        <DialogDescription>
          {statement
            ? t('learnerStatement.subtitle', { edition: statement.class?.code ?? statement.edition_id, hours: statement.class?.total_hours ?? '-' })
            : t('learnerStatement.loading')}
        </DialogDescription>
      </DialogHeader>

      <div className="flex max-h-[75vh] flex-col gap-4 overflow-y-auto">
        {query.isPending ? (
          <div role="status" aria-label={t('learnerStatement.loading')} className="flex flex-col gap-2">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-64 w-full" />
          </div>
        ) : query.isError || !statement ? (
          <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
            {serverMessage(query.error, t('learnerStatement.loadError'))}
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void query.refetch()}>
              {tc('common.retry')}
            </Button>
          </div>
        ) : (
          <>
            {statement.class ? <CourseInfo info={statement.class} /> : null}
            {statement.class_status === 'unavailable' ? (
              <p role="alert" className="rounded-md bg-warning/20 px-3 py-2 text-xs">
                <strong>{t('learnerStatement.unavailableTitle')}</strong> {t('learnerStatement.unavailable', { edition: statement.edition_id })}
              </p>
            ) : null}
            {statement.class_status === 'empty' ? (
              <p role="status" className="rounded-md bg-primary/10 px-3 py-2 text-xs">
                <strong>{t('learnerStatement.emptyTitle')}</strong> {t('learnerStatement.empty', { edition: statement.class?.code ?? statement.edition_id })}
              </p>
            ) : null}
            {statement.class_status === 'ok' ? (
              <div className="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_3fr]">
                <SummaryPanel statement={statement} />
                <div className="flex min-w-0 flex-col gap-4">
                  <section aria-labelledby="learner-statement-learners" className="flex min-w-0 flex-col gap-2">
                    <h3 id="learner-statement-learners" className="text-sm font-semibold">{t('learnerStatement.learners.title')}</h3>
                    <LearnersTable
                      statement={statement}
                      onCommission={(taxCode, payload) => commission.mutate({ taxCode, payload })}
                      saving={saving}
                    />
                  </section>
                  <PartnersPanel
                    statement={statement}
                    onScenario={controller.setScenario}
                    onSupplier={(partner, supplierId) => supplier.mutate({ partner, supplierId })}
                    saving={saving}
                  />
                </div>
              </div>
            ) : null}
            <ProgressStrip progress={statement.progress} />
          </>
        )}
      </div>

      <DialogFooter className="sm:justify-between">
        <DialogClose asChild>
          <Button type="button" variant="secondary" size="sm">{t('learnerStatement.close')}</Button>
        </DialogClose>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button type="button" variant="secondary" size="sm" disabled={!statement || exportFile.isPending} onClick={() => exportFile.mutate('partners')}>
            <Users className="size-3.5" aria-hidden="true" />
            {t('learnerStatement.export.partners')}
          </Button>
          <Button type="button" size="sm" disabled={!statement || exportFile.isPending} onClick={() => exportFile.mutate('full')}>
            <FileSpreadsheet className="size-3.5" aria-hidden="true" />
            {t('learnerStatement.export.full')}
          </Button>
        </div>
      </DialogFooter>
    </>
  )
}
