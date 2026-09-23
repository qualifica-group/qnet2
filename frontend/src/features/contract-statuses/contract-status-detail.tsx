import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
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
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'

interface ContractStatusDetailViewProps {
  contractStatus: ContractStatusDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single contract status, rendered as an
 * enterprise-CRM record (Opportunita' reference layout): the
 * identity/fields card on the left, the activity card on the right, a
 * metadata footer.
 */
export function ContractStatusDetailView({ contractStatus, onEdit }: ContractStatusDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = contractStatus.permissions.resource.update
  const createdAt = formatDateTime(contractStatus.created_at)
  const swatch = swatchClassFor(contractStatus.color)
  const canViewActivity = contractStatus.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('contract-statuses', contractStatus.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={contractStatus.name}
                icon={<Flag />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={contractStatus.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('contractStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('contractStatuses.detail.description')}>
                  {contractStatus.description ? contractStatus.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('contractStatuses.detail.color')}>
                  {contractStatus.color ? (
                    <span className="flex items-center gap-2">
                      <span
                        className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                        aria-hidden="true"
                      />
                      {t(`customFields.colors.${contractStatus.color}`)}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('contractStatuses.detail.sort_order')}>
                  {contractStatus.sort_order}
                </RecordField>
                <RecordField label={t('contractStatuses.detail.group')}>
                  {t(`contractStatuses.form.group.${contractStatus.group}`)}
                </RecordField>
                <RecordField label={t('contractStatuses.detail.isActive')}>
                  {contractStatus.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('contractStatuses.detail.isDefault')}>
                  {contractStatus.is_default ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('contractStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
