import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { RewardStatusRows } from '@/features/reward-statuses/reward-status-rows'
import { useRewardStatusForm, type RewardStatusFormValues } from '@/features/reward-statuses/use-reward-status-form'
import type { RewardStatusDetailWithPermissions, RewardStatusFormMode } from '@/features/reward-statuses/types'

interface RewardStatusDetailViewProps {
  rewardStatus: RewardStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single reward status on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata
 * footer. There is no edit page (spec 0235): every row edits IN PLACE, one at
 * a time, each save a PATCH of that field alone.
 */
export function RewardStatusDetailView(props: RewardStatusDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.rewardStatus.permissions}>
      <RewardStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function RewardStatusDetailContent({ rewardStatus, onChanged }: RewardStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<RewardStatusFormMode>(() => ({ type: 'edit', rewardStatus }), [rewardStatus])
  const { form, onSubmit, serverError, clearServerError } = useRewardStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<RewardStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('rewardStatuses.form.genericError'),
  })
  const canEdit = rewardStatus.permissions.resource.update
  const createdAt = formatDateTime(rewardStatus.created_at)
  const updatedAt = formatDateTime(rewardStatus.updated_at)
  const tabs = rewardStatus.permissions.actions.view_activity
    ? [activityLogTab('reward-statuses', rewardStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={rewardStatus.name} icon={<Flag />} />} title={rewardStatus.name} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('rewardStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RewardStatusRows inline={inline} canEdit={canEdit} isSystemRow={rewardStatus.system_key !== null} />
                <RecordField label={t('rewardStatuses.detail.sort_order')}>{rewardStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('rewardStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('rewardStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
