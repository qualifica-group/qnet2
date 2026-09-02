import { useTranslation } from 'react-i18next'
import { Boxes, ClipboardList, FileText, Hammer, History, Lock } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { BADGE_BASE, BADGE_COLOR_CLASSES, formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { WorkOrderDetailWithPermissions, WorkOrderQuoteLine, WorkOrderType } from '@/features/work-orders/types'

const WORK_ORDERS_DOMAIN = 'work-orders'

/** Colored status pill for the record header (D-3: calculated, sola lettura). */
function WorkOrderStatusBadge({ isClosed }: { isClosed: boolean }) {
  const { t } = useTranslation()
  return (
    <Badge
      variant="secondary"
      className={cn(BADGE_BASE, 'gap-1.5', isClosed ? BADGE_COLOR_CLASSES.slate : BADGE_COLOR_CLASSES.green)}
    >
      <span
        className={cn('size-1.5 shrink-0 rounded-full', isClosed ? 'bg-slate-500' : 'bg-green-500')}
        aria-hidden="true"
      />
      {t(isClosed ? 'workOrders.detail.statusClosed' : 'workOrders.detail.statusOpen')}
    </Badge>
  )
}

/** "Tipo commessa" pill for the record header (D-10). */
function WorkOrderTypeBadge({ type }: { type: WorkOrderType }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, BADGE_COLOR_CLASSES.blue)}>
      {t(`workOrders.options.type.${type}`)}
    </Badge>
  )
}

/** Read-only list of the linked offer's REVENUE lines (D-6/D-7), ordered like the picker. */
function WorkOrderLinesList({ lines }: { lines: WorkOrderQuoteLine[] }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('workOrders.detail.linesEmpty')}</p>
  }

  const sorted = [...lines].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <ul className="flex flex-col divide-y divide-border/60 rounded-lg border bg-surface text-sm">
      {sorted.map((line) => (
        <li key={line.id} className="flex items-center gap-2 px-3 py-2">
          {line.product ? (
            <>
              <span className="font-mono text-xs text-muted-foreground">{line.product.code}</span>
              <span className="truncate">{line.product.name}</span>
            </>
          ) : (
            <DetailEmpty />
          )}
        </li>
      ))}
    </ul>
  )
}

interface WorkOrderDetailViewProps {
  workOrder: WorkOrderDetailWithPermissions
}

/**
 * Read-only detail of a single work order (AC-075), on the same
 * `RecordCanvas` kit as the Contract/Offer records: identity header (status +
 * type badges), titled sections for the offer link and its product lines,
 * the closure reason when force-closed, description/notes, activity log when
 * granted, a metadata footer below.
 */
export function WorkOrderDetailView({ workOrder }: WorkOrderDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(workOrder.created_at)
  const updatedAt = formatDateTime(workOrder.updated_at)

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={workOrder.title} icon={<Hammer />} />}
          title={workOrder.title}
          subtitle={workOrder.code}
          badges={
            <>
              <WorkOrderStatusBadge isClosed={workOrder.status.value === 'closed'} />
              <WorkOrderTypeBadge type={workOrder.type} />
            </>
          }
        />

        <RecordSectionsGrid>
          <RecordSection title={t('workOrders.detail.sections.identity')} icon={<ClipboardList />}>
            <RecordFieldList>
              <RecordField label={t('workOrders.detail.type')}>
                {t(`workOrders.options.type.${workOrder.type}`)}
              </RecordField>
              <RecordField label={t('workOrders.detail.status')}>
                {t(workOrder.status.value === 'closed' ? 'workOrders.detail.statusClosed' : 'workOrders.detail.statusOpen')}
              </RecordField>
              <RecordField label={t('workOrders.detail.callbackDate')}>
                {formatDate(workOrder.callback_date) || <DetailEmpty />}
              </RecordField>
              <RecordField label={t('workOrders.detail.contractNumber')}>
                {workOrder.contract_number ?? <DetailEmpty />}
              </RecordField>
              {workOrder.is_force_closed ? (
                <RecordField label={t('workOrders.detail.forceCloseReason')} icon={<Lock />}>
                  <span className="whitespace-pre-wrap">{workOrder.force_close_reason}</span>
                </RecordField>
              ) : null}
            </RecordFieldList>
          </RecordSection>

          <RecordSection title={t('workOrders.detail.sections.offer')} icon={<Boxes />}>
            <RecordFieldList>
              <RecordField label={t('workOrders.detail.quote')}>
                {workOrder.quote ? workOrder.quote.title : <DetailEmpty />}
              </RecordField>
            </RecordFieldList>
            <div className="mt-3">
              <WorkOrderLinesList lines={workOrder.quote_lines} />
            </div>
          </RecordSection>

          <RecordSection title={t('workOrders.detail.sections.notes')} icon={<FileText />} full>
            <RecordFieldList>
              <RecordField label={t('workOrders.detail.description')}>
                {workOrder.description ? (
                  <span className="whitespace-pre-wrap">{workOrder.description}</span>
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
              <RecordField label={t('workOrders.detail.internalNotes')}>
                {workOrder.internal_notes ? (
                  <span className="whitespace-pre-wrap">{workOrder.internal_notes}</span>
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>

        {workOrder.permissions.actions.view_activity ? (
          <div className="border-t p-4">
            <RecordSection title={t('activityLog.title')} icon={<History />}>
              <ActivityLogSection resource={WORK_ORDERS_DOMAIN} id={workOrder.id} />
            </RecordSection>
          </div>
        ) : null}
      </RecordCard>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('workOrders.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('workOrders.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
