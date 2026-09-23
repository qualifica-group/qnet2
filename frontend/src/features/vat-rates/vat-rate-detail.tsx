import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
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
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatRate } from '@/features/vat-rates/column-renderers'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'

interface VatRateDetailViewProps {
  vatRate: VatRateDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single VAT rate, rendered as an enterprise-CRM
 * record (Opportunita' reference layout): the identity/fields card on the
 * left, the activity card on the right, a metadata footer.
 */
export function VatRateDetailView({ vatRate, onEdit }: VatRateDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = vatRate.permissions.resource.update
  const createdAt = formatDateTime(vatRate.created_at)
  const canViewActivity = vatRate.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('vat-rates', vatRate.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={vatRate.name}
                icon={<Percent />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={vatRate.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('vatRates.detail.details')} full>
              <RecordFieldList>
                <RecordField label={t('vatRates.columns.rate')}>{formatRate(vatRate.rate)}%</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('vatRates.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
