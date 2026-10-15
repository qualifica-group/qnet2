import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CircleSlash, FileCheck2, Play, Settings2 } from 'lucide-react'
import { DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatDateTime } from '@/features/table/cell-renderers'
import { commissionSettlementRunKeys, fetchCommissionSettlementRun } from '@/features/commission-settlement-runs/api'
import { RunCreatedTable, RunSkippedTable } from '@/features/commission-settlement-runs/commission-settlement-run-result'
import {
  COMMISSION_SETTLEMENT_RUNS_DOMAIN,
  type CommissionSettlementRunResult,
} from '@/features/commission-settlement-runs/types'

/** A run as a record (spec 0265 D-3, also right after a launch): who launched it and how, then what it created and what it skipped. */
export function RunRecord({ result }: { result: CommissionSettlementRunResult }) {
  const { t } = useTranslation()
  const { run, created, skipped } = result
  const period = t('commissionSettlementRuns.detail.periodRange', {
    from: formatDate(run.period_from),
    to: formatDate(run.period_to),
  })

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={period} icon={<Play />} />}
          title={t('commissionSettlementRuns.detail.title', { period })}
          subtitle={t('commissionSettlementRuns.detail.subtitle', {
            name: run.created_by.name,
            date: formatDateTime(run.created_at),
          })}
        />
        <RecordSectionsGrid>
          <RecordSection title={t('commissionSettlementRuns.detail.launch')} icon={<Settings2 />} full>
            <RecordFieldList>
              <RecordField label={t('commissionSettlementRuns.detail.period')}>{period}</RecordField>
              <RecordField label={t('commissionSettlementRuns.form.minAmount')}>{formatEuro(run.min_amount)}</RecordField>
              <RecordField label={t('commissionSettlementRuns.detail.outcome')}>
                {t('commissionSettlementRuns.detail.outcomeCounts', { created: created.length, skipped: skipped.length })}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
          <RecordSection
            title={t('commissionSettlementRuns.result.createdTitle', { total: created.length })}
            icon={<FileCheck2 />}
            full
          >
            <RunCreatedTable created={created} />
          </RecordSection>
          <RecordSection
            title={t('commissionSettlementRuns.result.skippedTitle', { total: skipped.length })}
            icon={<CircleSlash />}
            full
          >
            <RunSkippedTable skipped={skipped} />
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCard>
    </RecordCanvas>
  )
}

function RunRecordLoader({ runId }: { runId: number }) {
  const { t } = useTranslation()
  const { data, isError, error, refetch } = useQuery({
    queryKey: commissionSettlementRunKeys.detail(runId),
    queryFn: () => fetchCommissionSettlementRun(runId),
  })

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('commissionSettlementRuns.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => void refetch()}
      />
    )
  }
  if (!data) {
    return <DetailLoading />
  }
  return <RunRecord result={data} />
}

interface CommissionSettlementRunDetailSheetProps {
  runId: number | null
  onClose: () => void
}

/** "Visualizza" row action: the run in a resizable side sheet, like every other record, without leaving the list. */
export function CommissionSettlementRunDetailSheet({ runId, onClose }: CommissionSettlementRunDetailSheetProps) {
  const { t } = useTranslation()
  return (
    <Sheet open={runId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${COMMISSION_SETTLEMENT_RUNS_DOMAIN}`}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t('commissionSettlementRuns.result.title')}</SheetTitle>
          <SheetDescription>{t('commissionSettlementRuns.result.description')}</SheetDescription>
        </SheetHeader>
        {runId !== null ? <RunRecordLoader runId={runId} /> : null}
      </SheetContent>
    </Sheet>
  )
}
