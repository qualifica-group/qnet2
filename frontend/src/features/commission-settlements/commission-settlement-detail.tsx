import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileSpreadsheet } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { formatDate } from '@/lib/formatting/date-display'
import { formatDateTime } from '@/features/table/cell-renderers'
import { commissionSettlementKeys, fetchCommissionSettlement } from '@/features/commission-settlements/api'
import { CommissionAdjustmentDialog } from '@/features/commission-settlements/commission-adjustment-dialog'
import { CommissionSettlementActions } from '@/features/commission-settlements/commission-settlement-actions'
import { CommissionSettlementMovements } from '@/features/commission-settlements/commission-settlement-movements'
import { CommissionSettlementNotes } from '@/features/commission-settlements/commission-settlement-notes'
import { CommissionSettlementTotals } from '@/features/commission-settlements/commission-settlement-totals'
import { SettlementStatusBadge } from '@/features/commission-settlements/settlement-status-badge'
import { useSettlementDetailActions } from '@/features/commission-settlements/use-settlement-detail-actions'
import type {
  AdjustmentMode,
  SettlementDetailWithPermissions,
  SettlementMovement,
} from '@/features/commission-settlements/types'

interface DetailBodyProps {
  settlement: SettlementDetailWithPermissions
  onChanged: () => void
  onDeleted: () => void
}

function DetailBody({ settlement, onChanged, onDeleted }: DetailBodyProps) {
  const { t } = useTranslation()
  const actions = useSettlementDetailActions({ settlementId: settlement.id, onChanged, onDeleted })
  const [adjustment, setAdjustment] = useState<AdjustmentMode | null>(null)

  const isDraft = settlement.status === 'DRAFT'
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

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={settlement.referent.name} icon={<FileSpreadsheet />} />}
          title={
            settlement.code
              ? t('commissionSettlements.detail.title', { code: settlement.code })
              : t('commissionSettlements.detail.draftTitle')
          }
          subtitle={`${settlement.referent.name} - ${settlement.company.name}`}
          badges={<SettlementStatusBadge status={settlement.status} />}
        />
        <div className="border-b p-4">
          <CommissionSettlementActions
            abilities={settlement.abilities}
            actions={actions}
            onAdjust={() => setAdjustment(draftMode)}
          />
        </div>
        <RecordSectionsGrid>
          <RecordSection title={t('commissionSettlements.detail.header')}>
            <RecordFieldList>
              <RecordField label={t('commissionSettlements.columns.period_from')}>{formatDate(settlement.period_from)}</RecordField>
              <RecordField label={t('commissionSettlements.columns.period_to')}>{formatDate(settlement.period_to)}</RecordField>
              <RecordField label={t('commissionSettlements.columns.issued_at')}>
                {settlement.issued_at ? formatDateTime(settlement.issued_at) : <DetailEmpty />}
              </RecordField>
              {settlement.issued_by ? (
                <RecordField label={t('commissionSettlements.detail.issuedBy')}>{settlement.issued_by.name}</RecordField>
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
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('commissionSettlements.totals.label')}>
            <CommissionSettlementTotals settlement={settlement} />
          </RecordSection>
          <RecordSection title={t('commissionSettlements.notes.label')} full>
            <CommissionSettlementNotes
              key={settlement.id}
              notes={settlement.notes}
              editable={isDraft && settlement.abilities.update}
              isSaving={actions.isSavingNotes}
              onSave={actions.saveNotes}
            />
          </RecordSection>
        </RecordSectionsGrid>
        <div className="flex flex-col gap-2 border-t p-4">
          <h3 className="text-sm font-semibold">{t('commissionSettlements.movements.title')}</h3>
          <CommissionSettlementMovements
            movements={settlement.movements}
            canManageAdjustments={isDraft && settlement.abilities.adjust}
            onEdit={editMovement}
            onDelete={(movement) => actions.removeAdjustment(movement.id)}
          />
        </div>
      </RecordCard>
      <CommissionAdjustmentDialog mode={adjustment} onClose={() => setAdjustment(null)} onSaved={onChanged} />
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
