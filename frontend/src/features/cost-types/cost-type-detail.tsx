import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
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
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { CostTypeRows } from '@/features/cost-types/cost-type-rows'
import { useCostTypeForm, type CostTypeFormValues } from '@/features/cost-types/use-cost-type-form'
import { COST_TYPES_DOMAIN, type CostTypeDetailWithPermissions, type CostTypeFormMode } from '@/features/cost-types/types'

interface CostTypeDetailViewProps {
  costType: CostTypeDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single cost type on the record kit: identity band, the rows,
 * the activity in the side column, a metadata footer. There is no edit page:
 * every row edits IN PLACE, one at a time, each save a PATCH of that field
 * alone (spec 0249 D-5). The order is changed from the list's reorder sheet.
 */
export function CostTypeDetailView(props: CostTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.costType.permissions}>
      <CostTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CostTypeDetailContent({ costType, onChanged }: CostTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<CostTypeFormMode>(() => ({ type: 'edit', costType }), [costType])
  const { form, onSubmit, serverError, clearServerError } = useCostTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<CostTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('costTypes.form.genericError'),
  })
  const canEdit = costType.permissions.resource.update
  const createdAt = formatDateTime(costType.created_at)
  const updatedAt = formatDateTime(costType.updated_at)
  const tabs: RecordCollaborationTab[] = costType.permissions.actions.view_activity
    ? [activityLogTab(COST_TYPES_DOMAIN, costType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={<RecordCardHeader media={<DetailMonogram name={costType.name} icon={<Tags />} />} title={costType.name} />}
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('costTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <CostTypeRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('costTypes.detail.sortOrder')}>{costType.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('costTypes.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('costTypes.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
