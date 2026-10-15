import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FilePlus2, FileSpreadsheet, ListOrdered, ReceiptText, Wallet } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { formatDate } from '@/lib/formatting/date-display'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { commissionSettlementKeys, fetchCommissionSettlement } from '@/features/commission-settlements/api'
import { CommissionAdjustmentSheet } from '@/features/commission-settlements/commission-adjustment-sheet'
import { CommissionSettlementActions } from '@/features/commission-settlements/commission-settlement-actions'
import { CommissionSettlementMovements } from '@/features/commission-settlements/commission-settlement-movements'
import { CommissionSettlementNotesRow } from '@/features/commission-settlements/commission-settlement-notes'
import { CommissionSettlementTotals } from '@/features/commission-settlements/commission-settlement-totals'
import { SettlementDocumentSection } from '@/features/commission-settlements/settlement-document-section'
import { SettlementPurchaseInvoiceSection } from '@/features/commission-settlements/settlement-purchase-invoice-section'
import { SettlementStatusBadge } from '@/features/commission-settlements/settlement-status-badge'
import { useSettlementDetailActions } from '@/features/commission-settlements/use-settlement-detail-actions'
import {
  COMMISSION_SETTLEMENTS_DOMAIN,
  type AdjustmentMode,
  type SettlementDetailWithPermissions,
  type SettlementMovement,
} from '@/features/commission-settlements/types'

interface DetailBodyProps {
  settlement: SettlementDetailWithPermissions
  onChanged: () => void
  onDeleted: () => void
}

/**
 * The settlement record (spec 0265): identity band with the status and the
 * actions the server allows, the header data with the notes edited in place,
 * the totals and the movements, an activity tab and a metadata footer.
 */
function DetailBody({ settlement, onChanged, onDeleted }: DetailBodyProps) {
  const { t } = useTranslation()
  const actions = useSettlementDetailActions({ settlementId: settlement.id, onChanged, onDeleted })
  const [adjustment, setAdjustment] = useState<AdjustmentMode | null>(null)

  const isDraft = settlement.status === 'DRAFT'
  const canAdjust = isDraft && settlement.abilities.adjust
  const draftMode: AdjustmentMode = {
    type: 'draft',
    settlementId: settlement.id,
    referent: settlement.referent,
    company: settlement.company,
  }
  const editMovement = (movement: SettlementMovement) => {
    if (movement.amount === undefined) {
      return
    }
    setAdjustment({
      type: 'edit',
      adjustmentId: movement.id,
      amount: movement.amount,
      accruedOn: movement.accrued_on,
      notes: movement.notes ?? '',
    })
  }
  const createdAt = formatDateTime(settlement.created_at)

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={
          settlement.permissions.actions.view_activity
            ? [activityLogTab(COMMISSION_SETTLEMENTS_DOMAIN, settlement.id, t('activityLog.title'))]
            : []
        }
        header={
          <RecordCardHeader
            media={<DetailMonogram name={settlement.referent.name} icon={<FileSpreadsheet />} />}
            title={
              settlement.code
                ? t('commissionSettlements.detail.title', { code: settlement.code })
                : t('commissionSettlements.detail.draftTitle')
            }
            subtitle={`${settlement.referent.name} - ${settlement.company.name}`}
            badges={<SettlementStatusBadge status={settlement.status} />}
            actions={<CommissionSettlementActions settlement={settlement} actions={actions} onChanged={onChanged} />}
          />
        }
      >
        {/* Provider only (no DOM): the notes row reads the field permissions. */}
        <ResourcePermissionsProvider permissions={settlement.permissions}>
          <RecordSectionsGrid>
            <RecordSection title={t('commissionSettlements.detail.header')} icon={<ReceiptText />}>
              <RecordFieldList>
                <RecordField label={t('commissionSettlements.detail.period')}>
                  {t('commissionSettlements.detail.periodRange', {
                    from: formatDate(settlement.period_from),
                    to: formatDate(settlement.period_to),
                  })}
                </RecordField>
                <RecordField label={t('commissionSettlements.columns.issued_at')}>
                  {settlement.issued_at ? formatDateTime(settlement.issued_at) : <DetailEmpty />}
                </RecordField>
                {settlement.issued_by ? (
                  <RecordField label={t('commissionSettlements.detail.issuedBy')}>{settlement.issued_by.name}</RecordField>
                ) : null}
                {settlement.approved_at ? (
                  <RecordField label={t('commissionSettlements.detail.approvedAt')}>
                    {formatDateTime(settlement.approved_at)}
                    {settlement.approved_by ? ` - ${settlement.approved_by.name}` : ''}
                  </RecordField>
                ) : null}
                {settlement.supplier ? (
                  <RecordField label={t('commissionSettlements.detail.supplier')}>{settlement.supplier.name}</RecordField>
                ) : null}
                {settlement.status === 'CANCELLED' ? (
                  <>
                    <RecordField label={t('commissionSettlements.detail.cancelledAt')}>
                      {settlement.cancelled_at ? formatDateTime(settlement.cancelled_at) : <DetailEmpty />}
                    </RecordField>
                    <RecordField label={t('commissionSettlements.detail.cancellationReason')}>
                      {settlement.cancellation_reason ?? <DetailEmpty />}
                    </RecordField>
                  </>
                ) : null}
                <CommissionSettlementNotesRow
                  notes={settlement.notes}
                  editable={isDraft && settlement.abilities.update}
                  onSave={actions.saveNotes}
                />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('commissionSettlements.totals.label')} icon={<Wallet />}>
              <CommissionSettlementTotals settlement={settlement} />
            </RecordSection>
            {settlement.document ? <SettlementDocumentSection document={settlement.document} /> : null}
            {settlement.purchase_invoice ? (
              <SettlementPurchaseInvoiceSection invoice={settlement.purchase_invoice} onChanged={onChanged} />
            ) : null}
            <RecordSection
              title={t('commissionSettlements.movements.title')}
              icon={<ListOrdered />}
              full
              action={
                canAdjust ? (
                  <Button variant="secondary" size="xs" disabled={actions.isBusy} onClick={() => setAdjustment(draftMode)}>
                    <FilePlus2 aria-hidden="true" />
                    {t('commissionSettlements.actions.newAdjustment')}
                  </Button>
                ) : null
              }
            >
              <CommissionSettlementMovements
                movements={settlement.movements}
                canManageAdjustments={canAdjust}
                onEdit={editMovement}
                onDelete={(movement) => actions.removeAdjustment(movement.id)}
              />
            </RecordSection>
          </RecordSectionsGrid>
        </ResourcePermissionsProvider>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('commissionSettlements.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
      <CommissionAdjustmentSheet mode={adjustment} onClose={() => setAdjustment(null)} onSaved={onChanged} />
    </RecordCanvas>
  )
}

interface CommissionSettlementDetailViewProps {
  settlementId: number
  /** Called after any change so the caller refreshes its grid. */
  onChanged: () => void
  /** Called after the draft was deleted (close the sheet / go back to the list). */
  onDeleted: () => void
}

/** Full view of a settlement: header, D-2 totals, notes, movements and the actions allowed by `abilities`. */
export function CommissionSettlementDetailView({ settlementId, onChanged, onDeleted }: CommissionSettlementDetailViewProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useEntityDetail(
    commissionSettlementKeys.detail(settlementId),
    () => fetchCommissionSettlement(settlementId),
  )

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('commissionSettlements.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }
  if (isLoading || !data) {
    return <DetailLoading />
  }
  return <DetailBody settlement={data} onChanged={onChanged} onDeleted={onDeleted} />
}
