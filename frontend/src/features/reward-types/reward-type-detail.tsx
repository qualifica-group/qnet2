import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Gift } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
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
import { RewardTypeRows } from '@/features/reward-types/reward-type-rows'
import { useRewardTypeForm, type RewardTypeFormValues } from '@/features/reward-types/use-reward-type-form'
import type { RewardTypeDetailWithPermissions, RewardTypeFormMode } from '@/features/reward-types/types'

interface RewardTypeDetailViewProps {
  rewardType: RewardTypeDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single reward type on the record kit: identity band, the
 * "Dettagli" rows, the activity in the side column, a metadata
 * footer. There is no edit page (spec 0235): every row edits IN PLACE, one at
 * a time, each save a PATCH of that field alone.
 */
export function RewardTypeDetailView(props: RewardTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.rewardType.permissions}>
      <RewardTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function RewardTypeDetailContent({ rewardType, onChanged }: RewardTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<RewardTypeFormMode>(() => ({ type: 'edit', rewardType }), [rewardType])
  const { form, onSubmit, serverError, clearServerError } = useRewardTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<RewardTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('rewardTypes.form.genericError'),
  })
  const canEdit = rewardType.permissions.resource.update
  const createdAt = formatDateTime(rewardType.created_at)
  const updatedAt = formatDateTime(rewardType.updated_at)
  const tabs = rewardType.permissions.actions.view_activity
    ? [activityLogTab('reward-types', rewardType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader media={<DetailMonogram name={rewardType.name} icon={<Gift />} />} title={rewardType.name} />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('rewardTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <RewardTypeRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('rewardTypes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('rewardTypes.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
