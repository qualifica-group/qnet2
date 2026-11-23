import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { formatDateTime } from '@/lib/formatting/date-display'
import type { FormalabStatus, FormalabSyncOutcome } from '@/integrations/formalab/types'

const OUTCOME_VARIANT = {
  running: 'secondary',
  success: 'default',
  failure: 'destructive',
} as const satisfies Record<FormalabSyncOutcome, 'secondary' | 'default' | 'destructive'>

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-3 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="min-w-0 truncate text-right">{children}</dd>
    </div>
  )
}

/** Configuration, last sync and 24h traffic of the integration, as returned by the status endpoint. */
export function StatusCard({ status }: { status: FormalabStatus }) {
  const { t } = useTranslation('formalab')
  const { last_sync: lastSync, calls_last_24h: calls, unlinked_work_orders: unlinked } = status
  const yesNo = (value: boolean) => (value ? t('status.yes') : t('status.no'))

  return (
    <div className="flex flex-col gap-3">
      {status.missing_course_fields.length > 0 ? (
        <Card role="alert" className="gap-1 border-warning p-3">
          <p className="text-sm font-semibold">{t('status.missingCourseFieldsTitle')}</p>
          <p className="text-xs text-muted-foreground">{t('status.missingCourseFieldsDescription')}</p>
          <p className="text-xs break-words">{status.missing_course_fields.join(', ')}</p>
        </Card>
      ) : null}
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Card className="gap-2 p-4">
          <h2 className="text-base font-semibold">{t('status.configuration')}</h2>
          <dl className="flex flex-col gap-1.5">
            <Row label={t('status.baseUrl')}>{status.base_url ?? t('status.none')}</Row>
            <Row label={t('status.credentials')}>{yesNo(status.credentials_configured)}</Row>
            <Row label={t('status.inbound')}>{yesNo(status.inbound_enabled)}</Row>
            <Row label={t('status.interval')}>{t('status.intervalValue', { minutes: status.sync_interval_minutes })}</Row>
            <Row label={t('status.classes')}>{status.classes_count}</Row>
            <Row label={t('status.linkedWorkOrders')}>{status.linked_work_orders_count}</Row>
          </dl>
        </Card>

        <Card className="gap-2 p-4">
          <h2 className="text-base font-semibold">{t('status.lastSync')}</h2>
          {lastSync ? (
            <dl className="flex flex-col gap-1.5">
              <Row label={t('status.outcome')}>
                <Badge variant={OUTCOME_VARIANT[lastSync.outcome]}>{t(`status.outcomes.${lastSync.outcome}`)}</Badge>
              </Row>
              <Row label={t('status.trigger')}>{t(`status.triggers.${lastSync.trigger}`)}</Row>
              <Row label={t('status.startedAt')}>{formatDateTime(lastSync.started_at)}</Row>
              <Row label={t('status.finishedAt')}>
                {lastSync.finished_at ? formatDateTime(lastSync.finished_at) : t('status.none')}
              </Row>
              <Row label={t('status.classesSynced')}>{lastSync.classes_synced}</Row>
              <Row label={t('status.workOrdersUpdated')}>{lastSync.work_orders_updated}</Row>
              {lastSync.error_message ? (
                <p role="alert" className="text-xs text-destructive">
                  {lastSync.error_message}
                </p>
              ) : null}
              {lastSync.warning_message ? <p className="text-xs text-warning">{lastSync.warning_message}</p> : null}
            </dl>
          ) : (
            <p className="text-sm text-muted-foreground">{t('status.neverSynced')}</p>
          )}
        </Card>

        <Card className="gap-2 p-4">
          <h2 className="text-base font-semibold">{t('status.calls24h')}</h2>
          <dl className="flex flex-col gap-1.5">
            <Row label={t('status.callsTotal')}>{calls.total}</Row>
            <Row label={t('status.callsFailures')}>{calls.failures}</Row>
            <Row label={t('status.callsInbound')}>{calls.inbound}</Row>
            <Row label={t('status.callsOutbound')}>{calls.outbound}</Row>
          </dl>
        </Card>

        <Card className="gap-2 p-4">
          <h2 className="text-base font-semibold">{t('status.unlinked')}</h2>
          <p className="text-xs text-muted-foreground">{t('status.unlinkedDescription')}</p>
          <dl className="flex flex-col gap-1.5">
            <Row label={t('status.unlinkedUnmatched')}>{unlinked.unmatched}</Row>
            <Row label={t('status.unlinkedWithoutCourseName')}>{unlinked.without_course_name}</Row>
            <Row label={t('status.unlinkedLinkable')}>{unlinked.linkable}</Row>
          </dl>
        </Card>
      </div>
    </div>
  )
}
